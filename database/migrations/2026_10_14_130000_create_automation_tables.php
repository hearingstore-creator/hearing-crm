<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 – automations. New tables only.
 *  - notifications   : Laravel database notifications (the header bell)
 *  - automation_logs : one row per automation action already done, so a scheduled run never repeats it
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        Schema::create('automation_logs', function (Blueprint $table) {
            $table->id();
            $table->string('key', 150)->unique();   // e.g. "sla:activity:12", "appt-sms:5:2026-10-15"
            $table->string('automation', 30)->index();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_logs');
        Schema::dropIfExists('notifications');
    }
};
