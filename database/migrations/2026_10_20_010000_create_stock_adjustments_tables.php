<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock adjustments (damaged, lost, found, count correction, opening stock).
 *
 * Additive only. batches.adjust_qty starts at 0, so every stock formula gives exactly the old answer
 * until an adjustment is posted.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('batches', 'adjust_qty')) {
            Schema::table('batches', function (Blueprint $table) {
                $table->decimal('adjust_qty', 10, 2)->default(0)->after('sale_return_qty');
            });
        }

        if (!Schema::hasTable('stock_adjustments')) {
            Schema::create('stock_adjustments', function (Blueprint $table) {
                $table->id();
                $table->string('adjustment_no', 40)->unique();
                $table->unsignedBigInteger('vendor_id')->nullable()->index(); // location adjusted; NULL = head office
                $table->date('date');
                $table->string('reason', 30);
                $table->text('note')->nullable();
                $table->string('status', 20)->default('completed'); // completed | cancelled
                $table->timestamp('cancelled_at')->nullable();
                $table->text('cancel_reason')->nullable();
                $table->unsignedBigInteger('cancelled_by')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('stock_adjustment_details')) {
            Schema::create('stock_adjustment_details', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stock_adjustment_id')->constrained('stock_adjustments')->cascadeOnDelete();
                $table->unsignedBigInteger('product_id')->index();
                $table->string('direction', 3); // in | out
                $table->decimal('qty', 10, 2);
                $table->json('serial_no')->nullable();
                $table->unsignedBigInteger('batch_id')->nullable()->index(); // 'out': batch taken from; 'in': batch created
                $table->decimal('unit_cost', 12, 2)->nullable();
                $table->decimal('mrp_price', 12, 2)->nullable();
                $table->date('warranty_start_date')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_details');
        Schema::dropIfExists('stock_adjustments');
        if (Schema::hasColumn('batches', 'adjust_qty')) {
            Schema::table('batches', fn (Blueprint $table) => $table->dropColumn('adjust_qty'));
        }
    }
};
