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
        $this->assertEquals($hq, $value(), 'head office is the default pick');
        $this->assertEquals($hq + 5000, $value('?branch=all'));
        $this->assertEquals(5000, $value('?branch=' . $this->branch->id));
        $this->assertEquals($hq, $value('?branch=999999'), 'unknown branch falls back to head office');

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
        $rev = $this->getJson('/admin/dashboard/today/chart/revenue_month')->assertOk()->json();
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
}
