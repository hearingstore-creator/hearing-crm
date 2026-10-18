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
        // 1. Add supplier_memo_no to purchase_masters
        Schema::table('purchase_masters', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_masters', 'supplier_memo_no')) {
                $table->string('supplier_memo_no', 100)->nullable()->after('purchase_invoice_no')->index();
            }
        });

        // 2. Create purchase_returns table (Debit Notes to Suppliers)
        if (!Schema::hasTable('purchase_returns')) {
            Schema::create('purchase_returns', function (Blueprint $table) {
                $table->id();
                $table->string('return_no', 50)->unique();
                $table->unsignedBigInteger('purchase_master_id')->nullable();
                $table->unsignedBigInteger('supplier_id');
                $table->unsignedBigInteger('vendor_id')->nullable();
                $table->date('date');
                $table->decimal('total_amount', 12, 2)->default(0.00);
                $table->string('supplier_memo_no', 100)->nullable();
                $table->text('reason')->nullable();
                $table->string('status', 30)->default('completed');

                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->foreign('purchase_master_id')->references('id')->on('purchase_masters')->onDelete('set null');
                $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('restrict');
                $table->foreign('vendor_id')->references('id')->on('users')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users');
                $table->foreign('updated_by')->references('id')->on('users');
                $table->foreign('deleted_by')->references('id')->on('users');
            });
        }

        // 3. Create purchase_return_details table
        if (!Schema::hasTable('purchase_return_details')) {
            Schema::create('purchase_return_details', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('purchase_return_id');
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('batch_id')->nullable();
                $table->json('serial_no')->nullable();
                $table->decimal('qty', 10, 2)->default(1.00);
                $table->decimal('purchase_price', 10, 2)->default(0.00);
                $table->decimal('total_price', 12, 2)->default(0.00);

                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->foreign('purchase_return_id')->references('id')->on('purchase_returns')->onDelete('cascade');
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
        Schema::dropIfExists('purchase_return_details');
        Schema::dropIfExists('purchase_returns');

        Schema::table('purchase_masters', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_masters', 'supplier_memo_no')) {
                $table->dropIndex(['supplier_memo_no']);
                $table->dropColumn('supplier_memo_no');
            }
        });
    }
};
