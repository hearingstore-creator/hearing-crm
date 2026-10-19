<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Small per-user choices: the dashboard tab someone opens on, their starred reports. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_preferences')) {
            return;
        }

        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('key', 60);
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
