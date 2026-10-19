<?php

namespace Tests\Feature;

use App\Services\ProfitStatement;
use App\Support\ReportPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Report plan phase 1: the profit statement counts each thing once and splits it by category and branch. */
class ProfitStatementTest extends TestCase
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
        $this->postPurchase($this->admin, $this->supplier(), [[$product, '', 'SN-PS-1,SN-PS-2', 2]])->assertSessionHasNoErrors();
        $this->postSale($this->admin, $this->patient(), [[$product, null, ['SN-PS-1'], 1]])->assertSessionHasNoErrors();

        // Known numbers: sold for 200, bought at 120, 10 owed to a referring doctor
        $this->saleId = (int) DB::table('sale_masters')->orderByDesc('id')->value('id');
        DB::table('sale_details')->where('sale_master_id', $this->saleId)->update(['mrp_price' => 200, 'purchase_price' => 120, 'discount' => 0]);
        DB::table('sale_masters')->where('id', $this->saleId)->update(['reference_amount' => 10, 'total_amount' => 200, 'vat' => 0, 'total_discount' => 0]);

        $rent = DB::table('income_expense_heads')->insertGetId(['head_type' => 'Expense', 'head_name' => 'Rent', 'status' => 1, 'created_at' => now()]);
        $interest = DB::table('income_expense_heads')->insertGetId(['head_type' => 'Income', 'head_name' => 'Interest', 'status' => 1, 'created_at' => now()]);
        $this->ledger('Expense', 50, null, $rent);                       // manual expense: counts
        $this->ledger('Expense', 999, 'Supplier Payment');                // cash side of purchases: left out
        $this->ledger('Expense', 40, 'Doctor Payment');                   // cash side of referral: left out
        $this->ledger('Income', 200, 'Sales');                            // receipt of the sale: left out
        $this->ledger('Income', 25, null, $interest);                     // manual other income: counts
    }

    protected function ledger(string $side, float $amount, ?string $type, ?int $head = null, ?int $vendor = null): void
    {
        DB::table('ledgers')->insert([
            'invoice_no' => uniqid('T'), 'date' => now()->toDateString(), 'amount_type' => $side, 'type' => $type,
            'expense_amount' => $side === 'Expense' ? $amount : 0, 'income_amount' => $side === 'Income' ? $amount : 0,
            'income_expense_head_id' => $head, 'vendor_id' => $vendor, 'created_at' => now(),
        ]);
    }

    protected function statement(string $branch = 'all'): array
    {
        $this->actingAs($this->admin);

        return (new ProfitStatement(now()->startOfMonth(), now()->endOfMonth(), $branch))->build();
    }

    protected function row(array $statement, string $label): array
    {
        return collect($statement['rows'])->firstWhere('label', $label) ?? $this->fail("No row '$label'");
    }

    public function test_profit_is_sales_less_cost_referral_and_expenses_plus_other_income_without_double_counting(): void
    {
        $s = $this->statement();

        $this->assertEquals(200, $this->row($s, 'Hearing aids')['total']);
        $this->assertEquals(200, $this->row($s, 'Net sales')['total']);
        $this->assertEquals(120, $this->row($s, 'Total cost of goods')['total']);
        $this->assertEquals(80, $this->row($s, 'Gross profit')['total']);
        $this->assertEquals(10, $this->row($s, 'Referral commission')['total']);
        $this->assertEquals(50, $this->row($s, 'Rent')['total']);
        $this->assertEquals(25, $this->row($s, 'Interest')['total']);
        // 80 - 10 - 50 + 25: supplier payment (999), doctor payment (40) and the sale receipt (200) are not counted again
        $this->assertEquals(45, $this->row($s, 'Net profit')['total']);
        $this->assertEquals(45, $s['summary']['net_profit']);
    }

    public function test_line_discounts_are_percentages(): void
    {
        DB::table('sale_details')->where('sale_master_id', $this->saleId)->update(['discount' => 10]);
        DB::table('sale_masters')->where('id', $this->saleId)->update(['total_amount' => 180]);

        $s = $this->statement();
        $this->assertEquals(180, $this->row($s, 'Hearing aids')['total']);
        $this->assertEquals(180, $this->row($s, 'Net sales')['total']);
    }

    public function test_cash_movements_left_out_are_listed_and_commission_paid_is_a_memo(): void
    {
        $s = $this->statement();

        $left = collect($s['checks']['excluded'])->pluck('amount', 'what');
        $this->assertEquals(999, $left['Supplier Payment']);
        $this->assertEquals(40, $left['Doctor Payment']);
        $this->assertEquals(200, $left['Sales']);
        $this->assertEquals(40, $s['memo']['commission_paid_in_period']);
    }

    public function test_a_reversed_expense_nets_out(): void
    {
        $this->ledger('Expense', 30, 'Expense (Reversed)');
        $this->ledger('Expense', -30, 'Expense Reversal');

        $this->assertEquals(50, $this->row($this->statement(), 'Rent')['total'] + 0);
        $this->assertEquals(45, $this->row($this->statement(), 'Net profit')['total']);
    }

    public function test_returns_reduce_sales_cost_and_referral(): void
    {
        $detail = DB::table('sale_details')->where('sale_master_id', $this->saleId)->first();
        $returnId = DB::table('sale_returns')->insertGetId([
            'credit_note_no' => 'CN-T1', 'sale_master_id' => $this->saleId, 'sale_invoice_no' => 'X', 'customer_id' => DB::table('sale_masters')->where('id', $this->saleId)->value('customer_id'),
            'date' => now()->toDateString(), 'return_subtotal' => 200, 'credit_note_amount' => 200, 'status' => 'completed',
            'doctor_commission_adjusted' => 10, 'created_at' => now(),
        ]);
        DB::table('sale_return_details')->insert([
            'sale_return_id' => $returnId, 'sale_detail_id' => $detail->id, 'item_type' => 'product', 'product_id' => $detail->product_id,
            'sold_qty' => 1, 'return_qty' => 1, 'unit_price' => 200, 'net_return_amount' => 200, 'created_at' => now(),
        ]);

        $s = $this->statement();
        $this->assertEquals(0, $this->row($s, 'Net sales')['total']);
        $this->assertEquals(0, $this->row($s, 'Total cost of goods')['total']);
        $this->assertEquals(0, $this->row($s, 'Referral commission')['total']);
        $this->assertEquals(-25, $this->row($s, 'Net profit')['total']);   // only rent 50 and other income 25 remain
    }

    public function test_each_branch_gets_its_own_column_and_head_office_does_not_see_branch_ledger_in_its_column(): void
    {
        $this->ledger('Expense', 70, null, DB::table('income_expense_heads')->where('head_name', 'Rent')->value('id'), $this->branch->id);

        $s = $this->statement();
        $rent = $this->row($s, 'Rent');
        $this->assertEquals(50, $rent['vals']['hq']);
        $this->assertEquals(70, $rent['vals'][(string) $this->branch->id]);
        $this->assertEquals(120, $rent['total']);

        $hqOnly = $this->statement('hq');
        $this->assertSame(['hq'], array_keys($hqOnly['columns']));
        $this->assertEquals(50, $this->row($hqOnly, 'Rent')['total']);
    }

    public function test_page_export_and_permission(): void
    {
        $this->actingAs($this->admin)->get('/admin/report/profit-statement')->assertSuccessful()
            ->assertSee('profit-table', false)->assertSee('Hearing aids')->assertSee('Net profit');

        $csv = $this->actingAs($this->admin)->get('/admin/report/profit-statement?export=csv');
        $this->assertStringContainsString('Net profit', $csv->streamedContent());

        $this->actingAs($this->userWithPermissions(['reports_hub.index']))->get('/admin/report/profit-statement')->assertForbidden();
    }

    public function test_branch_staff_only_see_their_own_branch(): void
    {
        $this->ledger('Expense', 70, null, DB::table('income_expense_heads')->where('head_name', 'Rent')->value('id'), $this->branch->id);
        $staff = $this->userWithPermissions(['profit_statement.index']);
        $staff->update(['vendor_id' => $this->branch->id]);

        $this->actingAs($staff)->get('/admin/report/profit-statement?vendor_id=hq')->assertSuccessful()
            ->assertSee('Test Branch')->assertDontSee('Head Office');
    }

    public function test_period_helper_understands_presets_and_custom_ranges(): void
    {
        [$s, $e, $key] = ReportPeriod::resolve(Request::create('/', 'GET', ['period' => 'last_month']));
        $this->assertSame('last_month', $key);
        $this->assertSame(now()->subMonthNoOverflow()->startOfMonth()->toDateString(), $s->toDateString());

        [$s, $e, $key] = ReportPeriod::resolve(Request::create('/', 'GET', ['start_date' => '2026-01-05', 'end_date' => '2026-01-20']));
        $this->assertSame('custom', $key);
        $this->assertSame('2026-01-20', $e->toDateString());

        [, , $key] = ReportPeriod::resolve(Request::create('/', 'GET', ['period' => 'nonsense']));
        $this->assertSame('this_month', $key);
    }
}
