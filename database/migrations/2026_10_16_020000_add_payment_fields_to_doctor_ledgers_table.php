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
        Schema::table('doctor_ledgers', function (Blueprint $table) {
            if (!Schema::hasColumn('doctor_ledgers', 'payment_method')) {
                $table->string('payment_method', 50)->nullable()->after('type');
            }
            if (!Schema::hasColumn('doctor_ledgers', 'bank_name')) {
                $table->string('bank_name', 100)->nullable()->after('payment_method');
            }
            if (!Schema::hasColumn('doctor_ledgers', 'transaction_no')) {
                $table->string('transaction_no', 100)->nullable()->after('bank_name');
            }
            if (!Schema::hasColumn('doctor_ledgers', 'settlement_type')) {
                $table->string('settlement_type', 30)->nullable()->after('transaction_no'); // 'month', 'custom', 'general'
            }
            if (!Schema::hasColumn('doctor_ledgers', 'settlement_period_start')) {
                $table->date('settlement_period_start')->nullable()->after('settlement_type');
            }
            if (!Schema::hasColumn('doctor_ledgers', 'settlement_period_end')) {
                $table->date('settlement_period_end')->nullable()->after('settlement_period_start');
            }
            if (!Schema::hasColumn('doctor_ledgers', 'vendor_id')) {
                $table->unsignedBigInteger('vendor_id')->nullable()->after('doctor_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doctor_ledgers', function (Blueprint $table) {
            $cols = ['payment_method', 'bank_name', 'transaction_no', 'settlement_type', 'settlement_period_start', 'settlement_period_end', 'vendor_id'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('doctor_ledgers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
