<?php

namespace App\Services;

use App\Models\Receipt;
use App\Models\InstallmentPayment;
use App\Models\DebtPayment;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Support\Collection;

/**
 * Class ImportService
 *
 * This service handles the retrieval and aggregation of all import-related data,
 * including cash receipts, installment receipts, installment payments, and debt payments.
 */
class ImportService
{
    /**
     * Retrieve all import transactions (receipts, payments, etc.)
     *
     * @param array $data ['date' => 'YYYY-MM-DD' (optional)]
     * @return array
     */
    public function getAllImports($data): array
    {
        try {
            $date = $data['date'] ?? null;

            /** -----------------------------------------
             *  Cash Receipts (الإيصالات النقدية)
             * ----------------------------------------- */
            $cashReceipts = Receipt::with(['user:id,name','customer:id,name'])
                ->where('type', '1')
                ->when($date, fn($query) => $query->whereDate('receipt_date', $date))
                ->orderByDesc('created_at')
                ->get()
                ->map(fn($r) => [
                    'amount'        => $r->total_price ?? 0,
                    'date'          => $r->created_at->format('Y-m-d'),
                    'user'          => $r->user->name ?? 'غير معروف',
                    'type'          => 'نقدي',
                    'customer_name' => $r->customer->name ?? 'غير معروف',
                ]);

            /** -----------------------------------------
             *  Installment Receipts (الإيصالات بالأقساط)
             * ----------------------------------------- */
            $installmentReceipts = Receipt::with(['user:id,name', 'receiptProducts.installment','customer:id,name'])
                ->where('type', 'اقساط')
                ->when($date, fn($query) => $query->whereDate('receipt_date', $date))
                ->orderByDesc('created_at')
                ->get()
                ->map(fn($r) => [
                    'amount'        => $r->receiptProducts->sum(fn($product) => $product->installment->first_pay ?? 0),
                    'date'          => $r->created_at->format('Y-m-d'),
                    'user'          => $r->user->name ?? 'غير معروف',
                    'type'          => 'قسط',
                    'customer_name' => $r->customer->name ?? 'غير معروف',
                ]);

            /** -----------------------------------------
             *  Installment Payments (دفعات الأقساط)
             * ----------------------------------------- */
            $installmentPayments = InstallmentPayment::with([
                    'activities.user:id,name',
                    'installment:id,receipt_product_id',
                    'installment.receiptProduct:id,receipt_id',
                    'installment.receiptProduct.receipt:id,customer_id',
                    'installment.receiptProduct.receipt.customer:id,name',
                ])
                ->when($date, fn($q) => $q->whereDate('payment_date', $date))
                ->orderByDesc('payment_date')
                ->get()
                ->map(fn($p) => [
                    'amount'        => $p->amount ?? 0,
                    'date'          => optional($p->payment_date)->format('Y-m-d'),
   'user' => optional($p->activities->first()?->user)->name ?? 'غير معروف',
                    'type'          => 'قسط دين',
                    'customer_name' => $p->installment?->receiptProduct?->receipt?->customer->name ?? 'غير معروف',
                ]);

            /** -----------------------------------------
             *  Debt Payments (تسديد الديون)
             * ----------------------------------------- */
            $debtPayments = DebtPayment::with(['user:id,name', 'debt.customer:id,name'])
                ->when($date, fn($q) => $q->whereDate('payment_date', $date))
                ->orderByDesc('payment_date')
                ->get()
                ->map(fn($p) => [
                    'amount'        => $p->amount ?? 0,
                    'date'          => $p->payment_date->format('Y-m-d'),
                    'user'          => $p->user->name ?? 'غير معروف',
                    'type'          => 'تسديد دين',
                    'customer_name' => $p->debt?->customer?->name ?? 'غير معروف',
                ]);

            /** -----------------------------------------
             *  Merge All Collections (دمج جميع النتائج)
             * ----------------------------------------- */
            $merged = collect()
                ->merge($cashReceipts)
                ->merge($installmentReceipts)
                ->merge($installmentPayments)
                ->merge($debtPayments)
                ->values();

            $totalAmount = $merged->sum('amount');

            /** -----------------------------------------
             *  Successful Response
             * ----------------------------------------- */
            return [
                'status'  => 200,
                'message' => 'تم جلب بيانات الواردات بنجاح.',
                'data'    => [
                    'total_amount' => $totalAmount,
                    'imports'      => $merged,
                ],
            ];

        } catch (Exception $e) {
            Log::error('Error fetching import data: ' . $e->getMessage());

            return [
                'status'  => 500,
                'message' => 'حدث خطأ أثناء جلب بيانات الواردات.',
                'data'    => [],
            ];
        }
    }
}
