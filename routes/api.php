<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DebtController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\WhatsappController;
use App\Http\Controllers\DebtPaymentController;
use App\Http\Controllers\ActivitiesLogController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ProductOriginController;
use App\Http\Controllers\ReceiptProductController;
use App\Http\Controllers\FinancialReportController;
use App\Http\Controllers\FinancialTransactionController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\InstallmentPaymentController;
use App\Http\Controllers\AmountReceiptController;
use PHPUnit\Framework\Attributes\BackupStaticProperties;

/**
 *  Authentication Routes
 * -------------------------
 * Routes for handling user login/logout and token refresh using JWT.
 */
Route::post('/login', [AuthController::class, 'login']);      //  Authenticate user and generate token
Route::post('/logout', [AuthController::class, 'logout']);    //  Logout user and invalidate token
Route::post('/refresh', [AuthController::class, 'refresh']);  //  Refresh JWT token

/**
 *  Get authenticated user info
 * -------------------------------
 * Return authenticated user's data using Sanctum middleware.
 */
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/**
 *  Public API Routes
 * --------------------
 * Routes that can be accessed without authentication.
 */
Route::get('productOrigin', [ProductOriginController::class, 'index']);          //  Get product origins
Route::get('productCategory', [ProductCategoryController::class, 'index']);      //  Get product categories
Route::post('/send-whatsapp', [WhatsappController::class, 'sendMessage']);
/**
 *  Protected Routes (Requires JWT Authentication)
 * -------------------------------------------------
 * Grouped routes secured by `jwt` middleware.
 */
Route::middleware('jwt')->group(function () {

    //  User Management (CRUD)
    Route::apiResource('user', UserController::class)->names([
        'index' => 'user.list',
        'store' => 'user.create',
        'show' => 'user.details',
        'update' => 'user.update',
        'destroy' => 'user.delete'
    ]);

    //  Customer Management (CRUD)
    Route::apiResource('customer', CustomerController::class)->names([
        'index' => 'customer.list',
        'store' => 'customer.create',
        'show' => 'customer.details',
        'update' => 'customer.update',
        'destroy' => 'customer.delete'
    ]);

    //  Product Management (CRUD)
    Route::apiResource('product', ProductController::class)->names([
        'index' => 'product.list',
        'store' => 'product.create',
        'show' => 'product.details',
        'update' => 'product.update',
        'destroy' => 'product.delete'
    ]);
    Route::get('print-items', [ProductController::class, 'printItems']);

    //  Product Categories Management (Create/Update/Delete)
    Route::post('productCategory', [ProductCategoryController::class, 'store']);            //  Add category
    Route::put('productCategory/{productCategory}', [ProductCategoryController::class, 'update']);  //  Edit category
    Route::delete('productCategory/{productCategory}', [ProductCategoryController::class, 'destroy'])->name("productCategory.delete");  //  Delete category

    //  WhatsApp Messaging
    Route::get('getmessage', [WhatsappController::class, 'index'])->name('whatsappMessage.list');  //  Fetch WhatsApp messages

    //  Financial Reports & Activity Logs
    Route::get('/financialReport', [FinancialReportController::class, 'index'])->name('financialReport.list');  //  View financial reports
    Route::get('/activiteLog', [ActivitiesLogController::class, 'index'])->name('activiteLog.list');            //  View activity logs

    //  Receipt Management
    Route::apiResource('/receipt', ReceiptController::class);  // Manage receipts (CRUD)

    //  Receipt Product Management
    Route::get('receiptProducts/{id}', [ReceiptProductController::class, 'getreciptProduct']);  //  View receipt products by receipt ID

    //  Installment & Payment Management
    Route::post('installments/{id}/payments', [InstallmentPaymentController::class, 'store']);         //  Pay specific installment
    Route::post('installment/customer/{id}', [InstallmentPaymentController::class, 'installmentPaymentReceipt']);  //  General installment payment for customer
    Route::apiResource('/installmentPayments', InstallmentPaymentController::class);  //  Manage installment payments

    //  Payment Management
    Route::apiResource('/payment', PaymentController::class);  // Full payment control (CRUD)

    //  Debt Management
    Route::apiResource('/debt', DebtController::class);                // Manage debts
    Route::apiResource('/debtPayments', DebtPaymentController::class); // Handle payments toward debts

    //  Amount Receipt Management
    Route::apiResource('/amountReceipt', AmountReceiptController::class);

    //  Customer-Specific Details
    Route::get('debts/customer/{id}', [CustomerController::class, 'getCustomerDebts']);  //  Fetch customer debts
    Route::get('receiptProducts/customer/{id}', [CustomerController::class, 'getCustomerReceiptProducts']);  //  Fetch products per customer
    Route::get('receipt/customer/{id}', [CustomerController::class, 'getCustomerReceipt']);  //  View all receipts by customer

    //  Agent Management
    Route::apiResource('agent', AgentController::class)->names([
        'index' => 'agent.list',
        'store' => 'agent.create',
        'show' => 'agent.details',
        'update' => 'agent.update',
        'destroy' => 'agent.delete'
    ]);

    //  Agent Financial Transactions
    Route::get('financialtransaction/agent/{id}', [AgentController::class, 'getaAgentFinancialTransactions']);  //  Agent transactions
    Route::post('financialtransaction/payment/agent/{id}', [FinancialTransactionController::class, 'StorePaymentFinancialTransaction']);  //  Payment to agent
    Route::put('financialtransaction/payment/{id}', [FinancialTransactionController::class, 'UpdatePaymentFinancialTransaction']);  //  Update agent payment
    Route::post('financialtransaction/debt/agent/{id}', [FinancialTransactionController::class, 'StoreDebtFinancialTransaction']);
    Route::put('financialtransaction/debt/{id}', [FinancialTransactionController::class, 'UpdateDebtFinancialTransaction']);
    Route::apiResource('/financialtransaction', FinancialTransactionController::class);  //  Manage financial transactions
    Route::get("/backupDownload", [BackupController::class, 'index']);
    Route::get("/imports",[ImportController::class,'index']);
 Route::get("/exports",[ExportController::class,'index']);

});
