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
        Schema::table('sale_masters', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_masters', 'delivery_date_time')) {
                $table->dateTime('delivery_date_time')->nullable()->after('due_date');
            }
            if (!Schema::hasColumn('sale_masters', 'lab_name')) {
                $table->string('lab_name')->nullable()->after('delivery_date_time');
            }
            if (!Schema::hasColumn('sale_masters', 'delivered_at')) {
                $table->dateTime('delivered_at')->nullable()->after('lab_name');
            }
            if (!Schema::hasColumn('sale_masters', 'delivered_by')) {
                $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete()->after('delivered_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_masters', function (Blueprint $table) {
            if (Schema::hasColumn('sale_masters', 'delivered_by')) {
                $table->dropForeign(['delivered_by']);
                $table->dropColumn('delivered_by');
            }
            if (Schema::hasColumn('sale_masters', 'delivered_at')) {
                $table->dropColumn('delivered_at');
            }
            if (Schema::hasColumn('sale_masters', 'lab_name')) {
                $table->dropColumn('lab_name');
            }
            if (Schema::hasColumn('sale_masters', 'delivery_date_time')) {
                $table->dropColumn('delivery_date_time');
            }
        });
    }
};
