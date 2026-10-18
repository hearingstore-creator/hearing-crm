<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Public holidays (Eid, Victory Day…): skipped like weekly holidays for new-lead call deadlines. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name', 80);
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_holidays');
    }
};
