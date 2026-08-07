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
        Schema::create('ledgers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('income_expense_head_id')->nullable();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->string('invoice_no')->unique();
            $table->decimal('expense_amount', 10, 2)->nullable();
            $table->decimal('income_amount', 10, 2)->nullable();
            $table->string('amount_type')->nullable();
            $table->date('date');
            $table->text('details')->nullable();
            $table->string('type')->nullable();
            $table->string('ref_invoice_no')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->foreign('income_expense_head_id')->references('id')->on('income_expense_heads')->onDelete('restrict')->onUpdate('cascade');
            $table->foreign('vendor_id')->references('id')->on('users')->onDelete('restrict')->onUpdate('cascade');
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
        Schema::dropIfExists('ledgers');
    }
};
