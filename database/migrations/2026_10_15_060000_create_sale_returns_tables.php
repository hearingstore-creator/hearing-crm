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
        // 1. Create sale_returns table (Credit Notes for Patient Returns)
        if (!Schema::hasTable('sale_returns')) {
            Schema::create('sale_returns', function (Blueprint $table) {
                $table->id();
                $table->string('credit_note_no', 50)->unique();
                $table->unsignedBigInteger('sale_master_id')->nullable();
                $table->string('sale_invoice_no', 100)->nullable()->index();
                $table->unsignedBigInteger('customer_id');
                $table->unsignedBigInteger('vendor_id')->nullable(); // branch
                $table->date('date');
                $table->decimal('return_subtotal', 12, 2)->default(0.00);
                $table->decimal('deduction_amount', 12, 2)->default(0.00);
                $table->decimal('credit_note_amount', 12, 2)->default(0.00); // net credit note
                $table->string('settlement_type', 30)->default('due_reduction'); // due_reduction, store_credit, cash_refund
                $table->unsignedInteger('payment_type_id')->nullable(); // for cash_refund
                $table->unsignedBigInteger('doctor_id')->nullable();
                $table->decimal('doctor_commission_adjusted', 10, 2)->default(0.00);
                $table->text('reason')->nullable();
                $table->string('status', 30)->default('completed');

                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->foreign('sale_master_id')->references('id')->on('sale_masters')->onDelete('set null');
                $table->foreign('customer_id')->references('id')->on('customers')->onDelete('restrict');
                $table->foreign('vendor_id')->references('id')->on('users')->onDelete('set null');
                $table->foreign('doctor_id')->references('id')->on('doctors')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users');
                $table->foreign('updated_by')->references('id')->on('users');
                $table->foreign('deleted_by')->references('id')->on('users');
            });
        }

        // 2. Create sale_return_details table
        if (!Schema::hasTable('sale_return_details')) {
            Schema::create('sale_return_details', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sale_return_id');
                $table->unsignedBigInteger('sale_detail_id')->nullable();
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('batch_id')->nullable();
                $table->json('serial_no')->nullable(); // array of returned serial numbers
                $table->decimal('sold_qty', 10, 2)->default(1.00);
                $table->decimal('return_qty', 10, 2)->default(1.00);
                $table->decimal('unit_price', 10, 2)->default(0.00); // unit selling price
                $table->decimal('deduction_rate', 5, 2)->default(0.00); // % or fixed
                $table->decimal('deduction_amount', 10, 2)->default(0.00);
                $table->decimal('net_return_amount', 12, 2)->default(0.00);

                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->foreign('sale_return_id')->references('id')->on('sale_returns')->onDelete('cascade');
                $table->foreign('sale_detail_id')->references('id')->on('sale_details')->onDelete('set null');
                $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
                $table->foreign('batch_id')->references('id')->on('batches')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users');
                $table->foreign('updated_by')->references('id')->on('users');
                $table->foreign('deleted_by')->references('id')->on('users');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_return_details');
        Schema::dropIfExists('sale_returns');
    }
};
