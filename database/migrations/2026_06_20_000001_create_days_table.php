<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('days', function (Blueprint $table) {
            $table->id();
            $table->string('day', 20)->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('days');
    }
};
