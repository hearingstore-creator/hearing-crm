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
        // 1. Add payment tracking columns to supplier_ledgers table
        Schema::table('supplier_ledgers', function (Blueprint $table) {
            if (!Schema::hasColumn('supplier_ledgers', 'payment_method')) {
                $table->string('payment_method', 50)->nullable()->after('type');
            }
            if (!Schema::hasColumn('supplier_ledgers', 'transaction_no')) {
                $table->string('transaction_no', 100)->nullable()->after('payment_method');
            }
            if (!Schema::hasColumn('supplier_ledgers', 'bank_name')) {
                $table->string('bank_name', 100)->nullable()->after('transaction_no');
            }
            if (!Schema::hasColumn('supplier_ledgers', 'linked_purchase_id')) {
                $table->unsignedBigInteger('linked_purchase_id')->nullable()->after('bank_name');
            }
        });

        // 2. Allow payment_id on purchase_masters to default to 4 (Credit / Pay Later)
        // payment_id: 4 = Credit, 1 = Cash, 2 = Bank Deposit, 3 = Online Transfer
        Schema::table('purchase_masters', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_masters', 'payment_id')) {
                $table->integer('payment_id')->default(4)->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_ledgers', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'transaction_no', 'bank_name', 'linked_purchase_id']);
        });

        Schema::table('purchase_masters', function (Blueprint $table) {
            $table->integer('payment_id')->default(1)->change();
        });
    }
};
