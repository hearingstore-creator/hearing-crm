<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (!Schema::hasColumn('settings', 'return_window_days')) {
                $table->unsignedSmallInteger('return_window_days')->default(7);
            }
            if (!Schema::hasColumn('settings', 'return_charge_percent')) {
                $table->decimal('return_charge_percent', 5, 2)->default(10);
            }
        });

        Schema::table('sale_returns', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_returns', 'policy_exception')) {
                $table->string('policy_exception', 60)->nullable();
            }
            if (!Schema::hasColumn('sale_returns', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['return_window_days', 'return_charge_percent']);
        });
        Schema::table('sale_returns', function (Blueprint $table) {
            $table->dropColumn(['policy_exception', 'approved_by']);
        });
    }
};
