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
        // 1. Add financial allocation breakdown to sale_returns
        if (Schema::hasTable('sale_returns')) {
            Schema::table('sale_returns', function (Blueprint $table) {
                if (!Schema::hasColumn('sale_returns', 'due_amount_at_return')) {
                    $table->decimal('due_amount_at_return', 12, 2)->default(0.00)->after('credit_note_amount');
                }
                if (!Schema::hasColumn('sale_returns', 'due_reduced_amount')) {
                    $table->decimal('due_reduced_amount', 12, 2)->default(0.00)->after('due_amount_at_return');
                }
                if (!Schema::hasColumn('sale_returns', 'patient_refund_amount')) {
                    $table->decimal('patient_refund_amount', 12, 2)->default(0.00)->after('due_reduced_amount');
                }
            });
        }

        // 2. Add service return support to sale_return_details
        if (Schema::hasTable('sale_return_details')) {
            Schema::table('sale_return_details', function (Blueprint $table) {
                if (!Schema::hasColumn('sale_return_details', 'item_type')) {
                    $table->string('item_type', 20)->default('product')->after('sale_detail_id');
                }
                if (!Schema::hasColumn('sale_return_details', 'service_type_id')) {
                    $table->unsignedBigInteger('service_type_id')->nullable()->after('product_id');
                    $table->foreign('service_type_id')->references('id')->on('service_types')->onDelete('set null');
                }
                if (!Schema::hasColumn('sale_return_details', 'service_detail_id')) {
                    $table->unsignedBigInteger('service_detail_id')->nullable()->after('service_type_id');
                    $table->foreign('service_detail_id')->references('id')->on('service_details')->onDelete('set null');
                }
                // Allow product_id to be nullable for service returns
                $table->unsignedBigInteger('product_id')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sale_return_details')) {
            Schema::table('sale_return_details', function (Blueprint $table) {
                if (Schema::hasColumn('sale_return_details', 'service_detail_id')) {
                    $table->dropForeign(['service_detail_id']);
                    $table->dropColumn('service_detail_id');
                }
                if (Schema::hasColumn('sale_return_details', 'service_type_id')) {
                    $table->dropForeign(['service_type_id']);
                    $table->dropColumn('service_type_id');
                }
                if (Schema::hasColumn('sale_return_details', 'item_type')) {
                    $table->dropColumn('item_type');
                }
            });
        }

        if (Schema::hasTable('sale_returns')) {
            Schema::table('sale_returns', function (Blueprint $table) {
                if (Schema::hasColumn('sale_returns', 'patient_refund_amount')) {
                    $table->dropColumn('patient_refund_amount');
                }
                if (Schema::hasColumn('sale_returns', 'due_reduced_amount')) {
                    $table->dropColumn('due_reduced_amount');
                }
                if (Schema::hasColumn('sale_returns', 'due_amount_at_return')) {
                    $table->dropColumn('due_amount_at_return');
                }
            });
        }
    }
};
