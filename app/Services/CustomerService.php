<?php

namespace App\Services;

use DateTime;
use Exception;
use App\Models\Debt;
use App\Models\Receipt;
use App\Models\Customer;
use App\Models\DebtPayment;
use App\Models\ActivitiesLog;
use App\Models\InstallmentPayment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\QueryException;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\CustomerReceiptProduct;

use function PHPUnit\Framework\isEmpty;

/**use Illuminate\Support\Facades\Auth;

 * CustomerService
 *
 * This service provides methods for managing customer records,
 * including retrieving, creating, updating, and deleting customers.
 * It also supports caching and error logging for optimized performance.
 */
class CustomerService extends Service
{
    /**
     * Retrieve all customers with optional filtering and caching.
     *
     * @param array|null $filteringData Optional filters (e.g., name, phone).
     * @return array Structured success or error response.
     */




   public function getAllCustomers($filteringData)
{
    try {
        // $page = request('page', 1);
        // $cacheKey = 'customers_' . $page . (empty($filteringData) ? '' : md5(json_encode($filteringData)));
        // $cacheKeys = Cache::get('all_customers_keys', []);

        // if (!in_array($cacheKey, $cacheKeys)) {
        //     $cacheKeys[] = $cacheKey;
        //     Cache::put('all_customers_keys', $cacheKeys, now()->addHours(2));
        // }

        // return Cache::remember($cacheKey, now()->addMinutes(120), function () use ($filteringData) {
            $customers = Customer::query()
                ->when(!empty($filteringData), fn($query) => $query->filterBy($filteringData))
                ->with([
                    'receipts.receiptProducts.installment.installmentPayments',
                    'debts.debtPayments',
                    'amountReceipts'
                ])
                ->orderByDesc('created_at')
                ->paginate(10);

            $customers->getCollection()->transform(function ($customer) {
                $customer->total_remaining = $this->calculateTotalRemaining($customer);

                // إيجاد آخر دفعة (من الأقساط أو الديون)
                $latestInstallmentPaymentDate = $customer->receipts
                    ->flatMap(fn($r) => $r->receiptProducts)
                    ->flatMap(fn($rp) => $rp->installment ? $rp->installment->installmentPayments : collect())
                    ->sortByDesc('payment_date')
                    ->pluck('payment_date')
                    ->first();

                $latestDebtPaymentDate = $customer->debts
                    ->flatMap(fn($d) => $d->debtPayments)
                    ->sortByDesc('payment_date')
                    ->pluck('payment_date')
                    ->first();

                $lastestPaymentDate = null;
                if ($latestDebtPaymentDate && $latestInstallmentPaymentDate) {
                    $debtDate = new \DateTime($latestDebtPaymentDate);
                    $installmentDate = new \DateTime($latestInstallmentPaymentDate);
                    $lastestPaymentDate = ($debtDate > $installmentDate)
                        ? $debtDate
                        : $installmentDate;
                } else {
                    $lastestPaymentDate = $latestDebtPaymentDate
                        ? new \DateTime($latestDebtPaymentDate)
                        : ($latestInstallmentPaymentDate ? new \DateTime($latestInstallmentPaymentDate) : null);
                }

                // حساب الأيام منذ آخر دفعة أو فاتورة
                if ($lastestPaymentDate) {
                    $customer->days_since_last_payment = \Carbon\Carbon::parse($lastestPaymentDate)->diffInDays(now());
                } else {
                    // إذا ما في دفعات، احسب من تاريخ آخر فاتورة
                    $receiptDate = optional($customer->receipts->last())->receipt_date;
                    $customer->days_since_last_payment = $receiptDate
                        ? \Carbon\Carbon::parse($receiptDate)->diffInDays(now())
                        : null;
                }

                // لا نعرض آخر دفعة إذا ما في دفعات
                $customer->lastest_payment_date = null;

                return $customer;
            });

            return $this->successResponse('تم جلب بيانات العملاء بنجاح.', 200, $customers);
        // });
    } catch (\Illuminate\Database\QueryException $e) {
        Log::error('Database query error while retrieving customers: ' . $e->getMessage());
        return $this->errorResponse('فشل في جلب بيانات العملاء.');
    } catch (\Exception $e) {
        Log::error('General error while retrieving customers: ' . $e->getMessage());
        return $this->errorResponse('حدث خطأ أثناء استرجاع بيانات العملاء، يرجى المحاولة مرة أخرى.');
    }
}
// public function getAllCustomers($filteringData)
// {
//     try {
//         $page = request('page', 1);
//         $cacheKey = 'customers_' . $page . (empty($filteringData) ? '' : md5(json_encode($filteringData)));
//         $cacheKeys = Cache::get('all_customers_keys', []);

//         if (!in_array($cacheKey, $cacheKeys)) {
//             $cacheKeys[] = $cacheKey;
//             Cache::put('all_customers_keys', $cacheKeys, now()->addHours(2));
//         }

//         return Cache::remember($cacheKey, now()->addMinutes(120), function () use ($filteringData) {
//             $customers = Customer::query()
//                 ->when(!empty($filteringData), fn($query) => $query->filterBy($filteringData))
//                 ->with([
//                     'receipts.receiptProducts.installment.installmentPayments',
//                     'debts.debtPayments'
//                 ])
//                 ->orderByDesc('created_at')
//                 ->paginate(10);

//             $customers->getCollection()->transform(function ($customer) {
//                 $firstPays = 0;
//                 $receiptTotalPrice = 0;
//                 $installmentsPaid = 0;

//                 $receipts = Receipt::where('customer_id', $customer->id)
//                     ->where('type', 0)
//                     ->with([
//                         'receiptProducts',
//                         'receiptProducts.installment',
//                         'receiptProducts.installment.installmentPayments'
//                     ])
//                     ->get();

//                 foreach ($receipts as $receipt) {
//                     $receiptTotalPrice += $receipt->total_price;
//                     foreach ($receipt->receiptProducts as $receiptProduct) {
//                         if ($receiptProduct->installment) {
//                             $firstPays += $receiptProduct->installment->first_pay ?? 0;
//                             $installmentsPaid += $receiptProduct->installment->installmentPayments->sum('amount');
//                         }
//                     }
//                 }

//                 $remainingDebt = $customer->debts->sum('remaining_debt');
//                 $debtInstallmentsPaid = $customer->debts->sum(fn($debt) => $debt->debtPayments->sum('amount'));

//                 $totalRemaining = ($receiptTotalPrice - $firstPays - $installmentsPaid) + ($remainingDebt - $debtInstallmentsPaid);

//                 // آخر دفعة من الأقساط
//                 $latestInstallmentPaymentDate = InstallmentPayment::whereHas('installment.receiptProduct.receipt', function ($query) use ($customer) {
//                         $query->where('customer_id', $customer->id);
//                     })
//                     ->whereHas('installment', function ($query) {
//                         $query->where('status', 1);
//                     })
//                     ->latest('payment_date')
//                     ->value('payment_date');

//                 // آخر دفعة من الديون
//                 $latestDebtPaymentDate = DebtPayment::whereHas('debt', function ($query) use ($customer) {
//                         $query->where('customer_id', $customer->id);
//                     })
//                     ->latest('payment_date')
//                     ->value('payment_date');

//                 $lastestPaymentDate = null;
//                 if ($latestDebtPaymentDate && $latestInstallmentPaymentDate) {
//                     $debtDate = new DateTime($latestDebtPaymentDate);
//                     $installmentDate = new DateTime($latestInstallmentPaymentDate);
//                     $lastestPaymentDate = ($debtDate > $installmentDate) ? $debtDate->format('Y-m-d') : $installmentDate->format('Y-m-d');
//                 } else {
//                     $lastestPaymentDate = $latestDebtPaymentDate ?? $latestInstallmentPaymentDate;
//                 }

//                 $customer->total_remaining = $totalRemaining;

//                 // إذا ما في دفعات → خذ تاريخ آخر فاتورة
//                 if (empty($lastestPaymentDate)) {
//                     $lastestPaymentDate = optional($customer->receipts->last())->receipt_date;
//                 }

//                 $customer->lastest_payment_date = $lastestPaymentDate;

//                 // 🟢 الحقل الجديد: الأيام منذ آخر دفعة أو فاتورة
//                 if ($lastestPaymentDate) {
//                     $customer->days_since_last_payment = now()->diffInDays(\Carbon\Carbon::parse($lastestPaymentDate));
//                 } else {
//                     $customer->days_since_last_payment = null;
//                 }

//                 return $customer;
//             });

//             return $this->successResponse('تم جلب بيانات العملاء بنجاح.', 200, $customers);
//         });
//     } catch (QueryException $e) {
//         Log::error('Database query error while retrieving customers: ' . $e->getMessage());
//         return $this->errorResponse('فشل في جلب بيانات العملاء.');
//     } catch (Exception $e) {
//         Log::error('General error while retrieving customers: ' . $e->getMessage());
//         return $this->errorResponse('حدث خطأ أثناء استرجاع بيانات العملاء، يرجى المحاولة مرة أخرى.');
//     }
// }


