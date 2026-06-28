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

            // Collected installment payments
            $collectedInstallmentPayments = InstallmentPayment::when($startDate && $endDate, function ($q) use ($startDate, $endDate) {
                $q->whereBetween('payment_date', [$startDate, $endDate]);
            })->sum('amount');

            // Collected debt payments
            $collectedDebtPayments = DebtPayment::when($startDate && $endDate, function ($q) use ($startDate, $endDate) {
                $q->whereBetween('payment_date', [$startDate, $endDate]);
            })->sum('amount');

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
            $totaldebt     = Debt::with('debtPayments')
                ->when($startDate && $endDate, fn($q) => $q->whereBetween('debt_date', [$startDate, $endDate]))
                ->get()
                ->sum(function ($debt) {
                    return max(0, $debt->remaining_debt - $debt->debtPayments->sum('amount'));
                });

            $totalInstallmentSalesValueInPeriod = Receipt::when($startDate && $endDate, fn($q) => $q->whereBetween('receipt_date', [$startDate, $endDate]))
                ->where('type', 0)
                ->sum('total_price');

            $totalCashSalesRevenue = Receipt::when($startDate && $endDate, fn($q) => $q->whereBetween('receipt_date', [$startDate, $endDate]))
                ->where('type', 1)
                ->sum('total_price');

            $totalRevenueFromSalesInPeriod = $collectedDebtPayments + $collectedInstallmentPayments + $totalCashSalesRevenue;

            $cogsForPeriodSales = ReceiptProduct::when($startDate && $endDate, fn($q) =>
                    $q->whereHas('receipt', fn($r) => $r->whereBetween('receipt_date', [$startDate, $endDate])))
                ->sum(DB::raw('buying_price * quantity'));

            $totalSalesValueForProfit = $totalCashSalesRevenue + $totalInstallmentSalesValueInPeriod;

            $firstpay = Installment::when($startDate && $endDate, fn($q) =>
                    $q->whereHas('receiptProduct.receipt', fn($r) => $r->whereBetween('receipt_date', [$startDate, $endDate])))
                ->sum('first_pay');

            $adjustedCOGS = $totalInstallmentSalesValueInPeriod - $firstpay - $collectedInstallmentPayments;
            $grossProfitFromSalesInPeriod = $totalSalesValueForProfit - $cogsForPeriodSales;
            $operatingNetProfit = $grossProfitFromSalesInPeriod - $totalExpenses;

            // 1. Calculate Exchange Rate
            $dollarRate = (float) (\App\Models\Product::latest('id')->value('dollar_exchange') ?? 1530);
            if ($dollarRate <= 0) {
                $dollarRate = 1530;
            }

            // 2. Calculate Lifetime Inflows
            $cashSalesInflow = (float) \App\Models\Receipt::where('type', 1)->get()->sum(function($r) {
                return max(0, $r->total_price - ($r->discount ?? 0));
            });
            $installmentDownPayments = (float) \App\Models\Installment::sum('first_pay');
            $debtUpfrontPayments = (float) \App\Models\Receipt::where('type', 2)->sum('paid');
            $collectedInstallments = (float) \App\Models\InstallmentPayment::sum('amount');
            $collectedDebts = (float) \App\Models\DebtPayment::sum('amount');

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
