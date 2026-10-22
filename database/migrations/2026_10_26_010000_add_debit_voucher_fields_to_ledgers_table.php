<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Debit Voucher fields on manual expenses. All columns are nullable, so existing
 * ledger rows (and every other flow that posts to the ledger) are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledgers', function (Blueprint $table) {
            if (!Schema::hasColumn('ledgers', 'voucher_no')) {
                $table->string('voucher_no', 40)->nullable()->unique();
            }
            if (!Schema::hasColumn('ledgers', 'payee_name')) {
                $table->string('payee_name', 150)->nullable();
            }
            if (!Schema::hasColumn('ledgers', 'payment_method')) {
                $table->string('payment_method', 50)->nullable();
            }
            if (!Schema::hasColumn('ledgers', 'bank_name')) {
                $table->string('bank_name', 100)->nullable();
            }
            if (!Schema::hasColumn('ledgers', 'transaction_no')) {
                $table->string('transaction_no', 100)->nullable();
            }
            if (!Schema::hasColumn('ledgers', 'attachment_path')) {
                $table->string('attachment_path', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('ledgers', function (Blueprint $table) {
            if (Schema::hasColumn('ledgers', 'voucher_no')) {
                $table->dropUnique(['voucher_no']);
            }
            foreach (['voucher_no', 'payee_name', 'payment_method', 'bank_name', 'transaction_no', 'attachment_path'] as $col) {
                if (Schema::hasColumn('ledgers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
