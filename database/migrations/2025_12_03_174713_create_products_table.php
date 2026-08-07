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
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // Foreign keys to categories and types
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('type_id')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable();

            // Model (Intuis and others)
            $table->string('model')->nullable();
            $table->string('battery_size')->nullable();
            $table->integer('usage_months')->nullable();

            // Warranty (Inventory): 27 months (Alert after 25 months)
            $table->integer('warranty_inventory_months');
            $table->date('warranty_inventory_alert_date')->nullable();

            // Warranty (Sales): 24 or 12 months
            $table->integer('warranty_sales_months')->nullable();
            $table->date('warranty_sales_expiry_date')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            // Foreign keys
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
            $table->foreign('type_id')->references('id')->on('types')->cascadeOnDelete();
            $table->foreign('brand_id')->references('id')->on('brands')->cascadeOnDelete();

            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
