<?php

use App\Enums\FollowUpMode;
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
        Schema::create('lead_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('assigned_user')->nullable(); // who made the follow-up
            $table->dateTime('follow_up_date'); // when follow-up happened or scheduled
            $table->json('follow_up_mode')->nullable();
            $table->text('follow_up_notes')->nullable(); // notes on the follow-up
            $table->timestamps();
            $table->softDeletes();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            // Relationships
            $table->foreign('lead_id')->references('id')->on('leads')->onDelete('cascade');
            $table->foreign('assigned_user')->references('id')->on('users')->onDelete('set null');

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
        Schema::dropIfExists('lead_follow_ups');
    }
};
