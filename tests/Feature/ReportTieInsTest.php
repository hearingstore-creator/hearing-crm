<?php

namespace Tests\Feature;

use App\Services\DashboardAnalyticsService;
use App\Support\DataScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Report plan phase 3: Branch Monthly is activity only; the dashboard counts expenses the way the statement does. */
class ReportTieInsTest extends TestCase
{
    use RefreshDatabase, CrmFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
    }

    protected function ledger(string $type = null, float $amount = 0): void
    {
        DB::table('ledgers')->insert([
            'invoice_no' => uniqid('T'), 'date' => now()->toDateString(), 'amount_type' => 'Expense', 'type' => $type,
            'expense_amount' => $amount, 'income_amount' => 0, 'created_at' => now(),
        ]);
    }

    public function test_branch_monthly_no_longer_shows_profit_columns_but_links_to_the_statement(): void
    {
        $page = $this->actingAs($this->admin)->get('/admin/report/vendor-monthly');

        $page->assertSuccessful()
            ->assertDontSee('Est. COGS')->assertDontSee('Profitability')->assertDontSee('Gross Profit')
            ->assertSee('tbl-vendor-monthly', false)
            ->assertSee('link-profit', false);
    }

    public function test_branch_monthly_hides_the_profit_link_without_the_permission_and_exports_activity_only(): void
    {
        $viewer = $this->userWithPermissions(['vendor_monthly_report.index']);
        $this->actingAs($viewer)->get('/admin/report/vendor-monthly')->assertSuccessful()->assertDontSee('link-profit', false);

        $csv = $this->actingAs($this->admin)->get('/admin/report/vendor-monthly?export=csv')->streamedContent();
        $this->assertStringContainsString('Purchases', $csv);
        $this->assertStringNotContainsString('Gross Profit', $csv);
        $this->assertStringNotContainsString('Net (GP', $csv);
    }

    public function test_dashboard_expenses_skip_supplier_and_doctor_payments(): void
    {
        $this->ledger(null, 50);                 // a manual expense
        $this->ledger('Supplier Payment', 999);  // cash side of purchases
        $this->ledger('Doctor Payment', 40);     // cash side of referral
        $this->ledger('Expense (Reversed)', 30);
        $this->ledger('Expense Reversal', -30);

        DataScope::reset();
        $this->actingAs($this->admin);
        $widget = app(DashboardAnalyticsService::class)->pick(['expense_revenue'])['expense_revenue'];

        $this->assertEquals(50, $widget['expenses']);
    }

    public function test_sales_tab_links_to_the_statement_for_people_who_may_open_it(): void
    {
        DataScope::reset();
        $this->actingAs($this->admin)->get('/admin/dashboard/today/tab/sales')->assertSuccessful()->assertSee('link-profit-statement', false);
    }
}