    /**
     * Create a new customer record.
     *
     * @param array $data Customer details.
     * @return array Structured success or error response.
     */
    public function createCustomer(array $data): array
    {
        try {


            // Create the customer record
            $customer = Customer::create($data);
            $userId = Auth::id();

            ActivitiesLog::create([
                'user_id'     => $userId,
                'description' => 'تم إضافة زبون: ' . $customer->name,
                'type_id'     => $customer->id,
                'type_type'   => Customer::class,
            ]);

            return $this->successResponse('تم إنشاء العميل بنجاح.', 200);
        } catch (Exception $e) {
            Log::error('Error while creating customer: ' . $e->getMessage());

            return $this->errorResponse('حدث خطا اثناء انشاء  العميل  , يرجى المحاولة مرة اخرى ');;
        }
    }

    /**
     * Update an existing customer's information.
     *
     * @param array $data Updated customer details.
     * @param Customer $customer Customer model instance to update.
     * @return array Structured success or error response.
     */
    public function updateCustomer(array $data, Customer $customer): array
    {
        try {
            // Update the customer record
            $customer->update($data);
            $userId = Auth::id();

            ActivitiesLog::create([
                'user_id'     => $userId,
                'description' => 'تم تعديل زبون: ' . $customer->name,
                'type_id'     => $customer->id,
                'type_type'   => Customer::class,
            ]);

            return $this->successResponse('تم تحديث بيانات العميل بنجاح.', 200);
        } catch (Exception $e) {
            Log::error('Error while updating customer: ' . $e->getMessage());

            return $this->errorResponse('حدث خطا اثناء تحديث  العميل  , يرجى المحاولة مرة اخرى ');;
        }
    }

