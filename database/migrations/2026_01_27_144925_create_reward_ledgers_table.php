<?php

use App\Enums\RewardLedgerType;
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
        Schema::create('reward_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            $table->foreignId('sale_master_id')
                ->nullable()
                ->constrained('sale_masters')
                ->nullOnDelete();

            $table->foreignId('referral_id')
                ->nullable()
                ->constrained('referrals')
                ->nullOnDelete();

            $table->enum('type', array_column(RewardLedgerType::cases(), 'value'));

            $table->bigInteger('points'); // positive or negative
            $table->string('batch_reference')->nullable(); //invoice_id
            $table->date('expiry_date')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users');

            $table->index(['customer_id', 'type']);
            $table->index('expiry_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reward_ledgers');
    }
};
