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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            // Polymorphic links: Who caused the action? (Usually a User)
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            // This creates: subject_id (BIGINT) and subject_type (VARCHAR)
            $table->morphs('subject');

            $table->string('action', 100)->nullable();
            $table->json('old_values')->nullable(); 
            $table->json('new_values')->nullable(); 
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable(); // Browser/device info
            
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
