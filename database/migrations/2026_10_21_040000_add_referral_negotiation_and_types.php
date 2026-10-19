<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add referrer_type to doctors table ('doctor', 'quack', 'agent')
        Schema::table('doctors', function (Blueprint $table) {
            if (!Schema::hasColumn('doctors', 'referrer_type')) {
                $table->string('referrer_type', 30)->default('doctor')->after('name')->index();
            }
        });

        // 2. Add referral_settlement_status to sale_masters ('none', 'pending', 'settled')
        Schema::table('sale_masters', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_masters', 'referral_settlement_status')) {
                $table->string('referral_settlement_status', 20)->default('none')->after('reference_amount')->index();
            }
        });

        // 3. Backfill existing sales
        DB::table('sale_masters')
            ->whereNotNull('reference_by')
            ->where('reference_amount', '>', 0)
            ->update(['referral_settlement_status' => 'settled']);

        DB::table('sale_masters')
            ->whereNotNull('reference_by')
            ->where(function ($q) {
                $q->whereNull('reference_amount')->orWhere('reference_amount', '<=', 0);
            })
            ->update(['referral_settlement_status' => 'pending']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            if (Schema::hasColumn('doctors', 'referrer_type')) {
                $table->dropColumn('referrer_type');
            }
        });

        Schema::table('sale_masters', function (Blueprint $table) {
            if (Schema::hasColumn('sale_masters', 'referral_settlement_status')) {
                $table->dropColumn('referral_settlement_status');
            }
        });
    }
};
