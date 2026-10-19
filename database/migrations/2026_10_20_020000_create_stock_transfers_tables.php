<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock transfers between head office and branches.
 *
 * Additive only. batches.origin_batch_id points a transfer-created batch back to the batch that was
 * originally purchased, so supplier-warranty dates keep following the original purchase.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('batches', 'origin_batch_id')) {
            Schema::table('batches', function (Blueprint $table) {
                $table->unsignedBigInteger('origin_batch_id')->nullable()->index()->after('adjust_qty');
            });
        }

        if (!Schema::hasTable('stock_transfers')) {
            Schema::create('stock_transfers', function (Blueprint $table) {
                $table->id();
                $table->string('transfer_no', 40)->unique();
                $table->unsignedBigInteger('from_vendor_id')->nullable()->index(); // NULL = head office
                $table->unsignedBigInteger('to_vendor_id')->nullable()->index();
                $table->date('date');
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

        if (!Schema::hasTable('stock_transfer_details')) {
            Schema::create('stock_transfer_details', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
                $table->unsignedBigInteger('product_id')->index();
                $table->decimal('qty', 10, 2);
                $table->json('serial_no')->nullable();
                $table->unsignedBigInteger('batch_id')->nullable();      // source batch
                $table->unsignedBigInteger('dest_batch_id')->nullable(); // batch created or topped up at the destination
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_details');
        Schema::dropIfExists('stock_transfers');
        if (Schema::hasColumn('batches', 'origin_batch_id')) {
            Schema::table('batches', fn (Blueprint $table) => $table->dropColumn('origin_batch_id'));
        }
    }
};
