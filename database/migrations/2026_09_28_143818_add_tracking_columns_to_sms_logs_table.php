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
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->boolean('follow_up_created')->default(false)->after('sent_at');
            $table->boolean('read_by_agent')->default(false)->after('follow_up_created');
            $table->string('campaign_name')->nullable()->after('read_by_agent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->dropColumn(['follow_up_created', 'read_by_agent', 'campaign_name']);
        });
    }
};
