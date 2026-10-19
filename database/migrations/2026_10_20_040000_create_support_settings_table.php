<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Support rules that were fixed in code: hours allowed per ticket priority, days of silence before a reminder,
 * days after "Solved" before a ticket closes by itself. Also adds the "Service Ticket Solved" SMS text (inactive until switched on).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('support_settings')) {
            Schema::create('support_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('sla_critical_hours')->default(4);
                $table->unsignedInteger('sla_high_hours')->default(24);
                $table->unsignedInteger('sla_medium_hours')->default(72);
                $table->unsignedInteger('sla_low_hours')->default(168);
                $table->unsignedInteger('stale_days')->default(2);
                $table->unsignedInteger('auto_close_days')->default(3);
                $table->timestamps();
            });
            DB::table('support_settings')->insert(['created_at' => now(), 'updated_at' => now()]);
        }

        if (!DB::table('sms_automations')->where('trigger_type', 'ticket_solved')->exists()) {
            DB::table('sms_automations')->insert([
                'name' => 'Service Ticket Solved', 'trigger_type' => 'ticket_solved',
                'description' => 'Sent when a support ticket is marked Solved (the ticket closes by itself after a few days)',
                'message_body' => "Dear {{name}}, your support ticket #{{ticket_id}} ({{subject}}) has been marked solved. If the problem is not fixed, call {{company_phone}} within {{days}} days and we will reopen it. Hearing Care Team",
                'is_active' => false, 'sent_count' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('support_settings');
        DB::table('sms_automations')->where('trigger_type', 'ticket_solved')->where('sent_count', 0)->delete();
    }
};
