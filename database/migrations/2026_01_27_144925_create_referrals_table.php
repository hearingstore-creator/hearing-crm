<?php

use App\Enums\ReferralStatus;
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
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('referrer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            $table->foreignId('referred_customer_id')
                ->unique()
                ->constrained('customers')
                ->cascadeOnDelete();

            $table->string('referral_code_used');

            $table->enum('status', array_column(ReferralStatus::cases(), 'value'))
                ->default(ReferralStatus::PENDING->value);

            $table->timestamp('rewarded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
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
        Schema::dropIfExists('referrals');
    }
};
