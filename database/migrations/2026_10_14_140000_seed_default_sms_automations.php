<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data only (additive): SMS texts that were hard-coded in controllers become SMS Center automations,
 * active and with the same wording, so customers see no change until staff edit them.
 * The scheduled ones are added inactive – switch them on in SMS Center → Automation when ready.
 */
return new class extends Migration
{
    private function rows(): array
    {
        return [
            ['Customer Welcome', 'customer_created', true, 'Sent when a customer is registered',
                "Dear {{name}},\n\nWelcome to Our Hearing Care Family!\n\nThank you for choosing us. We are honored to be part of your journey to better hearing health.\n\nOur team of experts is committed to providing you with the best hearing solutions and personalized care.\n\nIf you have any questions or need assistance, please don't hesitate to reach out to us.\n\nBest regards,\nHearing Care Team"],
            ['Appointment Booked', 'appointment_booked', true, 'Sent when an appointment is booked',
                "Dear {{name}},\n\nYour appointment has been scheduled!\n\nService: {{service}}\nProfessional: {{professional}}\nDate: {{date}}\nTime: {{time}}\n\nBest regards,\nHearing Care Team"],
            ['Service Ticket Created', 'ticket_created', true, 'Sent when a support ticket is opened',
                "Dear {{name}},\n\nYour support ticket has been created successfully!\n\nTicket Details:\nTicket ID: #{{ticket_id}}\nSubject: {{subject}}\nPriority: {{priority}}\nStatus: {{status}}\n\nOur team will review your ticket and get back to you within 24-48 hours.\n\nIf you have any questions, feel free to contact us.\n\nBest regards,\nHearing Care Team"],
            ['Appointment Reminder', 'appointment_reminder', false, 'Daily 10:00 – appointments tomorrow',
                "Dear {{name}}, reminder: your {{service}} appointment is tomorrow ({{date}}). Call {{company_phone}} to reschedule. Hearing Care Team"],
            ['Warranty Expiring', 'warranty_expiring', false, 'Daily 10:30 – warranty ends in 30 days',
                "Dear {{name}}, the warranty of your {{product}} ends on {{warranty_expiry_date}}. Visit us for a free check-up before then. Call {{company_phone}}. Hearing Care Team"],
            ['Points Expiring', 'points_expiring', false, 'Daily 11:00 – points expire in 7 days',
                "Dear {{name}}, {{points}} reward points expire on {{expiry_date}}. Use them on your next visit! Call {{company_phone}}. Hearing Care Team"],
            ['Thank You – First Purchase', 'customer_first_sale', false, "Sent on the customer's first invoice",
                "Dear {{name}}, thank you for your first purchase with us! Your customer ID is {{customer_id}}. For any help call {{company_phone}}. Hearing Care Team"],
            ['Membership Upgraded', 'membership_upgrade', false, 'Sent when the membership tier goes up',
                "Congratulations {{name}}! You are now a {{tier}} member. Enjoy your new benefits. Call {{company_phone}} to learn more. Hearing Care Team"],
        ];
    }

    public function up(): void
    {
        foreach ($this->rows() as [$name, $trigger, $active, $description, $body]) {
            if (DB::table('sms_automations')->where('trigger_type', $trigger)->exists() || DB::table('sms_automations')->where('name', $name)->exists()) {
                continue;   // the user already has one for this trigger
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
    }
};
