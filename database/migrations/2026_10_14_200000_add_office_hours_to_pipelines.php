<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Office hours + weekly holidays for new-lead call deadlines (set in Pipeline Settings). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pipelines', function (Blueprint $table) {
            $table->string('office_open', 5)->default('10:00')->after('default_owner_id');
            $table->string('office_close', 5)->default('21:00')->after('office_open');
            $table->json('weekly_holidays')->nullable()->after('office_close'); // ISO days: 1=Mon … 7=Sun
        });

        \Illuminate\Support\Facades\DB::table('pipelines')->update(['weekly_holidays' => json_encode([5])]); // Friday
    }

    public function down(): void
    {
        Schema::table('pipelines', function (Blueprint $table) {
            $table->dropColumn(['office_open', 'office_close', 'weekly_holidays']);
        });
    }
};