    /**
     * Delete a customer record from the database.
     *
     * @param Customer $customer Customer model instance to delete.
     * @return array Structured success or error response.
     */
    public function deleteCustomer(Customer $customer): array
    {
        try {


            $userId = Auth::id();

            ActivitiesLog::create([
                'user_id'     => $userId,
                'description' => 'تم حذف زبون: ' . $customer->name,
                'type_id'     => $customer->id,
                'type_type'   => Customer::class,
            ]);
            // Delete the customer recor

            $customer->delete();


            return $this->successResponse('تم حذف العميل بنجاح.', 200);
        } catch (Exception $e) {
            Log::error('Error while deleting customer: ' . $e->getMessage());
            return $this->errorResponse('حدث خطا اثناء حذف  العميل  , يرجى المحاولة مرة اخرى ');;
        }
    }

    /**
     * Retrieve debts for a specific customer.
     *
     * This method fetches debts associated with a given customer and includes
     * related payments for enhanced data visualization.
     *
     * @param int $id Customer ID.
     * @return array JSON response containing the customer's debts.
     */
    public function getCustomerDebts($id)
    {
        try {
            $debts = Debt::with('debtPayments')
                ->where('customer_id', $id)
                ->get();

            return $this->successResponse('تم استرجاع الديون بنجاح.', 200, $debts);
        } catch (Exception $e) {
            Log::error('Error retrieving debts: ' . $e->getMessage());
            return $this->errorResponse('حدث خطا اثناء استرجاع ديون العميل  , يرجى المحاولة مرة اخرى ');
        }
    }
    /**
     * Get receipts for a specific customer.
     */
    public function getCustomerReceipt($id)
    {
        try {
            $receipts = Receipt::with(['user:id,name'])
                ->where('customer_id', $id)
                ->orderByDesc('receipt_date')
                ->paginate(10);

            // تعديل الرصيد الظاهر لفواتير الدين ليعكس المبلغ المتبقي الفعلي مع إرجاع الخصم والتسديد
            $receipts->getCollection()->transform(function ($receipt) {
                // إرجاع الخصم والتسديد للـ Frontend
                $receipt->discount_amount = $receipt->discount ?? 0;
                $receipt->paid_amount = $receipt->paid ?? 0;

                if ($receipt->type === "دين" || $receipt->type === 2) {
                    $receipt->total_price = $receipt->total_price - $receipt->discount_amount - $receipt->paid_amount;
                }
                return $receipt;
            });

            return [
                'status'  => 200,
                'message' => 'تم استرجاع جميع فواتير العميل بنجاح',
                'data'    => $receipts,
            ];
        } catch (Exception $e) {
            Log::error('Error in getCustomerReceipt: ' . $e->getMessage());

            return $this->errorResponse('حدث خطا اثناء استرجاع فواتير العميل  , يرجى المحاولة مرة اخرى ');
        }
    }

