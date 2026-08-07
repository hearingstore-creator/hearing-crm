<?php

namespace Database\Seeders;

use App\Enums\SmsTemplateCategory;
use App\Models\SmsTemplate;
use Illuminate\Database\Seeder;

class SmsTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Warranty Expiry Reminder',
                'category' => SmsTemplateCategory::WARRANTY->value,
                'body' => 'Dear {{name}}, your hearing device warranty will expire on {{warranty_expiry_date}}. Visit us for a free check-up and renewal options. Call {{company_phone}} for appointment.',
                'variables' => ['name', 'company_phone', 'warranty_expiry_date'],
            ],
            [
                'name' => 'New Lead Welcome',
                'category' => SmsTemplateCategory::LEAD_ONBOARDING->value,
                'body' => 'Welcome to Hearing Centre, {{name}}! Thank you for reaching out. Our team will contact you shortly to discuss your hearing needs. Call {{company_phone}} for any queries.',
                'variables' => ['name', 'company_phone'],
            ],
            [
                'name' => 'Post-Purchase Thank You',
                'category' => SmsTemplateCategory::CUSTOMER_ONBOARDING->value,
                'body' => 'Dear {{name}}, thank you for choosing Hearing Centre! We hope you enjoy your new device. Reach us anytime at {{company_phone}}.',
                'variables' => ['name', 'company_phone'],
            ],
            [
                'name' => 'Appointment Reminder',
                'category' => SmsTemplateCategory::APPOINTMENT->value,
                'body' => 'Dear {{name}}, this is a reminder for your appointment on {{appointment_date}} at Hearing Centre. Please arrive 10 minutes early. Reschedule: {{company_phone}}.',
                'variables' => ['name', 'company_phone', 'appointment_date'],
            ],
            [
                'name' => 'Tier Upgrade Congratulations',
                'category' => SmsTemplateCategory::MEMBERSHIP->value,
                'body' => 'Congratulations {{name}}! You have been upgraded to {{tier}} tier! Enjoy enhanced benefits including {{points}} bonus points. Call {{company_phone}} for more info.',
                'variables' => ['name', 'tier', 'points', 'company_phone'],
            ],
        ];

        foreach ($templates as $tpl) {
            SmsTemplate::firstOrCreate(
                ['name' => $tpl['name']],
                [
                    'category' => $tpl['category'],
                    'body' => $tpl['body'],
                    'variables' => $tpl['variables'],
                    'is_active' => true,
                ]
            );
        }
    }
}
