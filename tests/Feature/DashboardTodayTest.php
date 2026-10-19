<?php

namespace Tests\Feature;

use App\Support\DataScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** New Today dashboard and the legacy switch, dashboard plan phase 2. */
class DashboardTodayTest extends TestCase
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

    protected function tile(array $tiles, string $key): ?array
    {
        return collect($tiles)->firstWhere('key', $key);
    }

    /** One head office sale through the normal sale form, plus a copy of it booked to the branch for ৳5,000. */
    protected function salesAtBothPlaces(): float
    {
        $productId = $this->product('DT-AID');
        $patient = $this->patient();
        $this->postPurchase($this->admin, $this->supplier(), [[$productId, '', 'SN-DT-1', 1]])->assertSessionHasNoErrors();
        $this->postSale($this->admin, $patient, [[$productId, null, ['SN-DT-1'], 1]])->assertSessionHasNoErrors();

        $row = (array) DB::table('sale_masters')->orderByDesc('id')->first();
        unset($row['id']);
        DB::table('sale_masters')->insert(array_merge($row, ['sale_invoice_no' => $row['sale_invoice_no'] . '-BR', 'vendor_id' => $this->branch->id, 'total_amount' => 5000, 'created_by' => $this->branch->id]));

        return (float) $row['total_amount'];
    }

    public function test_legacy_stays_default_and_the_switch_opens_the_new_page(): void
    {
        $this->as($this->admin)->get('/admin/dashboard')->assertOk()->assertSee('btn-dash-switch-new', false)->assertDontSee('dashboard-today', false);
        $this->get('/admin/dashboard/legacy')->assertOk()->assertSee('btn-dash-switch-new', false);
        $this->get('/admin/dashboard/new')->assertOk()->assertSee('id="dashboard-today"', false)->assertSee('td-switch-legacy', false);

        config(['crm.dashboard' => 'new']);
        $this->get('/admin/dashboard')->assertOk()->assertSee('id="dashboard-today"', false);
    }

    public function test_endpoints_need_login_and_dashboard_access(): void
    {
        $this->get('/auto-login')->assertNotFound();
        $this->getJson('/admin/dashboard/today/tiles')->assertUnauthorized();

        $noDashboard = $this->userWithPermissions(['sale_list.index']);
        $this->as($noDashboard)->get('/admin/dashboard/new')->assertForbidden();
        $this->getJson('/admin/dashboard/today/tiles')->assertForbidden();
        $this->getJson('/admin/dashboard/today/chart/revenue_month')->assertForbidden();

        // Dashboard access without the chart permission: the chart is not served
        $basic = $this->userWithPermissions(['dashboard.index']);
        $this->as($basic)->getJson('/admin/dashboard/today/chart/revenue_month')->assertNotFound();
        $this->getJson('/admin/dashboard/today/tiles')->assertOk()->assertJson(['tiles' => []]);
        $this->getJson('/admin/dashboard/today/chart/unknown')->assertNotFound();
        $this->get('/admin/dashboard/today/block/unknown')->assertNotFound();
    }

    public function test_sales_tile_follows_the_branch_pick(): void
    {
        $hq = $this->salesAtBothPlaces();
        $this->assertGreaterThan(0, $hq);

        $value = fn (string $query = '') => $this->tile($this->getJson('/admin/dashboard/today/tiles' . $query)->assertOk()->json('tiles'), 'sales_today')['value'] ?? null;

        $this->as($this->admin);
        $this->assertEquals($hq + 5000, $value(), 'Super Admin opens on all branches');
        $this->assertEquals($hq, $value('?branch=hq'));
        $this->assertEquals($hq + 5000, $value('?branch=all'));
        $this->assertEquals(5000, $value('?branch=' . $this->branch->id));
        $this->assertEquals($hq + 5000, $value('?branch=999999'), 'unknown branch falls back to the default pick');

        // A branch login always sees its own branch, whatever it asks for
        $this->as($this->branch);
        $this->assertEquals(5000, $value());
        $this->assertEquals(5000, $value('?branch=all'));

        $tile = $this->tile($this->getJson('/admin/dashboard/today/tiles')->json('tiles'), 'sales_today');
        $this->assertCount(14, $tile['spark']);
        $this->assertSame('vs yesterday', $tile['vs']);
    }

    public function test_charts_and_blocks_load(): void
    {
        $hq = $this->salesAtBothPlaces();

        $this->as($this->admin);
        $rev = $this->getJson('/admin/dashboard/today/chart/revenue_month?branch=hq')->assertOk()->json();
        $this->assertEquals($hq, $rev['this_month'][now()->day - 1]);
        $this->assertNull($rev['this_month'][now()->day] ?? null, 'future days stay empty');

        $all = $this->getJson('/admin/dashboard/today/chart/revenue_month?branch=all')->assertOk()->json();
        $this->assertEquals($hq + 5000, $all['this_month'][now()->day - 1]);

        $cat = $this->getJson('/admin/dashboard/today/chart/sales_by_category?period=year')->assertOk()->json();
        $this->assertSame('This Year', $cat['period']);
        $this->assertGreaterThan(0, $cat['total']);

        $this->get('/admin/dashboard/today/block/my-day')->assertOk()->assertSee('to do');
        $this->get('/admin/dashboard/today/block/alerts')->assertOk()->assertSee('smart-alerts', false);
    }
    public function test_tabs_follow_permissions_and_branch_rights(): void
    {
        $this->salesAtBothPlaces();

        // Super Admin: all four tabs, each loads
        $page = $this->as($this->admin)->get('/admin/dashboard/new')->assertOk();
        foreach (['sales', 'care', 'pipeline', 'branches'] as $tab) {
            $page->assertSee('id="td-tab-' . $tab . '"', false);
            $this->get('/admin/dashboard/today/tab/' . $tab)->assertOk();
        }
        $this->get('/admin/dashboard/today/tab/nope')->assertNotFound();

        // Branch login: own tabs, never the Branches tab
        $this->as($this->branch)->get('/admin/dashboard/new')->assertOk()->assertDontSee('id="td-tab-branches"', false);
        $this->get('/admin/dashboard/today/tab/branches')->assertNotFound();

        // Only the gross profit permission: Sales & Money opens, others are hidden and refused
        $money = $this->userWithPermissions(['dashboard.index', 'dashboard.gross_profit']);
        $this->as($money)->get('/admin/dashboard/new')->assertOk()
            ->assertSee('id="td-tab-sales"', false)->assertDontSee('id="td-tab-care"', false)->assertDontSee('id="td-tab-pipeline"', false);
        $this->get('/admin/dashboard/today/tab/sales')->assertOk()->assertSee('Gross profit')->assertDontSee('Payment mix');
        $this->get('/admin/dashboard/today/tab/care')->assertNotFound();

        // No widget permissions at all: no tabs
        $none = $this->userWithPermissions(['dashboard.index']);
        $this->as($none)->get('/admin/dashboard/today/tab/sales')->assertNotFound();
    }

    public function test_sales_tab_shows_real_numbers_and_branch_pick(): void
    {
        $hq = $this->salesAtBothPlaces();

        $revenue = fn (string $q) => $this->get('/admin/dashboard/today/tab/sales?period=year' . $q)->assertOk()->getContent();
        $this->as($this->admin);

        // The payment mix doughnut carries each pick's invoice total
        $this->assertStringContainsString('&quot;data&quot;:[' . (int) $hq . ']', $revenue('&branch=hq'), 'head office only');
        $this->assertStringContainsString('&quot;data&quot;:[' . (int) ($hq + 5000) . ']', $revenue('&branch=all'));
        $this->assertStringContainsString('&quot;data&quot;:[5000]', $revenue('&branch=' . $this->branch->id));
        $this->assertStringContainsString('data-cv=', $revenue(''));
    }

    public function test_tab_results_are_cached_per_user_and_branch(): void
    {
        $this->salesAtBothPlaces();
        $this->as($this->admin)->get('/admin/dashboard/today/tab/sales?period=year')->assertOk();

        // A sale made after the first load stays out of the cached tab, a different branch pick does not
        $before = DB::table('sale_masters')->count();
        $row = (array) DB::table('sale_masters')->orderBy('id')->first();
        unset($row['id']);
        DB::table('sale_masters')->insert(array_merge($row, ['sale_invoice_no' => 'LATE-1', 'total_amount' => 777777]));
        $this->assertSame($before + 1, DB::table('sale_masters')->count());

        $this->assertStringNotContainsString('782977', $this->get('/admin/dashboard/today/tab/sales?period=year')->getContent());
        $this->assertStringContainsString('777977', $this->get('/admin/dashboard/today/tab/sales?period=year&branch=hq')->getContent());
    }
}