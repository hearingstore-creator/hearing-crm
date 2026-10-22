<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Follow-up sequences: a series of SMS, emails, WhatsApp template messages and call tasks spread over days,
 * started by an event (new lead, stage reached, deal lost) or by hand, and stopped when the patient replies,
 * books, moves on, buys or is lost. Three examples are added switched off.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sequences')) {
            Schema::create('sequences', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(false);
                $table->string('trigger', 30)->default('manual');   // manual | deal_created | stage_entered | deal_lost
                $table->json('trigger_sources')->nullable();          // deal_created: SourceOfEntry values (empty = any)
                $table->unsignedBigInteger('trigger_stage_id')->nullable();   // stage_entered
                $table->json('trigger_lost_reasons')->nullable();     // deal_lost: lost reason ids (empty = any)
                $table->boolean('stop_on_reply')->default(true);
                $table->boolean('stop_on_appointment')->default(true);
                $table->boolean('stop_on_stage_change')->default(false);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('sequence_steps')) {
            Schema::create('sequence_steps', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sequence_id')->constrained('sequences')->cascadeOnDelete();
                $table->unsignedSmallInteger('position')->default(1);
                $table->unsignedInteger('delay_minutes')->default(0);   // after the previous step (the first: after the start)
                $table->string('channel', 20);                          // sms | email | whatsapp | call
                $table->string('subject', 200)->nullable();             // email
                $table->text('body')->nullable();                       // sms / email text, call task note
                $table->string('wa_template', 100)->nullable();         // WhatsApp approved template name
                $table->string('wa_language', 10)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('sequence_enrollments')) {
            Schema::create('sequence_enrollments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sequence_id')->constrained('sequences')->cascadeOnDelete();
                $table->unsignedBigInteger('deal_id')->index();
                $table->unsignedBigInteger('lead_id')->index();
                $table->string('status', 15)->default('active')->index();   // active | completed | stopped
                $table->unsignedSmallInteger('next_position')->default(1);
                $table->timestamp('next_run_at')->nullable()->index();
                $table->unsignedBigInteger('start_stage_id')->nullable();
                $table->string('stop_reason', 120)->nullable();
                $table->unsignedBigInteger('started_by')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('sequence_runs')) {
            Schema::create('sequence_runs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('enrollment_id')->constrained('sequence_enrollments')->cascadeOnDelete();
                $table->unsignedBigInteger('step_id')->nullable();
                $table->string('channel', 20);
                $table->string('status', 15);   // sent | skipped | failed
                $table->string('detail', 500)->nullable();
                $table->timestamps();
            });
        }

        $this->examples();
    }

    /** Three ready-made sequences, switched off, to edit and switch on. */
    private function examples(): void
    {
        if (DB::table('sequences')->exists()) {
            return;
        }
        $now = now();
        $trialStage = DB::table('pipeline_stages')->where('status_key', 'trial')->value('id');
        $notReady = DB::table('lost_reasons')->where('name', 'like', '%not ready%')->value('id');

        $make = function (array $seq, array $steps) use ($now) {
            $id = DB::table('sequences')->insertGetId($seq + ['is_active' => false, 'created_at' => $now, 'updated_at' => $now]);
            foreach ($steps as $i => $s) {
                DB::table('sequence_steps')->insert($s + ['sequence_id' => $id, 'position' => $i + 1, 'created_at' => $now, 'updated_at' => $now]);
            }
        };

        $make(['name' => 'New ad lead – first week', 'description' => 'Facebook, Instagram, Google and WhatsApp leads that have not booked yet.',
            'trigger' => 'deal_created', 'trigger_sources' => json_encode(['facebook', 'instagram', 'google', 'whatsapp', 'website']),
            'stop_on_reply' => true, 'stop_on_appointment' => true, 'stop_on_stage_change' => true], [
            ['delay_minutes' => 0, 'channel' => 'sms', 'body' => 'Dear {{name}}, thank you for contacting {{company}}. Our hearing specialist will call you shortly. Free hearing test: call {{company_phone}}.'],
            ['delay_minutes' => 60 * 24, 'channel' => 'call', 'body' => 'Second call – offer a free hearing test this week'],
            ['delay_minutes' => 60 * 24 * 2, 'channel' => 'sms', 'body' => 'Dear {{name}}, a hearing test takes 30 minutes and is free this month. Reply or call {{company_phone}} to book a time that suits you.'],
            ['delay_minutes' => 60 * 24 * 4, 'channel' => 'call', 'body' => 'Last try – ask if they still want help with their hearing'],
        ]);

        if ($trialStage) {
            $make(['name' => 'After trial – no decision yet', 'description' => 'Patients who tried a device; stops when the deal moves on, is won or lost.',
                'trigger' => 'stage_entered', 'trigger_stage_id' => $trialStage,
                'stop_on_reply' => false, 'stop_on_appointment' => false, 'stop_on_stage_change' => true], [
                ['delay_minutes' => 60 * 24, 'channel' => 'call', 'body' => 'How did the trial feel? Answer questions, share the price options'],
                ['delay_minutes' => 60 * 24 * 2, 'channel' => 'sms', 'body' => 'Dear {{name}}, thank you for trying the hearing aid with us. If you have any questions about the model or payment options, call {{company_phone}}.'],
                ['delay_minutes' => 60 * 24 * 4, 'channel' => 'call', 'body' => 'Decision call – offer a second fitting visit with the family'],
            ]);
        }

        $make(['name' => 'Lost: not ready – check in later', 'description' => 'Gentle check-ins after a patient said they were not ready.',
            'trigger' => 'deal_lost', 'trigger_lost_reasons' => $notReady ? json_encode([$notReady]) : null,
            'stop_on_reply' => true, 'stop_on_appointment' => true, 'stop_on_stage_change' => false], [
            ['delay_minutes' => 60 * 24 * 30, 'channel' => 'sms', 'body' => 'Dear {{name}}, we hope you are well. Whenever you are ready, a free hearing check is waiting for you at {{company}}. Call {{company_phone}}.'],
            ['delay_minutes' => 60 * 24 * 30, 'channel' => 'call', 'body' => 'Check-in call: is now a better time?'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sequence_runs');
        Schema::dropIfExists('sequence_enrollments');
        Schema::dropIfExists('sequence_steps');
        Schema::dropIfExists('sequences');
    }
};
