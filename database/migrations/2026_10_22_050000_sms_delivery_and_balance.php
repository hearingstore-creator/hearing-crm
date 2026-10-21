<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SMS delivery reports (the gateway's own message id is kept so its status can be asked later)
 * and the gateway balance with a low-balance warning level.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('sms_logs', 'csms_id')) {
                $table->string('csms_id', 80)->nullable()->index();
            }
            if (!Schema::hasColumn('sms_logs', 'gateway_ref')) {
                $table->string('gateway_ref', 80)->nullable();
            }
            if (!Schema::hasColumn('sms_logs', 'delivery_status')) {
                $table->string('delivery_status', 20)->nullable()->index();   // delivered | failed | pending | unknown
            }
            if (!Schema::hasColumn('sms_logs', 'delivery_detail')) {
                $table->string('delivery_detail', 255)->nullable();
            }
            if (!Schema::hasColumn('sms_logs', 'delivery_checked_at')) {
                $table->timestamp('delivery_checked_at')->nullable();
            }
        });

        Schema::table('settings', function (Blueprint $table) {
            if (!Schema::hasColumn('settings', 'sms_low_balance')) {
                $table->decimal('sms_low_balance', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('settings', 'sms_balance_last')) {
                $table->decimal('sms_balance_last', 14, 2)->nullable();
            }
            if (!Schema::hasColumn('settings', 'sms_balance_checked_at')) {
                $table->timestamp('sms_balance_checked_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('sms_logs', fn (Blueprint $t) => $t->dropColumn(['csms_id', 'gateway_ref', 'delivery_status', 'delivery_detail', 'delivery_checked_at']));
        Schema::table('settings', fn (Blueprint $t) => $t->dropColumn(['sms_low_balance', 'sms_balance_last', 'sms_balance_checked_at']));
    }
};
