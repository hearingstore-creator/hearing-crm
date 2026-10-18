<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lead_follow_ups', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('follow_up_notes');
            $table->timestamp('completed_at')->nullable()->after('status');
            $table->text('outcome_notes')->nullable()->after('completed_at');
        });

        // Set existing follow-ups with past dates as 'completed'
        DB::table('lead_follow_ups')
            ->whereNull('deleted_at')
            ->where('follow_up_date', '<', now()->subDays(1))
            ->update(['status' => 'completed', 'completed_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_follow_ups', function (Blueprint $table) {
            $table->dropColumn(['status', 'completed_at', 'outcome_notes']);
        });
    }
};
