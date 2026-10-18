<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Structured call result (no_answer, busy, call_back, interested, not_interested, wrong_number). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_follow_ups', function (Blueprint $table) {
            $table->string('outcome', 20)->nullable()->after('outcome_notes');
            $table->index(['deal_id', 'outcome']);
        });
    }

    public function down(): void
    {
        Schema::table('lead_follow_ups', function (Blueprint $table) {
            $table->dropIndex(['deal_id', 'outcome']);
            $table->dropColumn('outcome');
        });
    }
};
