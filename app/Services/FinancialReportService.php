<?php

namespace App\Services;

use Exception;
use Carbon\Carbon;
use App\Models\Debt;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\DebtPayment;
use App\Models\Installment;
use App\Models\ReceiptProduct;
use App\Models\InstallmentPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\FinancialTransaction;

class FinancialReportService extends Service
{
    public function GetFinancialReport($data): array
    {
        try {
            // Parse dates only if موجودة، وإلا نرجع كل البيانات
            $startDate = $data['start_date'] ?? null;
            $endDate   = $data['end_date'] ?? null;

            // 1. Calculate Exchange Rate early for currency conversions
            $dollarRate = (float) (\App\Models\Product::latest('id')->value('dollar_exchange') ?? 1530);
            if ($dollarRate <= 0) {
                $dollarRate = 1530;
            }

            // Collected installment payments by customer currency
            $collectedInstallmentsDinar = (float) (InstallmentPayment::query()
                ->join('installments', 'installments.id', '=', 'installment_payments.installment_id')
                ->join('receipt_products', 'receipt_products.id', '=', 'installments.receipt_product_id')
                ->join('receipts', 'receipts.id', '=', 'receipt_products.receipt_id')
                ->join('customers', 'customers.id', '=', 'receipts.customer_id')
                ->where('customers.type', 1)
                ->when($startDate && $endDate, fn($q) => $q->whereBetween('installment_payments.payment_date', [$startDate, $endDate]))
                ->sum('installment_payments.amount') ?? 0);

            $collectedInstallmentsDollar = (float) (InstallmentPayment::query()
                ->join('installments', 'installments.id', '=', 'installment_payments.installment_id')
                ->join('receipt_products', 'receipt_products.id', '=', 'installments.receipt_product_id')
                ->join('receipts', 'receipts.id', '=', 'receipt_products.receipt_id')
                ->join('customers', 'customers.id', '=', 'receipts.customer_id')
                ->where('customers.type', 0)
                ->when($startDate && $endDate, fn($q) => $q->whereBetween('installment_payments.payment_date', [$startDate, $endDate]))
                ->sum('installment_payments.amount') ?? 0);

            $collectedInstallmentPayments = $collectedInstallmentsDinar + ($collectedInstallmentsDollar * $dollarRate);

            // Collected debt payments by customer currency
            $collectedDebtPaymentsDinar = (float) (DebtPayment::query()
                ->join('debts', 'debts.id', '=', 'debt_payments.debt_id')
                ->join('customers', 'customers.id', '=', 'debts.customer_id')
                ->where('customers.type', 1)
                ->when($startDate && $endDate, fn($q) => $q->whereBetween('debt_payments.payment_date', [$startDate, $endDate]))
                ->sum('debt_payments.amount') ?? 0);

            $collectedDebtPaymentsDollar = (float) (DebtPayment::query()
                ->join('debts', 'debts.id', '=', 'debt_payments.debt_id')
                ->join('customers', 'customers.id', '=', 'debts.customer_id')
                ->where('customers.type', 0)
                ->when($startDate && $endDate, fn($q) => $q->whereBetween('debt_payments.payment_date', [$startDate, $endDate]))
                ->sum('debt_payments.amount') ?? 0);

            $collectedDebtPayments = $collectedDebtPaymentsDinar + ($collectedDebtPaymentsDollar * $dollarRate);

            // Financial transactions grouped by agent type & transaction type
            $transactions = FinancialTransaction::select(
                    'agents.type as agentType',
                    'financial_transactions.type as transactionType',
                    DB::raw('SUM(paid_amount) as totalPaid'),
                    DB::raw('SUM(GREATEST(0, total_amount - discount_amount - paid_amount)) as remainingDebt'),
                    DB::raw('SUM(total_amount) as totalAmount')
                )
                ->join('agents', 'agents.id', '=', 'financial_transactions.agent_id')
                ->when($startDate && $endDate, fn($q) => $q->whereBetween('transaction_date', [$startDate, $endDate]))
                ->where('agents.status', 0)
                ->groupBy('agents.type', 'financial_transactions.type')
                ->get();

            $map = [
                1 => ['paymentField' => 'collecteFinancialTransactionPaymentsDinar', 'debtField' => 'collecteFinancialTransactionDebtsDinar'],
                0 => ['paymentField' => 'collecteFinancialTransactionPaymentsDolar', 'debtField' => 'collecteFinancialTransactionDebtsDolar'],
            ];

            $financialTransactions = [
                'collecteFinancialTransactionPaymentsDinar' => 0,
                'collecteFinancialTransactionDebtsDinar'    => 0,
                'collecteFinancialTransactionPaymentsDolar' => 0,
                'collecteFinancialTransactionDebtsDolar'    => 0,
            ];

            foreach ($transactions as $row) {
                $fields = $map[$row->agentType] ?? null;
                if (!$fields) continue;

                if (in_array($row->transactionType, [0, 1])) {
                    $financialTransactions[$fields['paymentField']] += (float) $row->totalPaid;
                }
                if ($row->transactionType == 0) {
                    $financialTransactions[$fields['debtField']] += (float) $row->remainingDebt;
                }
                if ($row->transactionType == 3) {
                    $financialTransactions[$fields['debtField']] += (float) $row->totalAmount;
                }
            }

            // Other calculations
            $totalExpenses = Payment::when($startDate && $endDate, fn($q) => $q->whereBetween('payment_date', [$startDate, $endDate]))->sum('amount');

            // Normal Debts by customer currency
            $debtsDinar = (float) Debt::with(['debtPayments'])
                ->whereHas('customer', fn($q) => $q->where('type', 1))
                ->when($startDate && $endDate, fn($q) => $q->whereBetween('debts.debt_date', [$startDate, $endDate]))
                ->get()
                ->sum(fn($d) => max(0, $d->remaining_debt - $d->debtPayments->sum('amount')));

            $debtsDollar = (float) Debt::with(['debtPayments'])
                ->whereHas('customer', fn($q) => $q->where('type', 0))
                ->when($startDate && $endDate, fn($q) => $q->whereBetween('debts.debt_date', [$startDate, $endDate]))
                ->get()
                ->sum(fn($d) => max(0, $d->remaining_debt - $d->debtPayments->sum('amount')));

            $totaldebt = $debtsDinar + ($debtsDollar * $dollarRate);

            // Installment sales in period
            $totalInstallmentSalesDinar = (float) (Receipt::query()
                ->join('customers', 'customers.id', '=', 'receipts.customer_id')
                ->where('receipts.type', 0)
                ->where('customers.type', 1)
                ->when($startDate && $endDate, fn($q) => $q->whereBetween('receipts.receipt_date', [$startDate, $endDate]))
                ->sum('receipts.total_price') ?? 0);

            $totalInstallmentSalesDollar = (float) (Receipt::query()
                ->join('customers', 'customers.id', '=', 'receipts.customer_id')
                ->where('receipts.type', 0)
                ->where('customers.type', 0)
                ->when($startDate && $endDate, fn($q) => $q->whereBetween('receipts.receipt_date', [$startDate, $endDate]))
                ->sum('receipts.total_price') ?? 0);

            $totalInstallmentSalesValueInPeriod = $totalInstallmentSalesDinar + ($totalInstallmentSalesDollar * $dollarRate);

            // Cash sales in period
            $totalCashSalesDinar = (float) (Receipt::query()
                ->join('customers', 'customers.id', '=', 'receipts.customer_id')
                ->where('receipts.type', 1)
                ->where('customers.type', 1)
                ->when($startDate && $endDate, fn($q) => $q->whereBetween('receipts.receipt_date', [$startDate, $endDate]))
                ->sum('receipts.total_price') ?? 0);

            $totalCashSalesDollar = (float) (Receipt::query()
                ->join('customers', 'customers.id', '=', 'receipts.customer_id')
                ->where('receipts.type', 1)
                ->where('customers.type', 0)
                ->when($startDate && $endDate, fn($q) => $q->whereBetween('receipts.receipt_date', [$startDate, $endDate]))
                ->sum('receipts.total_price') ?? 0);

            $totalCashSalesRevenue = $totalCashSalesDinar + ($totalCashSalesDollar * $dollarRate);

            $totalRevenueFromSalesInPeriod = $collectedDebtPayments + $collectedInstallmentPayments + $totalCashSalesRevenue;

            $cogsForPeriodSales = ReceiptProduct::when($startDate && $endDate, fn($q) =>
                    $q->whereHas('receipt', fn($r) => $r->whereBetween('receipt_date', [$startDate, $endDate])))
                ->sum(DB::raw('buying_price * quantity'));

            $totalSalesValueForProfit = $totalCashSalesRevenue + $totalInstallmentSalesValueInPeriod;

            // First pay (down payments)
            $firstpayDinar = (float) (Installment::query()
                ->join('receipt_products', 'receipt_products.id', '=', 'installments.receipt_product_id')
                ->join('receipts', 'receipts.id', '=', 'receipt_products.receipt_id')
                ->join('customers', 'customers.id', '=', 'receipts.customer_id')
                ->where('customers.type', 1)
                ->when($startDate && $endDate, fn($q) => $q->whereBetween('receipts.receipt_date', [$startDate, $endDate]))
                ->sum('installments.first_pay') ?? 0);

            $firstpayDollar = (float) (Installment::query()
                ->join('receipt_products', 'receipt_products.id', '=', 'installments.receipt_product_id')
                ->join('receipts', 'receipts.id', '=', 'receipt_products.receipt_id')
                ->join('customers', 'customers.id', '=', 'receipts.customer_id')
                ->where('customers.type', 0)
                ->when($startDate && $endDate, fn($q) => $q->whereBetween('receipts.receipt_date', [$startDate, $endDate]))
                ->sum('installments.first_pay') ?? 0);

            $firstpay = $firstpayDinar + ($firstpayDollar * $dollarRate);

            $adjustedCOGSDinar = max(0, $totalInstallmentSalesDinar - $firstpayDinar - $collectedInstallmentsDinar);
            $adjustedCOGSDollar = max(0, $totalInstallmentSalesDollar - $firstpayDollar - $collectedInstallmentsDollar);
            $adjustedCOGS = $adjustedCOGSDinar + ($adjustedCOGSDollar * $dollarRate);

            $customerDebtsDinar = $adjustedCOGSDinar + $debtsDinar;
            $customerDebtsDollar = $adjustedCOGSDollar + $debtsDollar;

            $customerSalesDinar = $totalInstallmentSalesDinar + $totalCashSalesDinar;
            $customerSalesDollar = $totalInstallmentSalesDollar + $totalCashSalesDollar;

            $customerCollectedDinar = $collectedInstallmentsDinar + $collectedDebtPaymentsDinar + $totalCashSalesDinar;
            $customerCollectedDollar = $collectedInstallmentsDollar + $collectedDebtPaymentsDollar + $totalCashSalesDollar;

            $grossProfitFromSalesInPeriod = $totalSalesValueForProfit - $cogsForPeriodSales;
            $operatingNetProfit = $grossProfitFromSalesInPeriod - $totalExpenses;

            // 2. Calculate Lifetime Inflows (converting dollar customers to dinar)
            $cashSalesInflow = (float) \App\Models\Receipt::with('customer')
                ->where('type', 1)->get()->sum(function($r) use ($dollarRate) {
                    $val = max(0, $r->total_price - ($r->discount ?? 0));
                    $isDollar = ($r->customer && $r->customer->getRawOriginal('type') == 0);
                    return $isDollar ? ($val * $dollarRate) : $val;
                });
            $installmentDownPayments = (float) (Installment::query()
                ->join('receipt_products', 'receipt_products.id', '=', 'installments.receipt_product_id')
                ->join('receipts', 'receipts.id', '=', 'receipt_products.receipt_id')
                ->join('customers', 'customers.id', '=', 'receipts.customer_id')
                ->selectRaw('SUM(CASE WHEN customers.type = 0 THEN installments.first_pay * ? ELSE installments.first_pay END) as total', [$dollarRate])
                ->value('total') ?? 0);
            $debtUpfrontPayments = (float) (Receipt::query()
                ->join('customers', 'customers.id', '=', 'receipts.customer_id')
                ->where('receipts.type', 2)
                ->selectRaw('SUM(CASE WHEN customers.type = 0 THEN receipts.paid * ? ELSE receipts.paid END) as total', [$dollarRate])
                ->value('total') ?? 0);
            $collectedInstallments = (float) (InstallmentPayment::query()
                ->join('installments', 'installments.id', '=', 'installment_payments.installment_id')
                ->join('receipt_products', 'receipt_products.id', '=', 'installments.receipt_product_id')
                ->join('receipts', 'receipts.id', '=', 'receipt_products.receipt_id')
                ->join('customers', 'customers.id', '=', 'receipts.customer_id')
                ->selectRaw('SUM(CASE WHEN customers.type = 0 THEN installment_payments.amount * ? ELSE installment_payments.amount END) as total', [$dollarRate])
                ->value('total') ?? 0);
            $collectedDebts = (float) (DebtPayment::query()
                ->join('debts', 'debts.id', '=', 'debt_payments.debt_id')
                ->join('customers', 'customers.id', '=', 'debts.customer_id')
                ->selectRaw('SUM(CASE WHEN customers.type = 0 THEN debt_payments.amount * ? ELSE debt_payments.amount END) as total', [$dollarRate])
                ->value('total') ?? 0);

            $totalLifetimeInflows = $cashSalesInflow + $installmentDownPayments + $debtUpfrontPayments + $collectedInstallments + $collectedDebts;

            // 3. Calculate Lifetime Outflows
            $lifetimeExpenses = (float) \App\Models\Payment::sum('amount');

            $paidToDinarAgents = (float) \App\Models\FinancialTransaction::join('agents', 'agents.id', '=', 'financial_transactions.agent_id')
                ->where('agents.type', 1) // Dinar
                ->where('agents.status', 0)
                ->whereIn('financial_transactions.type', [0, 1])
                ->sum('paid_amount');

            $paidToDollarAgentsUSD = (float) \App\Models\FinancialTransaction::join('agents', 'agents.id', '=', 'financial_transactions.agent_id')
                ->where('agents.type', 0) // Dollar
                ->where('agents.status', 0)
                ->whereIn('financial_transactions.type', [0, 1])
                ->sum('paid_amount');

            $paidToDollarAgentsIQD = $paidToDollarAgentsUSD * $dollarRate;

            $totalLifetimeOutflows = $lifetimeExpenses + $paidToDinarAgents + $paidToDollarAgentsIQD;

            // 4. Current Cash
            $currentCash = $totalLifetimeInflows - $totalLifetimeOutflows;

            // 5. Stock Value
            $stockValue = (float) \App\Models\Product::sum(DB::raw('quantity * dolar_buying_price * dollar_exchange'));
            $stockValueUsd = (float) \App\Models\Product::sum(DB::raw('quantity * dolar_buying_price'));

            // 6. Capital
            $capital = $currentCash + $stockValue;

            return $this->successResponse(
                'Financial report retrieved successfully',
                200,
                [
                    'period' => [
                        'startDate' => $startDate ?? null,
                        'endDate'   => $endDate ?? null,
                    ],
                    'income_statement_summary' => [
                        'total_installment_sales_value_in_period' => (float) $totalInstallmentSalesValueInPeriod,
                        'total_revenue_from_sales_in_period'      => (float) $totalRevenueFromSalesInPeriod,
                        'total_expenses_in_period'                => (float) $totalExpenses,
                        'operating_net_profit_in_period'          => (float) $operatingNetProfit,
                        'adjustedCOGS'                            => (float) $adjustedCOGS,
                        'totaldebt'                               => (float) $totaldebt,
                        'collectedDebtPayments'                   => (float) $collectedDebtPayments,
                        'current_cash'                            => (float) $currentCash,
                        'stock_value'                             => (float) $stockValue,
                        'stock_value_usd'                         => (float) $stockValueUsd,
                        'capital'                                 => (float) $capital,
                        'dollar_rate'                             => (float) $dollarRate,
                        'customer_debts_dinar'                    => (float) $customerDebtsDinar,
                        'customer_debts_dollar'                   => (float) $customerDebtsDollar,
                        'customer_installment_debts_dinar'        => (float) $adjustedCOGSDinar,
                        'customer_installment_debts_dollar'       => (float) $adjustedCOGSDollar,
                        'customer_normal_debts_dinar'             => (float) $debtsDinar,
                        'customer_normal_debts_dollar'            => (float) $debtsDollar,
                        'customer_sales_dinar'                    => (float) $customerSalesDinar,
                        'customer_sales_dollar'                   => (float) $customerSalesDollar,
                        'customer_collected_dinar'                => (float) $customerCollectedDinar,
                        'customer_collected_dollar'               => (float) $customerCollectedDollar,
                    ] + $financialTransactions,
                    'cash_flow_summary' => [
                        'cash_inflow_from_collected_installments' => (float) $collectedInstallmentPayments,
                    ],
                ]
            );

        } catch (Exception $e) {
            Log::error("Unexpected error in GetFinancialReport: " . $e->getMessage());
            return $this->errorResponse('حدث خطأ أثناء استرجاع التقرير المالي، يرجى المحاولة مرة اخرى.');
        }
    }
}
