<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dashboard Phase 2 — small, nullable data fields that unlock new widgets.
 *  - leads.date_of_birth            → Birthdays widget / birthday SMS
 *  - products.reorder_level         → per-product low-stock threshold (fallback 5)
 *  - products.service_interval_months → Service / cleaning due widget (fallback 6)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('leads', 'date_of_birth')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->date('date_of_birth')->nullable()->after('age');
            });
        }

        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'reorder_level')) {
                $table->unsignedInteger('reorder_level')->nullable()->after('usage_months');
            }
            if (!Schema::hasColumn('products', 'service_interval_months')) {
                $table->unsignedSmallInteger('service_interval_months')->nullable()->after('usage_months');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('leads', 'date_of_birth')) {
            Schema::table('leads', fn (Blueprint $table) => $table->dropColumn('date_of_birth'));
        }
        Schema::table('products', function (Blueprint $table) {
            foreach (['reorder_level', 'service_interval_months'] as $col) {
                if (Schema::hasColumn('products', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
