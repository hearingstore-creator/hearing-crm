<?php

namespace Tests\Feature;

use App\Classes\Helper;
use App\Support\DataScope;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Audit 29 Oct 2026, stage 3: dashboard sales labelled as before returns (F-04), cancelled invoices are kept (F-06). */
class AuditStage3Test extends TestCase
{
    use DatabaseTransactions, CrmFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
    }

    /** One stocked sale of ৳200, returned as [sale row, batch id]. */
    private function sale(): array
    {
        $productId = $this->product('AUD-S3');
        $this->postPurchase($this->admin, $this->supplier(), [[$productId, 'LOT-S3', '', 5]])->assertSessionHasNoErrors();
        $batchId = (int) DB::table('batches')->where('product_id', $productId)->value('id');
        $this->postSale($this->admin, $this->patient(), [[$productId, $batchId, null, 1]], 50)->assertSessionHasNoErrors();

        return [DB::table('sale_masters')->orderByDesc('id')->first(), $batchId];
    }

    private function cancel(object $sale, string $reason = 'Entered by mistake')
    {
        return $this->actingAs($this->admin)->from('/admin/sale')->delete("/admin/sale/{$sale->id}", ['reason' => $reason]);
    }

    // ── F-04: labels ──

    public function test_dashboard_sales_tile_says_before_returns(): void
    {
        DataScope::reset();
        $tile = collect($this->actingAs($this->admin)->getJson('/admin/dashboard/today/tiles')->assertOk()->json('tiles'))->firstWhere('key', 'sales_today');

        $this->assertSame('Invoiced today', $tile['label']);
        $this->assertStringContainsString('before returns', $tile['sub']);
    }

    // ── F-06: cancelled invoice is kept ──

    public function test_cancelling_keeps_the_invoice_and_its_lines(): void
    {
        [$sale, $batchId] = $this->sale();

        $this->cancel($sale, 'Patient changed mind')->assertSessionHas('success');

        $row = DB::table('sale_masters')->find($sale->id);
        $this->assertNotNull($row, 'the invoice row is kept');
        $this->assertNotNull($row->deleted_at);
        $this->assertSame($this->admin->id, (int) $row->deleted_by);
        $this->assertSame('Patient changed mind', $row->cancel_reason);
        $this->assertEquals(200, (float) $row->total_amount, 'amounts are kept as they were');
        $this->assertSame(1, DB::table('sale_details')->where('sale_master_id', $sale->id)->whereNotNull('deleted_at')->count());

        // Stock and money are reversed exactly as before
        $this->assertEquals(0, (float) DB::table('batches')->where('id', $batchId)->value('sale_qty'));
        $this->assertSame(0, DB::table('stocks')->where('ref_invoice_no', $sale->sale_invoice_no)->where('type', Helper::$sales)->whereNull('deleted_at')->count());
        $this->assertEqualsWithDelta(0, $this->partyNet('customer_ledgers', $sale->sale_invoice_no), 0.01);
        $this->assertEqualsWithDelta(0, $this->companyNet($sale->sale_invoice_no), 0.01);
    }

    public function test_a_cancelled_invoice_is_left_out_of_totals(): void
    {
        [$sale] = $this->sale();
        $tile = function () {
            DataScope::reset();
            \Illuminate\Support\Facades\Cache::flush(); // tiles are cached for two minutes

            return collect($this->actingAs($this->admin)->getJson('/admin/dashboard/today/tiles')->json('tiles'))->firstWhere('key', 'sales_today');
        };
        $before = $tile();

        $this->cancel($sale)->assertSessionHas('success');

        $after = $tile();
        $this->assertEqualsWithDelta($before['value'] - 200, $after['value'], 0.01, 'the invoiced total drops by the cancelled invoice');
        $this->assertStringStartsWith(((int) $before['sub'] - 1) . ' invoice', $after['sub']);
        $this->assertNull(\App\Models\SaleMaster::find($sale->id));
        $this->assertSame(0, \App\Models\SaleDetail::where('sale_master_id', $sale->id)->count());
    }

    public function test_sales_list_shows_cancelled_invoices_only_on_request(): void
    {
        [$sale] = $this->sale();
        $this->cancel($sale)->assertSessionHas('success');

        $this->actingAs($this->admin)->get('/admin/sale'); // uses up the "Sale … cancelled" flash, which names the invoice
        $this->get('/admin/sale')->assertOk()
            ->assertDontSee($sale->sale_invoice_no)->assertSee('btn-cancelled-sales', false);
        $this->get('/admin/sale?cancelled=1')->assertOk()
            ->assertSee($sale->sale_invoice_no)->assertSee('Cancelled');
    }

    public function test_a_cancelled_invoice_can_be_viewed_and_reprinted(): void
    {
        [$sale] = $this->sale();
        $this->cancel($sale, 'Wrong patient')->assertSessionHas('success');

        $this->actingAs($this->admin)->get("/admin/sale/{$sale->id}")->assertOk()
            ->assertSee('sale-cancelled-banner', false)->assertSee('Wrong patient')->assertSee('AUD-S3')
            ->assertDontSee('btn-edit-sale', false)->assertDontSee('data-target="#collectDueModal"', false);
        $this->get("/admin/sale-pos-print/{$sale->id}")->assertOk()
            ->assertSee('pos-cancelled-stamp', false)->assertSee('AUD-S3');
    }

    public function test_a_cancelled_invoice_cannot_be_edited_paid_or_cancelled_again(): void
    {
        [$sale] = $this->sale();
        $this->cancel($sale)->assertSessionHas('success');

        $this->actingAs($this->admin)->get("/admin/sale/{$sale->id}/edit")->assertForbidden();
        $this->cancel($sale)->assertForbidden();
        $this->assertSame(1, DB::table('activity_logs')->where('action', 'sale_cancelled')->where('new_values', 'like', '%' . $sale->sale_invoice_no . '%')->count());
    }

    public function test_a_serial_from_a_cancelled_sale_can_be_sold_again(): void
    {
        $productId = $this->product('AUD-S3-SN');
        $this->postPurchase($this->admin, $this->supplier(), [[$productId, '', 'SN-S3', 1]])->assertSessionHasNoErrors();
        $customerId = $this->patient();
        $this->postSale($this->admin, $customerId, [[$productId, null, ['SN-S3'], 1]])->assertSessionHasNoErrors();
        $sale = DB::table('sale_masters')->orderByDesc('id')->first();

        $this->cancel($sale)->assertSessionHas('success');

        $this->actingAs($this->admin);
        $this->assertContains('SN-S3', array_column(Helper::getAvailableSerialNumbers($productId), 'serial_number'));
        $this->postSale($this->admin, $customerId, [[$productId, null, ['SN-S3'], 1]])->assertSessionHasNoErrors()->assertSessionHas('success');
    }
}
