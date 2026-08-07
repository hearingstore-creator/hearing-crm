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
        Schema::create('service_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_master_id');
            $table->unsignedBigInteger('service_type_id');
            $table->decimal('qty', 10, 2)->nullable();
            $table->decimal('service_price', 10, 2)->nullable();
            $table->decimal('discount', 10, 2)->nullable()->comment('in percentage');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->foreign('sale_master_id')->references('id')->on('sale_masters');
            $table->foreign('service_type_id')->references('id')->on('service_types')->onDelete('restrict')->onUpdate('cascade');
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
        Schema::dropIfExists('service_details');
    }
};
