<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keeping patients: upgrade reminders when a hearing aid gets old, a yearly service reminder,
 * a short satisfaction survey (NPS) after delivery, and sales sent back to Meta / Google so the ads
 * learn who buys. The three SMS automations are added switched off.
 */
return new class extends Migration
{
    private function automations(): array
    {
        return [
            ['Hearing Aid Upgrade', 'upgrade_due', 'Daily 10:15 – the hearing aid is old enough to upgrade',
                "Dear {{name}}, your {{model}} is now {{years}} years old. New hearing aids are smaller and clearer – come for a free check and see the options. Call {{company_phone}}."],
            ['Yearly Service Reminder', 'service_due', 'Daily 10:15 – each year on the purchase date',
                "Dear {{name}}, it is a year since your last hearing aid check. A free clean and tune-up keeps it working well. Call {{company_phone}} to book."],
            ['Satisfaction Survey', 'nps_survey', 'Daily 10:15 – some days after delivery',
                "Dear {{name}}, how happy are you with {{company}}? Please answer one quick question: {{link}}"],
        ];
    }

    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (!Schema::hasColumn('settings', 'upgrade_after_months')) {
                $table->unsignedSmallInteger('upgrade_after_months')->default(36);
                $table->boolean('upgrade_opens_deal')->default(false);
                $table->unsignedSmallInteger('nps_after_days')->default(7);
            }
        });

        if (!Schema::hasTable('nps_responses')) {
            Schema::create('nps_responses', function (Blueprint $table) {
                $table->id();
                $table->string('token', 64)->unique();
                $table->unsignedBigInteger('sale_master_id')->nullable()->index();
                $table->unsignedBigInteger('customer_id')->index();
                $table->unsignedBigInteger('vendor_id')->nullable();
                $table->unsignedTinyInteger('score')->nullable();
                $table->text('comment')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('answered_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('conversion_events')) {
            Schema::create('conversion_events', function (Blueprint $table) {
                $table->id();
                $table->string('platform', 10);            // meta | google
                $table->unsignedBigInteger('sale_master_id');
                $table->string('status', 10);              // sent | failed
                $table->string('detail', 500)->nullable();
                $table->timestamps();
                $table->unique(['platform', 'sale_master_id']);
            });
        }

        foreach ($this->automations() as [$name, $trigger, $description, $body]) {
            if (!DB::table('sms_automations')->where('trigger_type', $trigger)->exists() && !DB::table('sms_automations')->where('name', $name)->exists()) {
                DB::table('sms_automations')->insert(['name' => $name, 'trigger_type' => $trigger, 'description' => $description, 'message_body' => $body,
                    'is_active' => false, 'sent_count' => 0, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        DB::table('sms_automations')->whereIn('name', array_column($this->automations(), 0))->where('sent_count', 0)->delete();
        Schema::dropIfExists('conversion_events');
        Schema::dropIfExists('nps_responses');
        Schema::table('settings', fn (Blueprint $t) => $t->dropColumn(['upgrade_after_months', 'upgrade_opens_deal', 'nps_after_days']));
    }
};
