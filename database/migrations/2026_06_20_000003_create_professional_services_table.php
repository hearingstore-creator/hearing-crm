<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professional_services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('appointment_service_id');

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('appointment_service_id')->references('id')->on('appointment_services')->cascadeOnDelete();

            $table->unique(['user_id', 'appointment_service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_services');
    }
};
