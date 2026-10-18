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
        Schema::create('sms_filter_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('recipient_type'); // 'leads' or 'customers'
            $table->json('filter_criteria');   // full filter state JSON
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_filter_groups');
    }
};
