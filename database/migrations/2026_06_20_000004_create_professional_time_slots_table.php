<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professional_time_slots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('time_slot_id');
            $table->unsignedBigInteger('day_id');

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('time_slot_id')->references('id')->on('time_slots')->cascadeOnDelete();
            $table->foreign('day_id')->references('id')->on('days')->cascadeOnDelete();

            $table->unique(['user_id', 'time_slot_id', 'day_id']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_time_slots');
    }
};
