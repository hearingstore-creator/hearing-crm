<?php

namespace Tests\Feature;

use App\Models\UserPreference;
use App\Support\DataScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Dashboard plan phases 4 and 5: Reports hub, start tab, report deep links, nightly snapshots. */
class ReportsHubTest extends TestCase
{
    use RefreshDatabase, CrmFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
    }

    protected function as($user)
    {
        DataScope::reset();

        return $this->actingAs($user);
    }

    public function test_hub_lists_only_reports_the_person_may_open(): void
    {
        $this->as($this->admin)->get('/admin/reports')->assertOk()
            ->assertSee('Sales Performance')->assertSee('Lead Funnel')->assertSee('Branch Monthly');

        $limited = $this->userWithPermissions(['reports_hub.index', 'sales_performance.index']);
        $this->as($limited)->get('/admin/reports')->assertOk()
            ->assertSee('Sales Performance')->assertDontSee('Lead Funnel')->assertDontSee('Branch Monthly');

        $nothing = $this->userWithPermissions(['reports_hub.index']);
        $this->as($nothing)->get('/admin/reports')->assertOk()->assertSee('No reports are switched on');

        $this->as($this->userWithPermissions(['sales_performance.index']))->get('/admin/reports')->assertForbidden();
        auth()->logout();
        $this->get('/admin/reports')->assertRedirect();
    }

    public function test_branch_login_can_open_the_hub(): void
    {
        $this->as($this->branch)->get('/admin/reports')->assertOk();
    }

    public function test_stars_toggle_are_capped_and_limited_to_allowed_reports(): void
    {
        $user = $this->userWithPermissions(['reports_hub.index', 'sales_performance.index', 'lead_funnel.index']);
        $this->as($user);

        $this->postJson('/admin/reports/star', ['url' => 'admin/report/sales-performance'])->assertOk()->assertJson(['stars' => ['admin/report/sales-performance']]);
        $this->get('/admin/reports')->assertSee('id="rh-starred-grid"', false)->assertSee('data-url="admin/report/sales-performance"', false);
        $this->postJson('/admin/reports/star', ['url' => 'admin/report/sales-performance'])->assertOk()->assertJson(['stars' => []]);

        // A report the person cannot open, or one that does not exist, cannot be starred
        $this->postJson('/admin/reports/star', ['url' => 'admin/report/vendor-monthly'])->assertStatus(422);
        $this->postJson('/admin/reports/star', ['url' => 'admin/anything'])->assertStatus(422);

        // The cap is six
        $this->as($this->admin);
        $urls = ['admin/report/sales-performance', 'admin/report/lead-funnel', 'admin/report/ledger', 'admin/report/stock', 'admin/report/vendor-monthly', 'admin/report/advances', 'admin/report/purchase-summary'];
        foreach (array_slice($urls, 0, 6) as $u) {
            $this->postJson('/admin/reports/star', ['url' => $u])->assertOk();
        }
        $this->postJson('/admin/reports/star', ['url' => $urls[6]])->assertStatus(422);
        $this->assertCount(6, UserPreference::read($this->admin->id, 'starred_reports'));
    }

    public function test_dashboard_report_links_carry_period_and_branch(): void
    {
        $this->as($this->admin);
        $html = $this->get('/admin/dashboard/today/tab/sales?period=month&branch=' . $this->branch->id)->assertOk()->getContent();
        $this->assertStringContainsString('sales-performance?start_date=' . now()->startOfMonth()->toDateString() . '&amp;end_date=' . now()->toDateString() . '&amp;vendor_id=' . $this->branch->id, $html);
        $this->assertStringContainsString('report/ledger?start_date=' . now()->startOfMonth()->toDateString(), $html);
        $this->assertStringNotContainsString('report/ledger?start_date=' . now()->startOfMonth()->toDateString() . '&amp;end_date=' . now()->toDateString() . '&amp;vendor_id', $html, 'ledger takes no branch');

        $all = $this->get('/admin/dashboard/today/tab/sales?period=all')->assertOk()->getContent();
        $this->assertStringNotContainsString('start_date', $all, 'All Time sends no dates');
    }

    public function test_start_tab_is_pinned_per_user_with_role_defaults(): void
    {
        // Default: Super Admin opens on Today; a money-only role opens on Sales & Money
        $this->as($this->admin)->get('/admin/dashboard/new')->assertOk()->assertSee('data-start="today"', false);
        $accounts = $this->userWithPermissions(['dashboard.index', 'dashboard.gross_profit', 'dashboard.total_sales']);
        $this->as($accounts)->get('/admin/dashboard/new')->assertOk()->assertSee('data-start="sales"', false);

        // Pinning wins, and only for tabs the person can open
        $this->postJson('/admin/dashboard/today/pin', ['tab' => 'today'])->assertOk();
        $this->get('/admin/dashboard/new')->assertSee('data-start="today"', false);
        $this->postJson('/admin/dashboard/today/pin', ['tab' => 'branches'])->assertStatus(422);

        $this->as($this->admin)->postJson('/admin/dashboard/today/pin', ['tab' => 'pipeline'])->assertOk();
        $this->get('/admin/dashboard/new')->assertSee('data-start="pipeline"', false);

        // Front-desk style roles (no money widgets) see My Day before the tiles
        $desk = $this->userWithPermissions(['dashboard.index', 'dashboard.follow_ups']);
        $this->as($desk)->get('/admin/dashboard/new')->assertSee('td-today td-myday-first', false);
        $this->as($this->admin)->get('/admin/dashboard/new')->assertDontSee('td-today td-myday-first', false);
    }

    public function test_heavy_widgets_are_snapshotted_and_shared_between_equal_views(): void
    {
        Cache::flush();
        $this->as($this->admin);
        $this->artisan('crm:dashboard-snapshots')->assertSuccessful();
        $this->assertTrue(Cache::has('dash-today:snap:customer_rfm:all:0:all:' . today()->toDateString()));

        // A second Super Admin-style view reads the same snapshot instead of recomputing
        $second = $this->userWithPermissions(['dashboard.index', 'dashboard.customer_rfm', 'dashboard.view_all_data']);
        $this->as($second)->get('/admin/dashboard/today/tab/care?branch=all')->assertOk();
        $this->assertTrue(Cache::has('dash-today:snap:customer_rfm:all:0:all:' . today()->toDateString()));
    }
}
