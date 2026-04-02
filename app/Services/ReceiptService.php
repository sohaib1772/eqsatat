<?php

namespace App\Services;

use Exception;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\ActivitiesLog;
use App\Events\ReceiptCreated;
use App\Models\ReceiptProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ReceiptService extends Service
{
    /**
     * Get all receipts with related user and customer info.
     */
    public function getAllReceipt(array $filteringData)
    {
        try {
            $page = request('page', 1);
            $cacheKey = 'receipts_' . $page . '_' . md5(json_encode($filteringData));
            $cacheKeys = Cache::get('all_receipts_keys', []);
            if (!in_array($cacheKey, $cacheKeys)) {
                $cacheKeys[] = $cacheKey;
                Cache::put('all_receipts_keys', $cacheKeys, now()->addHours(120));
            }
            $receipts = Cache::remember($cacheKey, now()->addMinutes(60), function () use ($filteringData) {
                return Receipt::with(['user:id,name', 'customer:id,name'])
                    ->when(!empty($filteringData), fn($query) => $query->filterBy($filteringData))
                    ->orderByDesc('receipt_date')->paginate(10);
            });

            return [
                'status'  => 200,
                'message' => 'تم استرجاع جميع الفواتير بنجاح',
                'data'    => $receipts,
            ];
        } catch (\Exception $e) {
            Log::error(' خطأ في استرجاع الفواتير: ' . $e->getMessage());

            return [
                'status'  => 500,
                'message' => ' حدث خطأ أثناء استرجاع الفواتير.',
            ];
        }
    }

    /**
     * Create a new receipt and its related products (and installments if needed).
     */
    public function createReceipt(array $data)
    {
        DB::beginTransaction();

        try {
            // ✅ التحقق المبكر من بيانات الأقساط
            if ($data['type'] === 'اقساط') {
                $this->validateInstallmentData($data['products']);
            }

            Log::info('Creating receipt', [
                'customer_id' => $data['customer_id'],
                'type' => $data['type'],
                'products_count' => count($data['products'])
            ]);

            $receipt = $this->storeReceipt($data);

            Log::info('Receipt created', ['receipt_id' => $receipt->id]);

            $this->storeReceiptProducts($receipt, $data['products'], $data['type']);

            Log::info('Receipt products stored successfully', ['receipt_id' => $receipt->id]);

            DB::commit();

            // ✅ تنظيف الكاش بعد النجاح
            $this->clearReceiptsCache();
            $this->clearProductsCache(); // ✅ تنظيف كاش المنتجات ليعكس التحديث في التعديلات

            return [
                'status' => 200,
                'message' => 'تم إنشاء الفاتورة بنجاح.',
            ];
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Error in createReceipt', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
                'data' => $data
            ]);

            return [
                'status' => 500,
                'message' => 'حدث خطأ أثناء إنشاء الفاتورة: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * ✅ Validate installment data before processing
     */
    protected function validateInstallmentData(array $products)
    {
        foreach ($products as $index => $productData) {
            $missingFields = [];

            if (!isset($productData['pay_cont'])) {
                $missingFields[] = 'عدد الأقساط';
            }
            if (!isset($productData['first_pay'])) {
                $missingFields[] = 'الدفعة الأولى';
            }
            if (!isset($productData['installment'])) {
                $missingFields[] = 'قيمة القسط';
            }
            if (!isset($productData['installment_type'])) {
                $missingFields[] = 'نوع القسط';
            }

            if (!empty($missingFields)) {
                throw new Exception(
                    "بيانات القسط غير مكتملة للمنتج رقم " . ($index + 1) .
                    " (ID: " . ($productData['product_id'] ?? 'غير محدد') . "). " .
                    "الحقول المفقودة: " . implode(', ', $missingFields)
                );
            }
        }
    }

    /**
     * Store receipt main record.
     */
    protected function storeReceipt(array $data)
    {
        $receipt = Receipt::create([
            'customer_id'    => $data['customer_id'],
            'receipt_number' => $data['receipt_number'],
            'type'           => $data['type'],
            'total_price'    => $data['total_price'],
            'discount'       => $data['discount_amount'] ?? 0,
            'paid'           => $data['paid_amount'] ?? 0,
            'notes'          => $data['notes'] ?? null,
            'receipt_date'   => $data['receipt_date'] ?? now(),
            'user_id'        => Auth::id(),
        ]);

        ActivitiesLog::create([
            'user_id'     => Auth::id(),
            'description' => 'تم اضافة فاتورة ذات الرقم : ' . $receipt->receipt_number,
            'type_id'     => $receipt->id,
            'type_type'   => Receipt::class,
        ]);

        if ($receipt->type === 'دين' || $receipt->type === 2) {
            $this->createLinkedDebt($receipt);
        }

        return $receipt;
    }

    /**
     * Store all products related to a receipt.
     */
    protected function storeReceiptProducts(Receipt $receipt, array $products, string $type)
    {
        foreach ($products as $index => $productData) {
            try {
                Log::info("Processing product {$index}", ['product_data' => $productData]);

                $product = Product::findOrFail($productData['product_id']);

                $buyingPrice = $product->getCalculatedBuyingPrice();
                $sellingPrice = $productData['selling_price'] ?? $product->getSellingPriceForReceiptType($type);

                $receiptProduct = $receipt->receiptProducts()->create([
                    'product_id'     => $productData['product_id'],
                    'description'    => $productData['description'] ?? null,
                    'quantity'       => $productData['quantity'],
                    'buying_price'   => $buyingPrice,
                    'selling_price'  => $sellingPrice,
                ]);

                Log::info("Receipt product created", ['receipt_product_id' => $receiptProduct->id]);

                // ✅ Handle installments قبل الـ event
                if ($type === 'اقساط') {
                    $this->createInstallment($receiptProduct, $productData);
                    Log::info("Installment created for product {$index}");
                }

                // ✅ Update inventory via event مع معالجة الأخطاء
                try {
                    event(new ReceiptCreated($productData['product_id'], $productData['quantity']));
                    Log::info("Event dispatched for product {$index}");
                } catch (Exception $eventException) {
                    // ✅ لا نريد أن فشل الـ event يوقف العملية
                    Log::error("Event failed but continuing", [
                        'product_id' => $productData['product_id'],
                        'error' => $eventException->getMessage()
                    ]);
                }

            } catch (Exception $e) {
                Log::error("Error processing product {$index}", [
                    'error' => $e->getMessage(),
                    'product_data' => $productData
                ]);

                throw new Exception("خطأ في معالجة المنتج رقم " . ($index + 1) . ": " . $e->getMessage());
            }
        }
    }

    /**
     * Create a new installment record.
     */
    protected function createInstallment(ReceiptProduct $receiptProduct, array $productData)
    {
        // ✅ Double check (defensive programming)
        if (!isset($productData['pay_cont'], $productData['first_pay'],
                   $productData['installment'], $productData['installment_type'])) {
            throw new Exception("بيانات القسط غير مكتملة للمنتج ID: " . $productData['product_id']);
        }

        $installment = $receiptProduct->installment()->create([
            'pay_cont'         => $productData['pay_cont'],
            'first_pay'        => $productData['first_pay'],
            'installment'      => $productData['installment'],
            'installment_type' => $productData['installment_type']
        ]);

        Log::info('Installment created', ['installment_id' => $installment->id]);

        return $installment;
    }

    /**
     * Create a debt record linked to a debt receipt.
     */
    protected function createLinkedDebt(Receipt $receipt)
    {
        $netDebt = $receipt->total_price - ($receipt->discount ?? 0);
        $paid = $receipt->paid ?? 0;
        
        // Ensure net debt doesn't go below zero if discount is somehow larger
        $remainingDebt = max(0, $netDebt - $paid);

        $debt = \App\Models\Debt::create([
            'customer_id'    => $receipt->customer_id,
            'remaining_debt' => $netDebt, // The original total debt before payments
            'payment_amount' => $netDebt, // Usually records the amount of debt
            'debt_date'      => $receipt->receipt_date ?? now(),
            'description'    => "دين بناءً على الفاتورة رقم: {$receipt->receipt_number}" . ($receipt->notes ? " - {$receipt->notes}" : ''),
            'user_id'        => Auth::id(),
        ]);

        if ($paid > 0) {
            $debt->debtPayments()->create([
                'amount'       => $paid,
                'user_id'      => Auth::id(),
                'payment_date' => $receipt->receipt_date ?? now(),
            ]);
        }

        return $debt;
    }

    /**
     * Sync the linked debt when a receipt is updated.
     */
    protected function syncLinkedDebt(Receipt $receipt)
    {
        $debt = \App\Models\Debt::where('customer_id', $receipt->customer_id)
            ->where('description', 'like', "دين بناءً على الفاتورة رقم: {$receipt->receipt_number}%")
            ->first();

        if ($receipt->type === 'دين' || $receipt->type === 2) {
            $netDebt = $receipt->total_price - ($receipt->discount ?? 0);
            $paid = $receipt->paid ?? 0;
            // Ensure net debt doesn't go below zero
            $remainingDebt = max(0, $netDebt - $paid);

            if ($debt) {
                // Update existing debt
                $debt->update([
                    'remaining_debt' => $netDebt,
                    'payment_amount' => $netDebt,
                    'debt_date'      => $receipt->receipt_date ?? $debt->debt_date,
                    'description'    => "دين بناءً على الفاتورة رقم: {$receipt->receipt_number}" . ($receipt->notes ? " - {$receipt->notes}" : ''),
                ]);

                // Update the initial payment if needed
                $initialPayment = $debt->debtPayments()->orderBy('id', 'asc')->first();
                if ($paid > 0) {
                    if ($initialPayment) {
                        $initialPayment->update([
                            'amount' => $paid,
                            'payment_date' => $receipt->receipt_date ?? $initialPayment->payment_date,
                        ]);
                    } else {
                        $debt->debtPayments()->create([
                            'amount'       => $paid,
                            'user_id'      => Auth::id(),
                            'payment_date' => $receipt->receipt_date ?? now(),
                        ]);
                    }
                } elseif ($initialPayment) {
                    // If paid was changed to 0 but a payment existed
                    $initialPayment->delete();
                }
            } else {
                // If it wasn't a debt receipt before but now it is
                $this->createLinkedDebt($receipt);
            }
        } else {
            // If the receipt is no longer a debt receipt, remove the debt
            if ($debt) {
                $debt->debtPayments()->delete();
                $debt->delete();
            }
        }
    }

    /**
     * Delete the linked debt when a receipt is deleted.
     */
    protected function deleteLinkedDebt(Receipt $receipt)
    {
        $debt = \App\Models\Debt::where('customer_id', $receipt->customer_id)
            ->where('description', 'like', "دين بناءً على الفاتورة رقم: {$receipt->receipt_number}%")
            ->first();

        if ($debt) {
            $debt->debtPayments()->delete();
            $debt->delete();
        }
    }

    /**
     * Update receipt and sync its products.
     */
    public function updateReceiptWithProducts(Receipt $receipt, array $data)
    {
        DB::beginTransaction();

        try {
            $receipt->load('receiptProducts');

            $existingReceiptProducts = $receipt->receiptProducts->keyBy('product_id');

            // التحقق من وجود المنتجات
            if (!isset($data['products']) || !is_array($data['products']) || empty($data['products'])) {
                DB::rollBack();
                return [
                    'status' => 422,
                    'message' => 'لا يمكن حذف جميع المنتجات، إذا أردت حذف المنتجات بالكامل استخدم عملية الحذف.',
                ];
            }

            $products = $data['products'];

            // ✅ التحقق من بيانات الأقساط إذا كان النوع أقساط
            if ($receipt->type === 'اقساط') {
                $this->validateInstallmentData($products);
            }

            // تحديث بيانات الفاتورة الأساسية
            $this->updateReceipt($receipt, $data);

            // المعالجة الطبيعية: حذف، تحديث، إضافة
            $currentProductIds = array_column($products, 'product_id');
            $deletedProductIds = $existingReceiptProducts->keys()->diff($currentProductIds);
            $addedProductIds = collect($currentProductIds)->diff($existingReceiptProducts->keys());
            $updatedProductIds = $existingReceiptProducts->keys()->intersect($currentProductIds);

            foreach ($deletedProductIds as $productIdToRemove) {
                $productToRemove = $existingReceiptProducts->get($productIdToRemove);

                try {
                    event(new ReceiptCreated($productToRemove->product_id, -$productToRemove->quantity));
                } catch (Exception $eventException) {
                    Log::error("Event failed during product deletion", [
                        'product_id' => $productToRemove->product_id,
                        'error' => $eventException->getMessage()
                    ]);
                }

                $productToRemove->delete();
            }

            foreach ($products as $productData) {
                $productId = $productData['product_id'];

                if ($addedProductIds->contains($productId)) {
                    $product = Product::findOrFail($productId);
                    $receiptType = $receipt->type;
                    $buyingPrice = $product->getCalculatedBuyingPrice();
                    $sellingPrice = $productData['selling_price'] ?? $product->getSellingPriceForReceiptType($receiptType);

                    $receiptProduct = $receipt->receiptProducts()->create([
                        'receipt_id'    => $receipt->id,
                        'product_id'    => $productId,
                        'description'   => $productData['description'] ?? null,
                        'quantity'      => (int)$productData['quantity'],
                        'buying_price'  => $buyingPrice,
                        'selling_price' => $sellingPrice,
                    ]);

                    try {
                        event(new ReceiptCreated($productId, (int)$productData['quantity']));
                    } catch (Exception $eventException) {
                        Log::error("Event failed during product addition", [
                            'product_id' => $productId,
                            'error' => $eventException->getMessage()
                        ]);
                    }

                    if ($receiptType === 'اقساط') {
                        $this->createInstallment($receiptProduct, $productData);
                    }
                } elseif ($updatedProductIds->contains($productId)) {
                    $receiptProduct = $existingReceiptProducts->get($productId);
                    $oldQuantity = (int)$receiptProduct->quantity;
                    $newQuantity = (int)$productData['quantity'];
                    $description = $productData['description'] ?? $receiptProduct->description;
                    $sellingPrice = $productData['selling_price'] ?? $receiptProduct->selling_price;

                    $receiptProduct->update([
                        'quantity'      => $newQuantity,
                        'description'   => $description,
                        'selling_price' => $sellingPrice,
                    ]);

                    $quantityDifference = $newQuantity - $oldQuantity;
                    if ($quantityDifference !== 0) {
                        try {
                            event(new ReceiptCreated($productId, $quantityDifference));
                        } catch (Exception $eventException) {
                            Log::error("Event failed during product update", [
                                'product_id' => $productId,
                                'error' => $eventException->getMessage()
                            ]);
                        }
                    }

                    if ($receipt->type === 'اقساط') {
                        $this->createOrUpdateInstallment($receiptProduct, $productData);
                    }
                }
            }

            $this->updateTotalPrice($receipt);

            // ✅ Sync Debt record if receipt type or totals changed
            $this->syncLinkedDebt($receipt);

            DB::commit();

            // ✅ تنظيف الكاش
            $this->clearReceiptsCache();
            $this->clearProductsCache(); // ✅ تنظيف كاش المنتجات ليعكس التحديث في التعديلات

            return [
                'status' => 200,
                'message' => 'تم تحديث الفاتورة بنجاح.',
            ];
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Error in updateReceiptWithProducts', [
                'receipt_id' => $receipt->id,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'data' => $data
            ]);

            return [
                'status' => 500,
                'message' => 'حدث خطأ أثناء تحديث الفاتورة: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Update receipt base info (not products).
     */
    protected function updateReceipt(Receipt $receipt, array $data): void
    {
        $receipt->update([
            'customer_id'  => $data['customer_id'] ?? $receipt->customer_id,
            'discount'     => isset($data['discount_amount']) ? $data['discount_amount'] : $receipt->discount,
            'paid'         => isset($data['paid_amount']) ? $data['paid_amount'] : $receipt->paid,
            'notes'        => $data['notes'] ?? $receipt->notes,
            'receipt_date' => $data['receipt_date'] ?? $receipt->receipt_date,
        ]);

        ActivitiesLog::create([
            'user_id'     => Auth::id(),
            'description' => 'تم تعديل فاتورة ذات الرقم : ' . $receipt->receipt_number,
            'type_id'     => $receipt->id,
            'type_type'   => Receipt::class,
        ]);
    }

    /**
     * Create or update an installment entry for a product.
     */
    protected function createOrUpdateInstallment(ReceiptProduct $receiptProduct, array $productData): void
    {
        $receiptProduct->installment()->updateOrCreate(
            [],
            [
                'pay_cont'         => $productData['pay_cont'],
                'first_pay'        => $productData['first_pay'],
                'installment'      => $productData['installment'],
                'installment_type' => $productData['installment_type']
            ]
        );
    }

    /**
     * Recalculate total price of the receipt.
     */
    protected function updateTotalPrice(Receipt $receipt): void
    {
        $receipt->load('receiptProducts');

        $totalPrice = $receipt->receiptProducts->sum(function ($receiptProduct) {
            return $receiptProduct->quantity * $receiptProduct->selling_price;
        });

        $receipt->update(['total_price' => $totalPrice]);
    }

    /**
     * Delete receipt and related products/installments.
     */
    public function deleteReceipt(Receipt $receipt)
    {
        DB::beginTransaction();

        try {
            $receipt->load('receiptProducts');

            if ($receipt->receiptProducts->isNotEmpty()) {
                foreach ($receipt->receiptProducts as $receiptProduct) {
                    try {
                        event(new ReceiptCreated($receiptProduct->product_id, -$receiptProduct->quantity));
                    } catch (Exception $eventException) {
                        Log::error("Event failed during receipt deletion", [
                            'product_id' => $receiptProduct->product_id,
                            'error' => $eventException->getMessage()
                        ]);
                    }
                }
            }

            // ✅ Delete the linked Debt record if it exists before deleting the receipt
            $this->deleteLinkedDebt($receipt);

            $receipt->delete();

            ActivitiesLog::create([
                'user_id'     => Auth::id(),
                'description' => 'تم حذف فاتورذات الرقم : ' . $receipt->receipt_number,
                'type_id'     => $receipt->id,
                'type_type'   => Receipt::class,
            ]);

            DB::commit();

            // ✅ تنظيف الكاش
            $this->clearReceiptsCache();
            $this->clearProductsCache(); // ✅ تنظيف كاش المنتجات ليعكس التحديث في التعديلات

            return [
                'status' => 200,
                'message' => 'تم حذف الفاتورة بنجاح.',
            ];
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Error in deleteReceipt', [
                'receipt_id' => $receipt->id,
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ]);

            return [
                'status' => 500,
                'message' => 'حدث خطأ أثناء حذف الفاتورة: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * ✅ Clear all receipts cache
     */
    protected function clearReceiptsCache(): void
    {
        try {
            $cacheKeys = Cache::get('all_receipts_keys', []);
            foreach ($cacheKeys as $key) {
                Cache::forget($key);
            }
            Cache::forget('all_receipts_keys');

            Log::info('Receipts cache cleared successfully');
        } catch (Exception $e) {
            Log::warning('Failed to clear receipts cache', ['error' => $e->getMessage()]);
        }
    }

    /**
     * ✅ Clear all products cache
     */
    protected function clearProductsCache(): void
    {
        try {
            $cacheKeys = Cache::get('all_products_keys', []);
            foreach ($cacheKeys as $key) {
                Cache::forget($key);
            }
            Cache::forget('all_products_keys');

            Log::info('Products cache cleared successfully from ReceiptService');
        } catch (Exception $e) {
            Log::warning('Failed to clear products cache', ['error' => $e->getMessage()]);
        }
    }
}
