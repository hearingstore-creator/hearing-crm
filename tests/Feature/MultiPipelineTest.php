<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\Lead;
use App\Models\LeadAssignmentRule;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Sequence;
use App\Models\User;
use App\Services\DealService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** More than one sales pipeline: settings, deals in a pipeline, the board switcher, moving between pipelines, reports. */
class MultiPipelineTest extends TestCase
{
    use DatabaseTransactions, CrmFixtures;

    protected int $phoneSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
    }

    private function lead(string $status = 'new'): Lead
    {
        return Lead::create([
            'lead_uid' => Lead::generateUid(), 'full_name' => 'Person ' . (++$this->phoneSeq),
            'phone_number' => '0181' . str_pad((string) $this->phoneSeq, 7, '0', STR_PAD_LEFT),
            'lead_status' => $status, 'inquiry_for' => ['hearing_aid'],
        ]);
    }

    private function createPipeline(string $name = 'Cochlear implants'): Pipeline
    {
        $this->actingAs($this->admin)->post('/admin/pipeline-settings/pipelines', ['name' => $name, 'description' => 'CI sales'])
            ->assertRedirect()->assertSessionHas('success');

        return Pipeline::where('name', $name)->firstOrFail();
    }

    public function test_the_examples_are_seeded_switched_off_and_the_default_stays(): void
    {
        $this->assertSame('Hearing Care Sales', Pipeline::default()->name);
        foreach (['Repairs', 'Institutional / corporate'] as $name) {
            $p = Pipeline::where('name', $name)->firstOrFail();
            $this->assertFalse($p->is_active);
            $this->assertFalse($p->is_default);
            $this->assertTrue($p->activeStages()->first()->is_system);
            $this->assertSame(0, $p->stages()->whereNotNull('status_key')->count(), 'only the default pipeline mirrors lead status');
        }
        $this->assertSame([Pipeline::default()->id], Pipeline::usable()->pluck('id')->all());
    }

    public function test_a_pipeline_is_created_renamed_switched_off_and_archived(): void
    {
        $p = $this->createPipeline();
        $this->assertTrue($p->is_active);
        $this->assertSame(['New', 'Contacted'], $p->activeStages()->pluck('name')->all());

        // stages are edited per pipeline and never get a lead status
        $this->actingAs($this->admin)->post('/admin/pipeline-settings/stages', [
            'pipeline_id' => $p->id, 'name' => 'Surgery booked', 'color' => '#123456', 'probability' => 80, 'status_key' => 'trial',
        ])->assertSessionHas('success');
        $stage = $p->stages()->where('name', 'Surgery booked')->firstOrFail();
        $this->assertNull($stage->status_key);
        $this->actingAs($this->admin)->get('/admin/pipeline-settings?pipeline=' . $p->id)->assertSuccessful()
            ->assertSee('Surgery booked')->assertSee('pipeline-tab-' . $p->id, false)->assertSee('shared by every pipeline');

        $this->actingAs($this->admin)->put('/admin/pipeline-settings/pipelines/' . $p->id, ['name' => 'Cochlear implant sales'])
            ->assertSessionHas('success');
        $p->refresh();
        $this->assertSame('Cochlear implant sales', $p->name);
        $this->assertFalse($p->is_active, 'unticked = switched off');

        // the default pipeline cannot be switched off or archived
        $default = Pipeline::default();
        $this->actingAs($this->admin)->put('/admin/pipeline-settings/pipelines/' . $default->id, ['name' => $default->name]);
        $this->assertTrue($default->fresh()->is_active);
        $this->actingAs($this->admin)->post('/admin/pipeline-settings/pipelines/' . $default->id . '/archive')->assertSessionHas('warning');
        $this->assertNull($default->fresh()->archived_at);

        // a pipeline with open deals cannot be archived
        $p->update(['is_active' => true]);
        $deal = app(DealService::class)->openNewDeal($this->lead(), ['pipeline_id' => $p->id]);
        $this->actingAs($this->admin)->post('/admin/pipeline-settings/pipelines/' . $p->id . '/archive')->assertSessionHas('warning');
        $deal->update(['status' => Deal::LOST, 'lost_at' => now()]);
        $this->actingAs($this->admin)->post('/admin/pipeline-settings/pipelines/' . $p->id . '/archive')->assertSessionHas('success');
        $this->assertNotNull($p->fresh()->archived_at);
        $this->assertFalse(Pipeline::usable()->whereKey($p->id)->exists());

        $this->actingAs($this->admin)->post('/admin/pipeline-settings/pipelines/' . $p->id . '/restore')->assertSessionHas('success');
        $this->assertNull($p->fresh()->archived_at);
    }

    public function test_deals_open_in_the_chosen_pipeline_without_touching_the_lead_status(): void
    {
        $repairs = Pipeline::where('name', 'Repairs')->firstOrFail();
        $owner = User::forceCreate(['name' => 'Repair desk', 'email' => 'repair@test.test', 'password' => bcrypt('x'), 'status' => 1]);
        $repairs->update(['is_active' => true, 'default_owner_id' => $owner->id]);
        $deals = app(DealService::class);

        $lead = $this->lead('counsel');
        $hearing = $deals->openInitialDeal($lead);
        $this->assertSame(Pipeline::default()->id, (int) $hearing->pipeline_id);
        $this->assertSame('counsel', $lead->fresh()->lead_status);

        $repair = $deals->openNewDeal($lead, ['pipeline_id' => $repairs->id, 'owner_id' => $owner->id]);
        $this->assertSame($repairs->id, (int) $repair->pipeline_id);
        $this->assertSame('New', $repair->stage->name);
        $deals->move($repair, $repairs->activeStages()->where('name', 'In repair')->firstOrFail());
        $this->assertSame('counsel', $lead->fresh()->lead_status, 'a repair deal never changes the lead status');

        // the default pipeline keeps mirroring
        $deals->move($hearing->fresh(), Pipeline::default()->activeStages()->where('status_key', 'trial')->firstOrFail());
        $this->assertSame('trial', $lead->fresh()->lead_status);

        // a switched-off pipeline falls back to the default; a stage of another pipeline never sneaks in
        $repairs->update(['is_active' => false]);
        $fallback = $deals->openNewDeal($this->lead(), ['pipeline_id' => $repairs->id, 'stage_id' => $repairs->activeStages()->first()->id]);
        $this->assertSame(Pipeline::default()->id, (int) $fallback->pipeline_id);
        $this->assertSame(Pipeline::default()->id, (int) $fallback->stage->pipeline_id);
    }

    public function test_quick_add_and_new_deal_for_a_person_pick_the_pipeline(): void
    {
        $repairs = Pipeline::where('name', 'Repairs')->firstOrFail();
        $repairs->update(['is_active' => true]);

        $this->actingAs($this->admin)->postJson('/admin/deals', [
            'full_name' => 'Rahim', 'phone_number' => '01911000001', 'inquiry_for' => ['hearing_aid'], 'pipeline_id' => $repairs->id,
        ])->assertSuccessful();
        $lead = Lead::where('phone_number', '01911000001')->firstOrFail();
        $this->assertSame($repairs->id, (int) $lead->deals()->firstOrFail()->pipeline_id);

        $this->actingAs($this->admin)->postJson('/admin/deals', [
            'existing_lead_id' => $lead->id, 'inquiry_for' => ['hearing_aid'],
        ])->assertSuccessful();
        $this->assertSame(1, $lead->deals()->where('pipeline_id', Pipeline::default()->id)->count(), 'no pipeline chosen = default');

        // a switched-off pipeline is refused
        $repairs->update(['is_active' => false]);
        $this->actingAs($this->admin)->postJson('/admin/deals', [
            'existing_lead_id' => $lead->id, 'inquiry_for' => ['hearing_aid'], 'pipeline_id' => $repairs->id,
        ])->assertStatus(422)->assertJsonValidationErrors('pipeline_id');
    }

    public function test_the_board_switches_pipeline_and_remembers_it(): void
    {
        $repairs = Pipeline::where('name', 'Repairs')->firstOrFail();
        $deals = app(DealService::class);
        $hearing = $deals->openNewDeal($this->lead());
        $repairs->update(['is_active' => true]);
        $repair = $deals->openNewDeal($this->lead(), ['pipeline_id' => $repairs->id]);
        $hearingName = $hearing->lead->full_name;
        $repairName = $repair->lead->full_name;

        $this->actingAs($this->admin)->get('/admin/deals')->assertSuccessful()
            ->assertSee('pipeline-switch', false)->assertSee($hearingName)->assertDontSee($repairName);

        $this->actingAs($this->admin)->get('/admin/deals?pipeline=' . $repairs->id)->assertSuccessful()
            ->assertSee('Ready for pickup')->assertSee($repairName)->assertDontSee($hearingName);
        $this->assertSame($repairs->id, (int) $this->admin->fresh()->deal_pipeline_id);

        // remembered, also in the list view
        $this->actingAs($this->admin->fresh())->get('/admin/deals?status=list')->assertSee($repairName)->assertDontSee($hearingName);

        // switched off → back to the default
        $repairs->update(['is_active' => false]);
        $this->actingAs($this->admin->fresh())->get('/admin/deals')->assertSee($hearingName)->assertDontSee('Ready for pickup');
    }

    public function test_a_deal_moves_to_another_pipeline_first_stage_with_history(): void
    {
        $repairs = Pipeline::where('name', 'Repairs')->firstOrFail();
        $deal = app(DealService::class)->openNewDeal($this->lead());

        $this->actingAs($this->admin)->postJson('/admin/deals/' . $deal->id . '/pipeline', ['pipeline_id' => $repairs->id])
            ->assertStatus(422);   // switched off

        $repairs->update(['is_active' => true]);
        $this->actingAs($this->admin)->get('/admin/deals/' . $deal->id)->assertSuccessful()->assertSee('dd-pipeline-move', false);
        $this->actingAs($this->admin)->postJson('/admin/deals/' . $deal->id . '/pipeline', ['pipeline_id' => $repairs->id, 'note' => 'Aid broken'])
            ->assertSuccessful()->assertJson(['pipeline_id' => $repairs->id]);

        $deal->refresh();
        $this->assertSame($repairs->id, (int) $deal->pipeline_id);
        $this->assertSame($repairs->activeStages()->first()->id, (int) $deal->stage_id);
        $this->assertTrue(DB::table('lead_status_histories')->where('deal_id', $deal->id)->where('to_stage_id', $deal->stage_id)
            ->where('notes', 'Moved from Hearing Care Sales to Repairs: Aid broken')->exists());

        // the same pipeline is refused
        $this->actingAs($this->admin)->postJson('/admin/deals/' . $deal->id . '/pipeline', ['pipeline_id' => $repairs->id])->assertStatus(422);
    }

    public function test_reports_funnel_and_forecast_are_per_pipeline(): void
    {
        $repairs = Pipeline::where('name', 'Repairs')->firstOrFail();
        $repairs->update(['is_active' => true]);
        $deals = app(DealService::class);
        $deals->openNewDeal($this->lead(), ['value' => 1000]);
        $r = $deals->openNewDeal($this->lead(), ['pipeline_id' => $repairs->id, 'value' => 777]);
        $deals->move($r, $repairs->activeStages()->where('name', 'Quote sent')->firstOrFail());

        $this->actingAs($this->admin)->get('/admin/deal-reports?pipeline=' . $repairs->id)->assertSuccessful()
            ->assertSee('Quote sent')->assertSee('Ready for pickup')->assertSee('dr-pipeline', false)
            ->assertViewHas('forecast', fn ($f) => collect($f)->sum('count') === 1)
            ->assertViewHas('summary', fn ($s) => $s['deals'] === 1);

        $this->actingAs($this->admin)->get('/admin/deal-reports')->assertSuccessful()
            ->assertDontSee('Ready for pickup')
            ->assertViewHas('pipeline', fn ($p) => $p->is_default);
    }

    public function test_rules_and_sequences_can_be_limited_to_a_pipeline(): void
    {
        $repairs = Pipeline::where('name', 'Repairs')->firstOrFail();
        $repairs->update(['is_active' => true]);
        $tech = User::forceCreate(['name' => 'Tech', 'email' => 'tech@test.test', 'password' => bcrypt('x'), 'status' => 1]);
        DB::table('lead_assignment_rules')->delete();
        LeadAssignmentRule::create(['name' => 'Repairs desk', 'method' => 'fixed', 'is_active' => true, 'include_manual' => true,
            'sort_order' => 1, 'user_ids' => [$tech->id], 'pipeline_ids' => [$repairs->id]]);

        $this->actingAs($this->admin);
        $deals = app(DealService::class);
        $this->assertSame($tech->id, (int) $deals->openNewDeal($this->lead(), ['pipeline_id' => $repairs->id])->owner_id);
        $this->assertNotSame($tech->id, (int) $deals->openNewDeal($this->lead())->owner_id, 'the rule only covers Repairs');

        // existing sequences were pinned to the default pipeline; a Repairs one only starts for repair deals
        $seq = Sequence::create(['name' => 'Repair welcome', 'is_active' => true, 'trigger' => 'deal_created', 'trigger_pipeline_id' => $repairs->id]);
        $seq->steps()->create(['position' => 1, 'channel' => 'call', 'delay_minutes' => 60, 'body' => 'Check the device']);
        $hearing = $deals->openNewDeal($this->lead());
        $repair = $deals->openNewDeal($this->lead(), ['pipeline_id' => $repairs->id]);
        $this->assertFalse(DB::table('sequence_enrollments')->where('sequence_id', $seq->id)->where('deal_id', $hearing->id)->exists());
        $this->assertTrue(DB::table('sequence_enrollments')->where('sequence_id', $seq->id)->where('deal_id', $repair->id)->exists());

        // the sequence form saves the pipeline of the chosen stage
        $stage = $repairs->activeStages()->where('name', 'Ready for pickup')->firstOrFail();
        $this->post('/admin/sequences', ['name' => 'Pickup reminder', 'trigger' => 'stage_entered', 'trigger_stage_id' => $stage->id, 'is_active' => 1,
            'steps' => [['channel' => 'call', 'delay_value' => 1, 'delay_unit' => 'days', 'body' => 'Remind pickup']]])->assertRedirect();
        $this->assertSame($repairs->id, (int) Sequence::where('name', 'Pickup reminder')->value('trigger_pipeline_id'));
        $this->get('/admin/sequences/create')->assertSuccessful()->assertSee('<optgroup label="Repairs">', false);
    }
}
