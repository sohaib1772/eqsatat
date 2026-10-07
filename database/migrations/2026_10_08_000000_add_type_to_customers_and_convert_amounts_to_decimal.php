<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add currency type to customers table (1 = دينار, 0 = دولار)
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'type')) {
                $table->tinyInteger('type')->default(1)->after('notes');
            }
        });

        // 2. Convert receipts monetary columns to decimal(15, 2)
        Schema::table('receipts', function (Blueprint $table) {
            $table->decimal('total_price', 15, 2)->change();
            $table->decimal('discount', 15, 2)->default(0)->change();
            $table->decimal('paid', 15, 2)->default(0)->change();
        });

        // 3. Convert receipt_products monetary columns to decimal(15, 2)
        Schema::table('receipt_products', function (Blueprint $table) {
            $table->decimal('selling_price', 15, 2)->change();
            $table->decimal('buying_price', 15, 2)->change();
        });

        // 4. Convert installments monetary columns to decimal(15, 2)
        Schema::table('installments', function (Blueprint $table) {
            $table->decimal('first_pay', 15, 2)->change();
            $table->decimal('installment', 15, 2)->change();
        });

        // 5. Convert installment_payments monetary columns to decimal(15, 2)
        Schema::table('installment_payments', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->change();
        });

        // 6. Convert debts monetary columns to decimal(15, 2)
        Schema::table('debts', function (Blueprint $table) {
            $table->decimal('payment_amount', 15, 2)->change();
            $table->decimal('remaining_debt', 15, 2)->change();
        });

        // 7. Convert debt_payments monetary columns to decimal(15, 2)
        Schema::table('debt_payments', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->change();
        });

        // 8. Convert amount_receipts monetary columns to decimal(15, 2)
        Schema::table('amount_receipts', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->change();
            $table->decimal('remaining_amount', 15, 2)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'type')) {
                $table->dropColumn('type');
            }
        });

        Schema::table('receipts', function (Blueprint $table) {
            $table->integer('total_price')->change();
            $table->integer('discount')->default(0)->change();
            $table->integer('paid')->default(0)->change();
        });

        Schema::table('receipt_products', function (Blueprint $table) {
            $table->integer('selling_price')->change();
            $table->integer('buying_price')->change();
        });

        Schema::table('installments', function (Blueprint $table) {
            $table->integer('first_pay')->change();
            $table->integer('installment')->change();
        });

        Schema::table('installment_payments', function (Blueprint $table) {
            $table->integer('amount')->change();
        });

        Schema::table('debts', function (Blueprint $table) {
            $table->unsignedInteger('payment_amount')->change();
            $table->unsignedInteger('remaining_debt')->change();
        });

        Schema::table('debt_payments', function (Blueprint $table) {
            $table->integer('amount')->change();
        });

        Schema::table('amount_receipts', function (Blueprint $table) {
            $table->integer('amount')->change();
            $table->integer('remaining_amount')->change();
        });
    }
};
