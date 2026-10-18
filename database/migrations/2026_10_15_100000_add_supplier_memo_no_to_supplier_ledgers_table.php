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
        Schema::table('supplier_ledgers', function (Blueprint $table) {
            if (!Schema::hasColumn('supplier_ledgers', 'supplier_memo_no')) {
                $table->string('supplier_memo_no', 100)->nullable()->after('ref_invoice_no');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_ledgers', function (Blueprint $table) {
            if (Schema::hasColumn('supplier_ledgers', 'supplier_memo_no')) {
                $table->dropColumn('supplier_memo_no');
            }
        });
    }
};
