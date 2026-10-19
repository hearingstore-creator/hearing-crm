<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profit_budgets', function (Blueprint $table) {
            $table->id();
            $table->char('month', 7);                 // 2026-10
            $table->string('branch_key', 12);         // all | hq | a branch's user id
            $table->string('metric', 30);             // net_sales | gross_profit | operating_expenses | net_profit
            $table->decimal('amount', 14, 2);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['month', 'branch_key', 'metric']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profit_budgets');
    }
};
