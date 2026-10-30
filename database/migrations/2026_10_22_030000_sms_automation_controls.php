<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SMS cost control: a mode switch for all automations, per-automation usage and cost, and the
 * repair / order pickup messages as proper SMS Center automations (they used to be hard-coded
 * in the repair screen and never reached the Send Log).
 */
return new class extends Migration
{
    private function rows(): array
    {
        return [
            ['Repair Received', 'repair_received', true, 'Sent when a device is received for repair',
                "Dear {{name}}, your hearing aid{{serial_text}} is received at {{branch}}. Job Card: #{{job_no}}. Est. completion: {{promised_date}}. Thank you."],
            ['Repair Ready for Pickup', 'repair_ready', true, 'Sent when the repaired device is ready',
                "Dear {{name}}, your device is ready for pickup at {{branch}}. Job: #{{job_no}}. Due: Tk {{due}}. Please bring your Job Card. Ph: {{branch_phone}}"],
            ['Order Ready for Pickup', 'order_ready', true, 'Sent when a device or report is ready in the Delivery Hub',
                "Dear {{name}}, your {{item}} (Inv #{{invoice}}) is ready at {{branch}}. Due: Tk {{due}}. Call {{company_phone}} to arrange your visit."],
        ];
    }

    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (!Schema::hasColumn('settings', 'sms_automation_mode')) {
                $table->string('sms_automation_mode', 20)->default('all');   // all | essential | off
            }
            if (!Schema::hasColumn('settings', 'sms_cost_per_sms')) {
                $table->decimal('sms_cost_per_sms', 8, 3)->default(0.35);
            }
        });

        Schema::table('sms_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('sms_logs', 'automation_trigger')) {
                $table->string('automation_trigger', 60)->nullable()->index();
            }
            if (!Schema::hasColumn('sms_logs', 'sms_parts')) {
                $table->unsignedTinyInteger('sms_parts')->default(1);
            }
        });

        foreach ($this->rows() as [$name, $trigger, $active, $description, $body]) {
            if (DB::table('sms_automations')->where('trigger_type', $trigger)->exists() || DB::table('sms_automations')->where('name', $name)->exists()) {
                continue;
            }
            DB::table('sms_automations')->insert([
                'name' => $name, 'trigger_type' => $trigger, 'description' => $description, 'message_body' => $body,
                'is_active' => $active, 'sent_count' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('sms_automations')->whereIn('name', array_column($this->rows(), 0))->where('sent_count', 0)->delete();
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->dropColumn(['automation_trigger', 'sms_parts']);
        });
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['sms_automation_mode', 'sms_cost_per_sms']);
        });
    }
};
