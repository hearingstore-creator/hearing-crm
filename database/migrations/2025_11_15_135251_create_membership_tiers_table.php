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
        Schema::create('membership_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('min_purchase', 12, 2)->default(0);
            $table->decimal('max_purchase', 12, 2)->nullable();
            $table->decimal('earn_percentage', 5, 2);
            $table->unsignedInteger('referral_reward_points');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
