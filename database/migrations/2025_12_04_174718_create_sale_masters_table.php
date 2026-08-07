<?php

use App\Enums\DeliveryStatus;
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
        Schema::create('sale_masters', function (Blueprint $table) {
           $table->id();
            $table->string('sale_invoice_no')->unique();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->date('date');
            $table->text('details')->nullable();
            $table->enum('delivery_status', array_column(DeliveryStatus::cases(), 'value'))
            ->default(DeliveryStatus::DELIVERED->value);
            $table->unsignedBigInteger('reference_by')->nullable();
            $table->boolean('is_percent_service_commission')->default(true);
            $table->boolean('is_percent_product_commission')->default(true);
            $table->integer('payment_id');
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->decimal('vat', 10, 2)->nullable();
            $table->decimal('reference_amount', 10, 2)->default(0.00);
            $table->decimal('total_discount', 10, 2)->nullable();
            $table->decimal('paid', 10, 2)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('restrict')->onUpdate('cascade');
            $table->foreign('vendor_id')->references('id')->on('users')->onDelete('restrict')->onUpdate('cascade');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users');
            $table->foreign('reference_by')->references('id')->on('doctors');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_masters');
    }
};
