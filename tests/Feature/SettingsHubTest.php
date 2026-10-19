<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\LoginEvent;
use App\Services\RoleAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Administration step 4: settings hub and the activity log's sign-ins tab and CSV export. */
class SettingsHubTest extends TestCase
{
    use RefreshDatabase, CrmFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        RoleAccess::forget();
        $this->setUpCrm();
    }

    public function test_hub_shows_only_the_tiles_a_person_may_open(): void
    {
        $user = $this->userWithPermissions(['settings_hub.index', 'support_settings.index', 'users.index']);

        $this->actingAs($user)->get(route('settings.hub'))->assertSuccessful()
            ->assertSee('data-tile="support"', false)
            ->assertSee('data-tile="people"', false)
            ->assertDontSee('data-tile="pipeline"', false)
            ->assertDontSee('data-tile="database-backup"', false);
    }

    public function test_hub_needs_its_own_permission_and_super_admin_sees_everything(): void
    {
        $this->actingAs($this->userWithPermissions(['users.index']))->get(route('settings.hub'))->assertForbidden();

        $this->actingAs($this->admin)->get(route('settings.hub'))->assertSuccessful()
            ->assertSee('data-tile="pipeline"', false)->assertSee('data-tile="database-backup"', false);
    }

    public function test_branch_logins_have_no_settings_hub(): void
    {
        $this->actingAs($this->branch)->get(route('settings.hub'))->assertForbidden();
    }

    public function test_sign_ins_tab_lists_events_and_filters_them(): void
    {
        LoginEvent::record('login', 'a@test.test', null);
        LoginEvent::record('failed', 'b@test.test', null);

        $page = $this->actingAs($this->admin);
        $page->get('/admin/activity-log?tab=signins')->assertSuccessful()->assertSee('a@test.test')->assertSee('b@test.test');
        $page->get('/admin/activity-log?tab=signins&event=failed')->assertSee('b@test.test')->assertDontSee('a@test.test');
    }

    public function test_csv_export_downloads_sign_ins_and_changes_with_the_filters(): void
    {
        LoginEvent::record('login', 'a@test.test', null);
        LoginEvent::record('failed', 'b@test.test', null);
        ActivityLog::create(['user_id' => $this->admin->id, 'subject_type' => \App\Models\User::class, 'subject_id' => 1, 'action' => 'updated']);

        $signins = $this->actingAs($this->admin)->get('/admin/activity-log/export?tab=signins&event=failed');
        $signins->assertSuccessful();
        $csv = $signins->streamedContent();
        $this->assertStringContainsString('b@test.test', $csv);
        $this->assertStringNotContainsString('a@test.test', $csv);

        $changes = $this->actingAs($this->admin)->get('/admin/activity-log/export');
        $this->assertStringContainsString('updated', $changes->streamedContent());

        $this->actingAs($this->userWithPermissions(['users.index']))->get('/admin/activity-log/export')->assertForbidden();
    }
}
