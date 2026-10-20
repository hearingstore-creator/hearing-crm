<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A commission for one product / model, which beats the category rule and the doctor's default
        Schema::create('doctor_product_commissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctor_id');
            $table->unsignedBigInteger('product_id');
            $table->enum('commission_type', ['percent', 'fixed'])->default('percent');
            $table->decimal('commission_value', 10, 2)->default(0.00);
            $table->timestamps();

            $table->foreign('doctor_id')->references('id')->on('doctors')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->unique(['doctor_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_product_commissions');
    }
};
