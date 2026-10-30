<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\LeadAssignmentRule;
use App\Models\LeadCaptureForm;
use App\Models\Pipeline;
use App\Models\User;
use App\Services\LeadScorer;
use App\Services\WebLeadIntake;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Marketing phase 3: assignment rules, first-contact deadline with escalation, and the lead score. */
class LeadRoutingAndScoringTest extends TestCase
{
    use DatabaseTransactions, CrmFixtures;

    protected LeadCaptureForm $form;
    protected array $team = [];
    protected int $phoneSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
        $this->form = LeadCaptureForm::create(['name' => 'Inbox', 'key' => LeadCaptureForm::newKey(), 'secret' => 's', 'is_active' => true, 'sla_minutes' => 15]);
        foreach (['Ayesha', 'Babul', 'Chandni'] as $name) {
            $this->team[$name] = User::forceCreate(['name' => $name, 'email' => strtolower($name) . '@team.test', 'password' => bcrypt('x'), 'status' => 1])->id;
        }
        DB::table('lead_assignment_rules')->delete();
        Pipeline::default()->update(['scoring' => null]);
    }

    /** A lead arriving by itself (website / connector), as the queue would process it. */
    private function inbound(string $source = 'facebook', array $extra = []): Deal
    {
        $phone = '0171' . str_pad((string) (++$this->phoneSeq), 7, '0', STR_PAD_LEFT);
        app(WebLeadIntake::class)->accept($this->form, $extra + ['name' => 'Lead ' . $this->phoneSeq, 'phone' => $phone, 'source' => $source,
            'client_ip' => '1.1.1.1', 'elapsed_seconds' => 99], '88' . $phone);

        return Deal::withoutGlobalScopes()->whereHas('lead', fn ($q) => $q->withoutGlobalScopes()->where('normalized_phone', '88' . $phone))->latest('id')->firstOrFail();
    }

    private function rule(array $attrs): LeadAssignmentRule
    {
        return LeadAssignmentRule::create($attrs + ['name' => 'Rule', 'method' => 'round_robin', 'is_active' => true, 'sort_order' => 10,
            'user_ids' => array_values($this->team)]);
    }

    // ── assignment ──

    public function test_matching_leads_take_turns_and_others_go_to_the_default_owner(): void
    {
        $this->rule(['name' => 'Facebook team', 'sources' => ['facebook', 'instagram']]);

        $owners = collect(range(1, 4))->map(fn () => $this->inbound('facebook')->owner_id)->all();
        $this->assertSame([$this->team['Ayesha'], $this->team['Babul'], $this->team['Chandni'], $this->team['Ayesha']], $owners);

        $website = $this->inbound('website');
        $this->assertSame((int) Pipeline::default()->default_owner_id, (int) $website->owner_id, 'no rule for website leads');
        $this->assertNull($website->assigned_rule_id);

        $deal = $this->inbound('instagram');
        $this->assertTrue(DB::table('lead_follow_ups')->where('deal_id', $deal->id)->where('follow_up_notes', 'Assigned by rule "Facebook team"')->exists());
        $this->assertSame($deal->owner_id, (int) DB::table('lead_follow_ups')->where('deal_id', $deal->id)->where('activity_type', 'call')->value('assigned_user'), 'the first call goes to the new owner');
        $this->assertTrue(DB::table('notifications')->where('notifiable_id', $deal->owner_id)->where('data', 'like', '%New lead for you%')->exists());
    }

    public function test_fewest_open_deals_wins_and_busy_absent_or_inactive_people_are_skipped(): void
    {
        $this->rule(['method' => 'least_busy', 'max_open' => 3]);
        $pipeline = Pipeline::default();
        $stage = $pipeline->activeStages()->first();
        $lead = DB::table('leads')->insertGetId(['full_name' => 'Busy', 'phone_number' => '01999999990', 'created_at' => now()]);
        foreach ([['Ayesha', 3], ['Babul', 1]] as [$who, $n]) {
            for ($i = 0; $i < $n; $i++) {
                DB::table('deals')->insert(['lead_id' => $lead, 'pipeline_id' => $pipeline->id, 'stage_id' => $stage->id, 'status' => 'open', 'owner_id' => $this->team[$who], 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        $this->assertSame($this->team['Chandni'], $this->inbound()->owner_id, 'Chandni has none');

        DB::table('staff_leaves')->insert(['user_id' => $this->team['Chandni'], 'start_datetime' => now()->subHour(), 'end_datetime' => now()->addDay(), 'leave_type' => 'casual', 'status' => 'approved', 'created_at' => now()]);
        $this->assertSame($this->team['Babul'], $this->inbound()->owner_id, 'Chandni is on leave, Ayesha is at the limit');

        User::whereKey($this->team['Babul'])->update(['status' => 0]);
        $this->assertSame((int) Pipeline::default()->default_owner_id, (int) $this->inbound()->owner_id, 'nobody free: default owner');
    }

    public function test_interest_and_branch_conditions_and_handing_to_a_branch(): void
    {
        $this->rule(['name' => 'Repairs to branch', 'inquiries' => ['repair'], 'set_vendor_id' => $this->branch->id, 'user_ids' => [$this->team['Babul']], 'sort_order' => 1]);
        $this->rule(['name' => 'Everything else', 'user_ids' => [$this->team['Ayesha']], 'sort_order' => 2]);

        $repair = $this->inbound('website', ['inquiry_for' => ['repair']]);
        $this->assertSame($this->team['Babul'], $repair->owner_id);
        $this->assertSame($this->branch->id, (int) $repair->vendor_id);

        $this->assertSame($this->team['Ayesha'], $this->inbound('website', ['inquiry_for' => ['hearing_aid']])->owner_id);
    }

    public function test_typed_in_leads_follow_rules_only_when_the_rule_says_so(): void
    {
        $stage = Pipeline::default()->activeStages()->first();
        $post = fn (string $phone) => $this->actingAs($this->admin)->post('/admin/leads', ['full_name' => 'Walk In', 'phone_number' => $phone,
            'inquiry_for' => ['hearing_aid'], 'stage_id' => $stage->id, 'activity_type' => 'call', 'due_at' => now()->addHour()->toDateTimeString(),
            'source_of_entry' => 'phone_call'])->assertSessionHasNoErrors();
        $ownerOf = fn (string $phone) => (int) DB::table('deals')->where('lead_id', DB::table('leads')->where('phone_number', $phone)->value('id'))->value('owner_id');

        $rule = $this->rule(['user_ids' => [$this->team['Chandni']]]);
        $post('01811111111');
        $this->assertNotSame($this->team['Chandni'], $ownerOf('01811111111'));

        $rule->update(['include_manual' => true]);
        $post('01811111112');
        $this->assertSame($this->team['Chandni'], $ownerOf('01811111112'));
    }

    // ── first contact deadline ──

    public function test_a_late_lead_is_escalated_then_moved_to_the_next_person_once(): void
    {
        $this->rule(['user_ids' => [$this->team['Ayesha'], $this->team['Babul']], 'reassign_after_minutes' => 30]);
        $deal = $this->inbound();
        $this->assertNotNull($deal->first_contact_due_at, 'inbound leads get a first-contact deadline');
        $this->assertSame($this->team['Ayesha'], $deal->owner_id);

        // 10 minutes late: alert, no move yet
        $deal->forceFill(['first_contact_due_at' => now()->subMinutes(10)])->saveQuietly();
        Artisan::call('crm:automations', ['task' => 'sla']);
        $deal->refresh();
        $this->assertNotNull($deal->sla_alerted_at);
        $this->assertSame($this->team['Ayesha'], $deal->owner_id);

        // 40 minutes late: moved on to Babul with the waiting call
        $deal->forceFill(['first_contact_due_at' => now()->subMinutes(40)])->saveQuietly();
        Artisan::call('crm:automations', ['task' => 'sla']);
        $deal->refresh();
        $this->assertSame($this->team['Babul'], $deal->owner_id);
        $this->assertSame(1, (int) $deal->sla_reassigned);
        $this->assertSame($this->team['Babul'], (int) DB::table('lead_follow_ups')->where('deal_id', $deal->id)->where('activity_type', 'call')->where('status', 'pending')->value('assigned_user'));

        Artisan::call('crm:automations', ['task' => 'sla']);
        $this->assertSame($this->team['Babul'], $deal->fresh()->owner_id, 'moved only once');
    }

    public function test_a_call_stops_the_clock(): void
    {
        $deal = $this->inbound('whatsapp');
        $call = \App\Models\LeadFollowUp::where('deal_id', $deal->id)->where('activity_type', 'call')->first();
        app(\App\Services\DealService::class)->recordOutcome($call, \App\Enums\CallOutcome::NO_ANSWER);
        $deal->refresh();
        $this->assertNotNull($deal->first_contact_at);

        $deal->forceFill(['first_contact_due_at' => now()->subHour()])->saveQuietly();
        Artisan::call('crm:automations', ['task' => 'sla']);
        $this->assertNull($deal->fresh()->sla_alerted_at);
    }

    // ── lead score ──

    public function test_the_score_adds_up_its_reasons(): void
    {
        $deal = $this->inbound('website', ['inquiry_for' => ['hearing_aid']]);
        DB::table('leads')->where('id', $deal->lead_id)->update(['age' => 70, 'approx_budget' => 60000,
            'hearing_loss' => json_encode(['right' => ['enabled' => true, 'degree' => 'moderate']])]);
        DB::table('lead_follow_ups')->where('deal_id', $deal->id)->update(['follow_up_date' => now()->addHour()]);   // nothing overdue

        $score = app(LeadScorer::class)->refresh($deal->fresh());
        $this->assertSame(20 + 20 + 15 + 10 + 5, $score);
        $this->assertSame('hot', LeadScorer::band($score));
        $labels = collect($deal->fresh()->score_reasons)->pluck('label');
        $this->assertTrue($labels->contains('Moderate or worse hearing loss recorded'));

        // an overdue next step costs points
        DB::table('lead_follow_ups')->where('deal_id', $deal->id)->update(['follow_up_date' => now()->subDay()]);
        $this->assertSame(65, app(LeadScorer::class)->refresh($deal->fresh()));
    }

    public function test_new_deals_are_scored_and_weights_can_be_changed(): void
    {
        $deal = $this->inbound('website', ['inquiry_for' => ['hearing_test']]);
        $this->assertNotNull($deal->score, 'scored when opened');
        $before = $deal->score;

        $this->actingAs($this->admin)->put('/admin/pipeline-settings/lead-score', ['weights' => ['interest_test' => 30]])->assertSessionHas('success');
        $this->assertSame(['interest_test' => 30], Pipeline::default()->scoring);
        $this->assertSame($before + 18, (int) $deal->fresh()->score, 'all open deals re-scored');
    }

    // ── screens ──

    public function test_settings_rules_can_be_managed_and_the_board_shows_scores(): void
    {
        $this->actingAs($this->admin)->post('/admin/pipeline-settings/assignment-rules', [
            'name' => 'Ads team', 'method' => 'round_robin', 'user_ids' => [$this->team['Ayesha']], 'sources' => ['facebook'], 'is_active' => 1,
        ])->assertSessionHas('success');
        $rule = LeadAssignmentRule::where('name', 'Ads team')->first();
        $this->assertSame(['facebook'], $rule->sources);

        $this->actingAs($this->admin)->post('/admin/pipeline-settings/assignment-rules', ['name' => 'Nobody', 'method' => 'round_robin'])->assertSessionHasErrors('user_ids');

        $page = $this->actingAs($this->admin)->get('/admin/pipeline-settings')->assertSuccessful();
        $page->assertSee('Lead assignment')->assertSee('Ads team')->assertSee('Lead score');

        $deal = $this->inbound('facebook');
        $this->actingAs($this->admin)->get('/admin/deals')->assertSuccessful()->assertSee('dc-score-' . $deal->id, false);
        $this->actingAs($this->admin)->get('/admin/deals?status=list&sort=score')->assertSuccessful()->assertSee('dc-score-' . $deal->id, false);

        $this->actingAs($this->admin)->delete('/admin/pipeline-settings/assignment-rules/' . $rule->id)->assertSessionHas('success');
        $this->assertSame(0, LeadAssignmentRule::count());
    }
}
