<?php

namespace Tests\Feature;

use App\Models\ProfitBudget;
use App\Services\ProfitStatement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Report plan phase 4: 12-month trend, the records behind a line, and monthly budgets against actuals. */
class ProfitStatementExtrasTest extends TestCase
{
    use RefreshDatabase, CrmFixtures;

    protected int $saleId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();

        $categoryId = DB::table('categories')->insertGetId(['category_name' => 'Hearing aids', 'status' => 1, 'created_at' => now()]);
        $product = $this->product('Phoenix');
        DB::table('products')->where('id', $product)->update(['category_id' => $categoryId]);
        $this->postPurchase($this->admin, $this->supplier(), [[$product, '', 'SN-X-1,SN-X-2', 2]])->assertSessionHasNoErrors();
        $this->postSale($this->admin, $this->patient(), [[$product, null, ['SN-X-1'], 1]])->assertSessionHasNoErrors();
        $this->saleId = (int) DB::table('sale_masters')->orderByDesc('id')->value('id');
        DB::table('sale_details')->where('sale_master_id', $this->saleId)->update(['mrp_price' => 200, 'purchase_price' => 120, 'discount' => 0]);
        DB::table('sale_masters')->where('id', $this->saleId)->update(['total_amount' => 200, 'vat' => 0, 'total_discount' => 0, 'reference_amount' => 0]);

        $rent = DB::table('income_expense_heads')->insertGetId(['head_type' => 'Expense', 'head_name' => 'Rent', 'status' => 1, 'created_at' => now()]);
        DB::table('ledgers')->insert([
            'invoice_no' => uniqid('T'), 'date' => now()->toDateString(), 'amount_type' => 'Expense', 'type' => null, 'details' => 'October rent',
            'expense_amount' => 50, 'income_amount' => 0, 'income_expense_head_id' => $rent, 'created_at' => now(),
        ]);
    }

    protected function month(): array
    {
        return ['period' => 'custom', 'start_date' => now()->startOfMonth()->toDateString(), 'end_date' => now()->endOfMonth()->toDateString()];
    }

    public function test_trend_has_twelve_months_with_this_months_figures_last(): void
    {
        $this->actingAs($this->admin);
        $trend = ProfitStatement::trend(now(), 'all');

        $this->assertCount(12, $trend);
        $this->assertSame(now()->format('Y-m'), end($trend)['key']);
        $this->assertEquals(200, end($trend)['summary']['net_sales']);
        $this->assertEquals(80 - 50, end($trend)['summary']['net_profit']);
        $this->assertEquals(0, $trend[0]['summary']['net_sales']);

        $this->actingAs($this->admin)->get('/admin/report/profit-statement?view=trend')->assertSuccessful()
            ->assertSee('trend-table', false)->assertSee('trendChart', false);
    }

    public function test_drill_down_lists_the_invoice_lines_and_ledger_entries_behind_a_line(): void
    {
        $product = $this->actingAs($this->admin)->get('/admin/report/profit-statement/detail?' . http_build_query($this->month() + ['kind' => 'product', 'label' => 'Hearing aids']));
        $product->assertSuccessful()->assertSee('detail-table', false)->assertSee('Phoenix')->assertSee('200.00')->assertSee('120.00');

        $expense = $this->actingAs($this->admin)->get('/admin/report/profit-statement/detail?' . http_build_query($this->month() + ['kind' => 'expense', 'label' => 'Rent']));
        $expense->assertSuccessful()->assertSee('October rent')->assertSee('50.00');

        $this->actingAs($this->admin)->get('/admin/report/profit-statement/detail?kind=bogus&label=x')->assertNotFound();
    }

    public function test_statement_lines_link_to_their_detail_page(): void
    {
        $this->actingAs($this->admin)->get('/admin/report/profit-statement?' . http_build_query($this->month()))
            ->assertSee('profit-statement/detail?kind=product', false)->assertSee('kind=expense', false);
    }

    public function test_budget_is_saved_and_compared_with_actuals_for_a_whole_month(): void
    {
        $month = now()->format('Y-m');
        $this->actingAs($this->admin)->post(route('report.profit-budget.save'), [
            'month' => $month, 'vendor_id' => 'all', 'amounts' => ['net_sales' => 400, 'operating_expenses' => 40, 'gross_profit' => '', 'net_profit' => 100],
        ])->assertSessionHasNoErrors();

        $this->assertSame(3, ProfitBudget::where('month', $month)->count());

        $page = $this->actingAs($this->admin)->get('/admin/report/profit-statement?' . http_build_query($this->month()));
        $page->assertSee('id="ps-budget"', false)->assertSee('data-metric="net_sales"', false)->assertSee('50%', false);

        // a part-month range has no budget comparison
        $this->actingAs($this->admin)->get('/admin/report/profit-statement?period=custom&start_date=' . now()->startOfMonth()->toDateString() . '&end_date=' . now()->startOfMonth()->addDays(2)->toDateString())
            ->assertDontSee('id="ps-budget"', false);

        // clearing a line removes it
        $this->actingAs($this->admin)->post(route('report.profit-budget.save'), ['month' => $month, 'vendor_id' => 'all', 'amounts' => ['net_sales' => '']]);
        $this->assertSame(0, ProfitBudget::where('month', $month)->count());
    }

    public function test_budget_needs_its_own_permission_and_branch_staff_are_held_to_their_branch(): void
    {
        $viewer = $this->userWithPermissions(['profit_statement.index']);
        $this->actingAs($viewer)->get(route('report.profit-budget'))->assertForbidden();
        $this->actingAs($viewer)->post(route('report.profit-budget.save'), ['month' => now()->format('Y-m'), 'amounts' => ['net_sales' => 1]])->assertForbidden();

        $manager = $this->userWithPermissions(['profit_statement.index', 'profit_statement.budget']);
        $manager->update(['vendor_id' => $this->branch->id]);
        $this->actingAs($manager)->post(route('report.profit-budget.save'), ['month' => now()->format('Y-m'), 'vendor_id' => 'all', 'amounts' => ['net_sales' => 900]]);

        $this->assertDatabaseHas('profit_budgets', ['branch_key' => (string) $this->branch->id, 'metric' => 'net_sales', 'amount' => 900]);
        $this->assertDatabaseMissing('profit_budgets', ['branch_key' => 'all']);
    }
}