    /**
     * Retrieve all receipt products for a specific customer, including installment details.
     *
     * This method fetches receipts with products, installment details, and installment payments for a specific customer.
     * The data is returned in a formatted structure using a resource.
     *
     * @param int $id Customer ID to filter the receipts.
     * @return array Structured response with success or error message in Arabic.
     */
    public function getCustomerReceiptProducts($id)
    {
        try {

            $receipts = Receipt::with([
                'receiptProducts' => function ($q) {
                    $q->select('id', 'receipt_id', 'product_id', 'quantity', 'selling_price');
                },
                'receiptProducts.product' => function ($q) {
                    $q->select('id', 'name');
                },
                'receiptProducts.installment' => function ($q) {
                    $q->select('id', 'receipt_product_id', 'pay_cont', 'first_pay', 'installment_type', 'status', 'installment', 'id');
                },
                'receiptProducts.installment.installmentPayments' => function ($q) {
                    $q->select('id', 'installment_id', 'payment_date', 'amount');
                },
            ])
                ->where('customer_id', $id)  // Filter receipts by the customer ID
                ->orderByDesc('receipt_date')
                ->where('type', 'اقساط')     // Filter only installment type receipts
                ->get();

            // Format the data by flattening it and converting it into the appropriate resource
            $formattedProducts = $receipts->flatMap(function ($receipt) {
                return $receipt->receiptProducts->map(function ($receiptProduct) {
                    return new CustomerReceiptProduct($receiptProduct);  // Transform each receipt product using a resource
                });
            });


            return $this->successResponse('تم جلب جميع منتجات العميل بنجاح.', 200, $formattedProducts);
        } catch (\Exception $e) {
            // Log any errors and return a failure response
            Log::error('Error in getCustomerReceiptProducts: ' . $e->getMessage());
            return $this->errorResponse('حدث خطا اثناء استرجاع منتجات العميل  , يرجى المحاولة مرة اخرى ');;
        }
    }
public function getCustomerById($id)
{
    try {

        $customer = Customer::with([
            'receipts.receiptProducts.installment.installmentPayments',
            'debts.debtPayments',
            'amountReceipts'
        ])
        ->findOrFail($id);

        $customer->total_remaining = $this->calculateTotalRemaining($customer);



        return $this->successResponse('تم جلب بيانات العميل بنجاح.', 200, $customer);

    } catch (QueryException $e) {
        Log::error('Database query error while retrieving customer: ' . $e->getMessage());
        return $this->errorResponse('فشل في جلب بيانات العميل.');
    } catch (Exception $e) {
        Log::error('General error while retrieving customer: ' . $e->getMessage());
        return $this->errorResponse('حدث خطأ أثناء استرجاع بيانات العميل، يرجى المحاولة مرة أخرى.');
    }
}

    /**
     * Calculate the total remaining debt for a customer.
     * Includes installment receipts, manual debts, and general amount receipts.
     *
     * @param Customer $customer
     * @return int
     */
    private function calculateTotalRemaining(Customer $customer): int
    {
        $firstPays = 0;
        $receiptTotalPrice = 0;
        $installmentsPaid = 0;

        // 1. Calculate debt from installment receipts (Type 0: اقساط)
        foreach ($customer->receipts->whereIn('type', ["اقساط", 0]) as $receipt) {
            // Include gross total minus receipt-level discounts and paid amounts
            $receiptTotalPrice += ($receipt->total_price - ($receipt->discount ?? 0) - ($receipt->paid ?? 0));

            foreach ($receipt->receiptProducts as $receiptProduct) {
                if ($receiptProduct->installment) {
                    $firstPays += $receiptProduct->installment->first_pay ?? 0;
                    $installmentsPaid += $receiptProduct->installment->installmentPayments->sum('amount');
                }
            }
        }

        // 2. Calculate debt from manual/linked debts table
        $remainingDebt = $customer->debts->sum('remaining_debt');
        $debtPayments = $customer->debts->sum(fn($debt) => $debt->debtPayments->sum('amount'));

        // Total Remaining = (Net Receipt Debt - Initial Pays - Installment Payments)
        //                  + (Normal Debt - Debt Payments)
        return ($receiptTotalPrice - $firstPays - $installmentsPaid)
               + ($remainingDebt - $debtPayments);
    }
}
