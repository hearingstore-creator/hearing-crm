<?php

namespace Tests\Feature;

use App\Classes\SMS;
use App\Models\Deal;
use App\Models\LeadCaptureForm;
use App\Models\Pipeline;
use App\Models\Sequence;
use App\Models\SequenceEnrollment;
use App\Models\Setting;
use App\Services\DealService;
use App\Services\SequenceService;
use App\Services\WebLeadIntake;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Marketing phase 4: follow-up sequences (SMS, email, WhatsApp template, call task) with start and stop rules. */
class FollowUpSequencesTest extends TestCase
{
    use DatabaseTransactions, CrmFixtures;

    protected LeadCaptureForm $form;
    protected int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
        Carbon::setTestNow(Carbon::parse('2026-11-02 11:00', 'Asia/Dhaka')->setTimezone(config('app.timezone')));   // daytime: no quiet hours

        $this->app->bind(SMS::class, fn () => new class extends SMS {
            public function callApi($url, $params)
            {
                return json_encode(['status' => 'SUCCESS']);
            }
        });
        DB::table('settings')->where('id', '!=', 1)->delete();
        if (!DB::table('settings')->where('id', 1)->exists()) {
            DB::table('settings')->insert(['id' => 1, 'company_name' => 'Zen Hearing', 'created_at' => now()]);
        }
        Setting::find(1)->update(['company_name' => 'Zen Hearing', 'phone' => '09600000000', 'sms_automation_mode' => 'all']);
        DB::table('sequences')->update(['is_active' => false]);
        DB::table('sms_logs')->delete();
        $this->form = LeadCaptureForm::create(['name' => 'Inbox', 'key' => LeadCaptureForm::newKey(), 'secret' => 'sek', 'is_active' => true, 'sla_minutes' => 15]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function inbound(string $source = 'facebook', array $extra = []): Deal
    {
        $phone = '0172' . str_pad((string) (++$this->seq), 7, '0', STR_PAD_LEFT);
        app(WebLeadIntake::class)->accept($this->form, $extra + ['name' => 'Patient ' . $this->seq, 'phone' => $phone, 'source' => $source,
            'client_ip' => '1.1.1.1', 'elapsed_seconds' => 99], '88' . $phone);

        return Deal::withoutGlobalScopes()->whereHas('lead', fn ($q) => $q->withoutGlobalScopes()->where('normalized_phone', '88' . $phone))->latest('id')->firstOrFail();
    }

    private function sequence(array $attrs, array $steps): Sequence
    {
        $s = Sequence::create($attrs + ['name' => 'Test sequence', 'is_active' => true, 'trigger' => 'manual', 'stop_on_reply' => true, 'stop_on_appointment' => true]);
        foreach ($steps as $i => $step) {
            $s->steps()->create($step + ['position' => $i + 1]);
        }

        return $s;
    }

    private function tick(int $minutes = 0): void
    {
        if ($minutes) {
            Carbon::setTestNow(now()->addMinutes($minutes));
        }
        app(SequenceService::class)->runDue();
    }

    public function test_the_examples_are_there_and_switched_off(): void
    {
        $this->assertGreaterThanOrEqual(2, Sequence::count());
        $this->assertSame(0, Sequence::where('is_active', true)->count());
        $this->assertTrue(Sequence::where('name', 'New ad lead – first week')->first()->steps()->exists());
    }

    public function test_a_new_ad_lead_gets_its_sequence_and_the_steps_run_on_time(): void
    {
        $this->sequence(['trigger' => 'deal_created', 'trigger_sources' => ['facebook']], [
            ['delay_minutes' => 0, 'channel' => 'sms', 'body' => 'Dear {{name}}, thanks for contacting {{company}}. Call {{company_phone}}.'],
            ['delay_minutes' => 1440, 'channel' => 'call', 'body' => 'Offer a free test'],
        ]);

        $deal = $this->inbound('facebook');
        $this->inbound('website');
        $this->assertSame(1, SequenceEnrollment::count(), 'only the Facebook lead');
        $e = SequenceEnrollment::first();

        $this->tick();
        $sms = DB::table('sms_logs')->first();
        $this->assertSame('Dear Patient 1, thanks for contacting Zen Hearing. Call 09600000000.', $sms->message);
        $this->assertSame('sequence', $sms->automation_trigger);
        $this->assertNull($deal->fresh()->first_contact_at, 'an automatic SMS is not the first contact');
        $this->assertSame(2, $e->fresh()->next_position);

        $this->tick(60);
        $this->assertSame(1, DB::table('sms_logs')->count(), 'the call step waits a day');

        $this->tick(1440);
        $call = DB::table('lead_follow_ups')->where('deal_id', $deal->id)->where('activity_type', 'call')->where('status', 'pending')
            ->where('follow_up_notes', 'like', '%Offer a free test%')->first();
        $this->assertNotNull($call);
        $this->assertSame((int) $deal->owner_id, (int) $call->assigned_user);
        $this->assertSame('completed', $e->fresh()->status);
    }

    public function test_reply_booking_stage_change_and_winning_stop_a_sequence(): void
    {
        $steps = [['delay_minutes' => 60, 'channel' => 'sms', 'body' => 'Hello'], ['delay_minutes' => 60, 'channel' => 'sms', 'body' => 'Again']];
        $seq = $this->sequence(['stop_on_stage_change' => true], $steps);
        $svc = app(SequenceService::class);

        // reply on WhatsApp
        $d1 = $this->inbound();
        $e1 = $svc->start($seq, $d1);
        $lead1 = DB::table('leads')->find($d1->lead_id);
        $svc->stopForPhone('+88' . $lead1->phone_number, 'Replied on WhatsApp');
        $this->assertSame('stopped', $e1->fresh()->status);

        // appointment booked
        $d2 = $this->inbound();
        $e2 = $svc->start($seq, $d2);
        DB::table('appointments')->insert(['lead_id' => $d2->lead_id, 'appointment_date' => now()->addDay()->toDateString(), 'status' => 'scheduled', 'created_at' => now()->addMinute(), 'updated_at' => now()]);
        $this->tick(61);
        $this->assertSame('Appointment booked', $e2->fresh()->stop_reason);

        // moved to another stage
        $d3 = $this->inbound();
        $e3 = $svc->start($seq, $d3);
        DB::table('deals')->where('id', $d3->id)->update(['stage_id' => Pipeline::default()->activeStages()->get()[1]->id]);
        $this->tick(61);
        $this->assertSame('Deal moved to another stage', $e3->fresh()->stop_reason);

        // won
        $d4 = $this->inbound();
        $e4 = $svc->start($seq, $d4);
        app(DealService::class)->markWon($d4->fresh());
        $this->assertSame('Deal won', $e4->fresh()->stop_reason);

        $this->assertSame(0, DB::table('sms_logs')->where('message', 'Hello')->count(), 'none of them got the message');
    }

    public function test_sms_follow_the_sms_center_rules(): void
    {
        $seq = $this->sequence([], [['delay_minutes' => 0, 'channel' => 'sms', 'body' => 'Offer'], ['delay_minutes' => 0, 'channel' => 'sms', 'body' => 'Offer 2']]);
        $deal = $this->inbound();

        Setting::find(1)->update(['sms_automation_mode' => 'off']);
        $e = app(SequenceService::class)->start($seq, $deal);
        $this->tick();
        $this->assertSame('skipped', DB::table('sequence_runs')->where('enrollment_id', $e->id)->value('status'));
        $this->assertSame(0, DB::table('sms_logs')->count());

        Setting::find(1)->update(['sms_automation_mode' => 'all']);
        DB::table('blocked_contacts')->insert(['type' => \App\Models\BlockedContact::SMS_OPT_OUT, 'value' => \App\Models\BlockedContact::normalize('sms', DB::table('leads')->where('id', $deal->lead_id)->value('phone_number')), 'created_at' => now(), 'updated_at' => now()]);
        $this->tick();
        $this->assertSame('Number opted out of SMS', DB::table('sequence_runs')->where('enrollment_id', $e->id)->latest('id')->value('detail'));
        $this->assertSame(0, DB::table('sms_logs')->count());
    }

    public function test_messages_wait_for_the_morning(): void
    {
        $seq = $this->sequence([], [['delay_minutes' => 0, 'channel' => 'sms', 'body' => 'Night?']]);
        $deal = $this->inbound();
        Carbon::setTestNow(Carbon::parse('2026-11-02 23:00', 'Asia/Dhaka')->setTimezone(config('app.timezone')));
        $e = app(SequenceService::class)->start($seq, $deal);

        $this->tick();
        $this->assertSame(0, DB::table('sms_logs')->count());
        $this->assertSame('09:00', $e->fresh()->next_run_at->timezone('Asia/Dhaka')->format('H:i'));
    }

    public function test_email_and_whatsapp_steps(): void
    {
        Mail::fake();
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.X']]])]);
        $seq = $this->sequence([], [
            ['delay_minutes' => 0, 'channel' => 'email', 'subject' => 'Your hearing test, {{name}}', 'body' => 'Dear {{name}}, book anytime.'],
            ['delay_minutes' => 0, 'channel' => 'whatsapp', 'wa_template' => 'hearing_offer', 'wa_language' => 'bn'],
            ['delay_minutes' => 0, 'channel' => 'whatsapp', 'wa_template' => 'hearing_offer'],
        ]);
        $deal = $this->inbound();
        DB::table('leads')->where('id', $deal->lead_id)->update(['email' => 'patient@example.com']);
        $e = app(SequenceService::class)->start($seq, $deal);

        $this->tick();
        $this->assertSame('sent', DB::table('sequence_runs')->where('enrollment_id', $e->id)->where('channel', 'email')->value('status'));

        $this->tick();
        $this->assertStringContainsString('not set up', DB::table('sequence_runs')->where('enrollment_id', $e->id)->where('channel', 'whatsapp')->value('detail'), 'no keys: skipped');

        config(['connectors.meta.wa_phone_number_id' => '1234', 'connectors.meta.wa_token' => 'tok']);
        $this->tick();
        $this->assertSame('sent', DB::table('sequence_runs')->where('enrollment_id', $e->id)->where('channel', 'whatsapp')->latest('id')->value('status'));
        Http::assertSent(fn ($r) => str_contains($r->url(), '/1234/messages') && $r['template']['name'] === 'hearing_offer' && $r['template']['language']['code'] === 'en');
    }

    public function test_reaching_a_stage_and_losing_a_deal_start_their_sequences(): void
    {
        $trial = Pipeline::default()->activeStages()->where('status_key', 'trial')->first();
        $this->sequence(['name' => 'After trial', 'trigger' => 'stage_entered', 'trigger_stage_id' => $trial->id, 'stop_on_stage_change' => true], [['delay_minutes' => 1440, 'channel' => 'call', 'body' => 'How was it?']]);
        $reason = \App\Models\LostReason::first();
        $this->sequence(['name' => 'Check in later', 'trigger' => 'deal_lost', 'trigger_lost_reasons' => [$reason->id]], [['delay_minutes' => 43200, 'channel' => 'sms', 'body' => 'Still there?']]);

        $deal = $this->inbound();
        app(DealService::class)->move($deal->fresh(), $trial, ['trial_product' => 'Phonak L70']);
        $this->assertTrue(SequenceEnrollment::where('deal_id', $deal->id)->whereHas('sequence', fn ($q) => $q->where('name', 'After trial'))->where('status', 'active')->exists());

        app(DealService::class)->markLost($deal->fresh(), $reason->id);
        $this->assertSame('stopped', SequenceEnrollment::whereHas('sequence', fn ($q) => $q->where('name', 'After trial'))->value('status'));
        $later = SequenceEnrollment::whereHas('sequence', fn ($q) => $q->where('name', 'Check in later'))->first();
        $this->assertSame('active', $later->status);

        // the person comes back with a new enquiry: the check-in ends
        app(DealService::class)->openNewDeal(\App\Models\Lead::withoutGlobalScopes()->find($deal->lead_id), ['owner_id' => $this->admin->id]);
        $this->assertSame('New enquiry from the same person', $later->fresh()->stop_reason);
    }

    public function test_sequences_are_managed_and_started_from_a_deal(): void
    {
        $this->actingAs($this->admin)->post('/admin/sequences', ['name' => 'Empty text', 'trigger' => 'manual', 'steps' => [['channel' => 'sms', 'delay_value' => 0, 'delay_unit' => 'minutes', 'body' => '']]])
            ->assertSessionHasErrors('steps.0.body');

        $this->actingAs($this->admin)->post('/admin/sequences', ['name' => 'Two-day nurture', 'trigger' => 'manual', 'is_active' => 1, 'stop_on_reply' => 1, 'steps' => [
            ['channel' => 'sms', 'delay_value' => 0, 'delay_unit' => 'minutes', 'body' => 'Hi {{name}}'],
            ['channel' => 'call', 'delay_value' => 2, 'delay_unit' => 'days', 'body' => 'Call back'],
        ]])->assertRedirect(route('sequences.index'));
        $seq = Sequence::where('name', 'Two-day nurture')->first();
        $this->assertSame([0, 2880], $seq->steps->pluck('delay_minutes')->all());

        $this->actingAs($this->admin)->get('/admin/sequences')->assertSuccessful()->assertSee('Two-day nurture')->assertSee('after 2 days');
        $this->actingAs($this->admin)->get('/admin/sequences/' . $seq->id . '/edit')->assertSuccessful()->assertSee('Call back');

        $deal = $this->inbound('website');
        $this->actingAs($this->admin)->get('/admin/deals/' . $deal->id)->assertSuccessful()->assertSee('dd-seq-start', false);
        $this->actingAs($this->admin)->post('/admin/deals/' . $deal->id . '/sequence', ['sequence_id' => $seq->id])->assertSessionHas('success');
        $e = SequenceEnrollment::where('deal_id', $deal->id)->first();
        $this->actingAs($this->admin)->get('/admin/deals/' . $deal->id)->assertSee('Two-day nurture')->assertSee('dd-seq-stop-' . $e->id, false);

        $this->actingAs($this->admin)->post('/admin/sequence-enrollments/' . $e->id . '/stop')->assertSessionHas('success');
        $this->assertSame('stopped', $e->fresh()->status);

        $this->actingAs($this->admin)->post('/admin/sequences/' . $seq->id . '/toggle');
        $this->assertFalse($seq->fresh()->is_active);
    }
}
