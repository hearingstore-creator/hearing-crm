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
        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('qualification')->nullable();
            
            $table->decimal('product_commission_percent', 8, 2)->default(0.00)->comment('Commission percentage for products');
            $table->decimal('product_commission_amount', 10, 2)->default(0.00)->comment('Commission amount for products');
            $table->decimal('service_commission_percent', 8, 2)->default(0.00)->comment('Commission percentage for services');
            $table->decimal('service_commission_amount', 10, 2)->default(0.00)->comment('Commission amount for services');

            $table->boolean('status')->default(true);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users');
            $table->foreign('vendor_id')->references('id')->on('users')->onDelete('restrict')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctors');
    }
};
