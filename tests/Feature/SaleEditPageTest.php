<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** The Edit Sale screen keeps every field, class and id its script and the update route rely on. */
class SaleEditPageTest extends TestCase
{
    use RefreshDatabase, CrmFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
    }

    private function makeSale(): object
    {
        $productId = $this->product('EDIT');
        $this->postPurchase($this->admin, $this->supplier(), [[$productId, 'LOT-E', '', 10]])->assertSessionHasNoErrors();
        $batchId = (int) DB::table('batches')->where('product_id', $productId)->value('id');
        $customerId = $this->patient();
        $this->postSale($this->admin, $customerId, [[$productId, $batchId, null, 2]], 0, ['total_amount' => 2000])->assertSessionHasNoErrors();

        return DB::table('sale_masters')->orderByDesc('id')->first();
    }

    public function test_the_edit_page_opens_with_the_saved_values_and_the_fields_the_script_needs(): void
    {
        $sale = $this->makeSale();

        $page = $this->actingAs($this->admin)->get("/admin/sale/{$sale->id}/edit")->assertOk();

        $page->assertSee('Edit Sale')
            ->assertSee('action="' . url("admin/sale/{$sale->id}") . '"', false)
            ->assertSee('name="_method" value="put"', false)
            ->assertSee('data-sale-add-row=', false);

        // Names the update route reads
        foreach (['customer_id', 'date', 'payment_type_id', 'reference_by', 'details', 'vat', 'invoice_discount', 'total_discount', 'total_amount', 'paid', 'due_amount', 'sub_total', 'delivery_status', 'delivery_date_time', 'redeem_points', 'primary_reference_amount', 'reference_amount'] as $name) {
            $page->assertSee('name="' . $name . '"', false);
        }
        $page->assertSee('name="old[0][id]"', false)->assertSee('name="old[0][qty]"', false)->assertSee('name="old[0][unit_price]"', false);

        // Classes and ids sales.js uses
        foreach (['neworderbody', 'grand-total', 'paid-amount', 'due-amount', 'sub-total', 'invoice-discount', 'total-discount', 'wrap-due-date', 'secondary-referrer-box', 'doctor-chamber-wrap'] as $hook) {
            $page->assertSee($hook, false);
        }

        // Saved money values are shown as saved
        $page->assertSee('value="' . $sale->total_amount . '"', false);
    }

    public function test_the_page_needs_a_login_and_the_update_route_still_saves(): void
    {
        $sale = $this->makeSale();

        $detail = DB::table('sale_details')->where('sale_master_id', $sale->id)->first();
        $this->actingAs($this->admin)->from("/admin/sale/{$sale->id}/edit")->put("/admin/sale/{$sale->id}", [
            'customer_id' => $sale->customer_id, 'payment_type_id' => 1, 'date' => date('Y-m-d'), 'details' => 'Edited note',
            'total_amount' => 2000, 'paid' => 0, 'vat' => 0, 'total_discount' => 0, 'redeem_points' => 0,
            'old' => [['id' => $detail->id, 'product_id' => $detail->product_id, 'qty' => 2, 'purchase_price' => 100, 'unit_price' => 1000, 'discount' => 0]],
            'old_batch_formatted' => json_encode([$detail->batch_id]), 'old_serial_formatted' => json_encode([null]),
            'batch_id_formatted' => '[]', 'serial_no_formatted' => '[]',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Edited note', DB::table('sale_masters')->where('id', $sale->id)->value('details'));
    }
}
