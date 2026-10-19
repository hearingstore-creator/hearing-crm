<?php

namespace Tests\Feature;

use App\Models\LoyaltySetting;
use App\Models\MembershipMilestone;
use App\Models\MembershipTier;
use App\Services\MembershipSettingsTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Moving membership amounts from one site to another as a reviewed file. */
class MembershipTransferTest extends TestCase
{
    use RefreshDatabase, CrmFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
    }

    protected function tiers(array $earn, array $names = ['Bronze', 'Silver', 'Gold', 'Platinum']): void
    {
        MembershipTier::query()->forceDelete();
        $rows = [[0, 10000], [10000.01, 24999.99], [25000, 49999.99], [50000, null]];
        foreach ($rows as $i => [$min, $max]) {
            MembershipTier::create(['name' => $names[$i], 'min_purchase' => $min, 'max_purchase' => $max, 'earn_percentage' => $earn[$i], 'referral_reward_points' => 100 * ($i + 1)]);
        }
    }

    /** "This site" becomes the live one: old amounts. Returns the export made from the new amounts. */
    protected function newAmountsExportedThenLiveReset(): array
    {
        $this->tiers([1.5, 2.5, 3.5, 5]);
        LoyaltySetting::current()->update(['point_value' => 2, 'expiry_months' => 6, 'min_redeem_points' => 50, 'max_redeem_percent' => 40]);
        MembershipMilestone::create(['name' => 'Spend 100k', 'type' => 'lifetime_spend', 'threshold' => 100000, 'bonus_points' => 500, 'is_active' => true]);
        $file = (new MembershipSettingsTransfer())->export();

        $this->tiers([1, 2, 3, 4]);
        LoyaltySetting::current()->update(['point_value' => 1, 'expiry_months' => 12, 'min_redeem_points' => 0, 'max_redeem_percent' => 100]);
        MembershipMilestone::query()->forceDelete();

        return $file;
    }

    public function test_export_then_import_makes_the_other_site_match_without_replacing_tiers(): void
    {
        $file = $this->newAmountsExportedThenLiveReset();
        $tierIds = MembershipTier::orderBy('min_purchase')->pluck('id')->all();

        $service = new MembershipSettingsTransfer();
        $plan = $service->plan($file);
        $this->assertNotEmpty($plan['changes']);

        $service->apply($file);

        $this->assertSame($tierIds, MembershipTier::orderBy('min_purchase')->pluck('id')->all(), 'tier rows must be updated, not replaced');
        $this->assertEquals([1.5, 2.5, 3.5, 5.0], MembershipTier::orderBy('min_purchase')->pluck('earn_percentage')->map(fn ($v) => (float) $v)->all());
        $this->assertEquals(2, (float) LoyaltySetting::current()->point_value);
        $this->assertSame(6, (int) LoyaltySetting::current()->expiry_months);
        $this->assertSame(500, (int) MembershipMilestone::where('type', 'lifetime_spend')->value('bonus_points'));

        // Applying again changes nothing
        $this->assertSame([], $service->plan($file)['changes']);
    }

    public function test_tiers_are_matched_by_order_so_renamed_and_swapped_names_work(): void
    {
        $this->tiers([1, 2, 3, 4], ['Bronze', 'Silver', 'Gold', 'Platinum']);
        $file = (new MembershipSettingsTransfer())->export();
        $file['tiers'][0]['name'] = 'Silver';     // names move around between rows
        $file['tiers'][1]['name'] = 'Bronze';

        (new MembershipSettingsTransfer())->apply($file);

        $this->assertSame(['Silver', 'Bronze', 'Gold', 'Platinum'], MembershipTier::orderBy('min_purchase')->pluck('name')->all());
    }

    public function test_milestones_are_matched_by_type_and_threshold_and_others_are_left_alone(): void
    {
        MembershipMilestone::create(['name' => 'Old name', 'type' => 'purchase_count', 'threshold' => 5, 'bonus_points' => 10, 'is_active' => true]);
        MembershipMilestone::create(['name' => 'Only here', 'type' => 'purchase_count', 'threshold' => 99, 'bonus_points' => 1, 'is_active' => true]);
        $file = (new MembershipSettingsTransfer())->export();
        $file['milestones'] = [['name' => 'Fifth purchase', 'type' => 'purchase_count', 'threshold' => 5, 'bonus_points' => 75, 'is_active' => false]];

        $plan = (new MembershipSettingsTransfer())->plan($file);
        $this->assertCount(1, $plan['left_alone']);

        (new MembershipSettingsTransfer())->apply($file);
        $m = MembershipMilestone::where('type', 'purchase_count')->where('threshold', 5)->first();
        $this->assertSame('Fifth purchase', $m->name);
        $this->assertSame(75, (int) $m->bonus_points);
        $this->assertFalse((bool) $m->is_active);
        $this->assertNotNull(MembershipMilestone::where('name', 'Only here')->first());
    }

    public function test_bad_files_are_refused_with_a_reason_and_change_nothing(): void
    {
        $this->tiers([1, 2, 3, 4]);
        $good = (new MembershipSettingsTransfer())->export();
        $service = new MembershipSettingsTransfer();

        $cases = [
            'not ours'      => fn ($f) => ['hello' => 'world'],
            'wrong count'   => function ($f) { array_pop($f['tiers']); return $f; },
            'gap'           => function ($f) { $f['tiers'][1]['min_purchase'] = 12000; return $f; },
            'first not 0'   => function ($f) { $f['tiers'][0]['min_purchase'] = 5; return $f; },
            'last capped'   => function ($f) { $f['tiers'][3]['max_purchase'] = 90000; return $f; },
            'bad point'     => function ($f) { $f['loyalty']['point_value'] = -3; return $f; },
            'bad milestone' => function ($f) { $f['milestones'][] = ['name' => 'x', 'type' => 'nonsense', 'threshold' => 1, 'bonus_points' => 1]; return $f; },
        ];
        foreach ($cases as $name => $break) {
            try {
                $service->apply($break($good));
                $this->fail("$name should have been refused");
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
        $this->assertEquals([1.0, 2.0, 3.0, 4.0], MembershipTier::orderBy('min_purchase')->pluck('earn_percentage')->map(fn ($v) => (float) $v)->all());
    }

    public function test_the_screens_export_preview_and_apply(): void
    {
        $file = $this->newAmountsExportedThenLiveReset();
        $upload = UploadedFile::fake()->createWithContent('settings.json', json_encode($file));

        $this->actingAs($this->admin)->get(route('membership.settings.export'))->assertSuccessful()->assertHeader('Content-Type', 'application/json');

        $this->actingAs($this->admin)->post(route('membership.settings.import'), ['settings_file' => $upload])
            ->assertSuccessful()->assertSee('import-preview', false)->assertSee('btn-apply-import', false);
        $this->assertEquals([1.0, 2.0, 3.0, 4.0], MembershipTier::orderBy('min_purchase')->pluck('earn_percentage')->map(fn ($v) => (float) $v)->all(), 'preview must not save');

        $this->actingAs($this->admin)->post(route('membership.settings.import.apply'))->assertRedirect(route('membership.settings'));
        $this->assertEquals([1.5, 2.5, 3.5, 5.0], MembershipTier::orderBy('min_purchase')->pluck('earn_percentage')->map(fn ($v) => (float) $v)->all());

        $this->actingAs($this->admin)->get(route('membership.settings'))->assertSee('btn-export-settings', false);
    }

    public function test_a_broken_upload_shows_an_error_and_import_needs_edit_rights(): void
    {
        $bad = UploadedFile::fake()->createWithContent('settings.json', '{"hello": 1}');
        $this->actingAs($this->admin)->post(route('membership.settings.import'), ['settings_file' => $bad])->assertSessionHasErrors('settings_file');

        $viewer = $this->userWithPermissions(['loyalty_settings.index', 'loyalty_settings.edit']);
        $this->actingAs($viewer)->post(route('membership.settings.import'), ['settings_file' => $bad])->assertForbidden();
    }
}
