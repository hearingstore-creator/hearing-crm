<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Scheduled campaigns, "retried" marker on the Send Log, and five more automations
 * (payment received, due reminder, referral earned / paid, birthday). The new automations
 * are added switched OFF: turn on only the ones worth the SMS cost.
 */
return new class extends Migration
{
    private function rows(): array
    {
        return [
            ['Payment Received', 'payment_received', 'Sent when a payment is recorded for a patient',
                "Dear {{name}}, we received Tk {{amount}} on {{date}} (ref {{invoice}}). Thank you. Call {{company_phone}} for any help."],
            ['Payment Due Reminder', 'due_reminder', 'Daily 10:00 – invoices due tomorrow, and overdue ones once a week',
                "Dear {{name}}, Tk {{due}} is due on invoice #{{invoice}}{{due_text}}. Please visit or call {{company_phone}}. Thank you."],
            ['Referral Commission Earned', 'referral_earned', 'Sent to the doctor / referrer when a sale credits their commission',
                "Dear {{name}}, Tk {{amount}} commission was added to your account for invoice #{{invoice}}. Thank you for your trust."],
            ['Referral Commission Paid', 'referral_paid', 'Sent to the doctor / referrer when a commission payment is made',
                "Dear {{name}}, Tk {{amount}} commission was paid to you on {{date}}. Voucher {{voucher}}. Thank you."],
            ['Birthday Greeting', 'birthday', "Daily 09:30 – patient's birthday",
                "Dear {{name}}, happy birthday from all of us! Wishing you good health and happiness. Call {{company_phone}} anytime."],
        ];
    }

    public function up(): void
    {
        Schema::table('sms_campaigns', function (Blueprint $table) {
            if (!Schema::hasColumn('sms_campaigns', 'scheduled_at')) {
                $table->timestamp('scheduled_at')->nullable();
            }
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE sms_campaigns MODIFY status ENUM('sending','completed','failed','scheduled') NOT NULL DEFAULT 'sending'");
        }

        Schema::table('sms_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('sms_logs', 'retried_at')) {
                $table->timestamp('retried_at')->nullable();
            }
        });

        foreach ($this->rows() as [$name, $trigger, $description, $body]) {
            if (DB::table('sms_automations')->where('trigger_type', $trigger)->exists() || DB::table('sms_automations')->where('name', $name)->exists()) {
                continue;
            }
            DB::table('sms_automations')->insert([
                'name' => $name, 'trigger_type' => $trigger, 'description' => $description, 'message_body' => $body,
                'is_active' => false, 'sent_count' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('sms_automations')->whereIn('name', array_column($this->rows(), 0))->where('sent_count', 0)->delete();
        Schema::table('sms_logs', fn (Blueprint $t) => $t->dropColumn('retried_at'));
        DB::table('sms_campaigns')->where('status', 'scheduled')->delete();
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE sms_campaigns MODIFY status ENUM('sending','completed','failed') NOT NULL DEFAULT 'sending'");
        }
        Schema::table('sms_campaigns', fn (Blueprint $t) => $t->dropColumn('scheduled_at'));
    }
};
