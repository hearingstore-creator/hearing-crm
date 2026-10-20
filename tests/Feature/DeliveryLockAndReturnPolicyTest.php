<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Delivered is final; returns follow the window and charge from Settings, with manager exceptions. */
class DeliveryLockAndReturnPolicyTest extends TestCase
{
    use DatabaseTransactions, CrmFixtures;

    protected int $patient;
    protected int $product;
    protected int $saleId;
    protected string $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();

        $setting = Setting::first() ?? Setting::create(['company_name' => 'Test Co']);
        $setting->update(['return_window_days' => 7, 'return_charge_percent' => 10]);

        $this->product = $this->product('Aid R');
        $this->patient = $this->patient();
        $this->postPurchase($this->admin, $this->supplier(), [[$this->product, '', 'SN-R1', 1]])->assertSessionHasNoErrors();
        $this->postSale($this->admin, $this->patient, [[$this->product, null, ['SN-R1'], 1]], 200)->assertSessionHasNoErrors();
        $this->saleId = (int) DB::table('sale_masters')->orderByDesc('id')->value('id');
        $this->invoice = (string) DB::table('sale_masters')->where('id', $this->saleId)->value('sale_invoice_no');
    }

    protected function markDelivered(int $daysAgo = 0): void
    {
        DB::table('sale_masters')->where('id', $this->saleId)->update([
            'delivery_status' => 'delivered', 'delivered_at' => now()->subDays($daysAgo), 'delivered_by' => $this->admin->id,
        ]);
    }

    protected function returnPayload(float $deductionAmount, ?string $reason = 'Customer changed mind'): array
    {
        $detail = DB::table('sale_details')->where('sale_master_id', $this->saleId)->first();

        return [
            'customer_id' => $this->patient, 'date' => date('Y-m-d'), 'sale_invoice_no' => $this->invoice,
            'settlement_type' => 'store_credit', 'reason' => $reason,
            'items' => [[
                'selected' => 1, 'item_type' => 'product', 'product_id' => $this->product, 'sale_detail_id' => $detail->id,
                'return_qty' => 1, 'sold_qty' => 1, 'unit_price' => 200, 'serial_no' => 'SN-R1',
                'deduction_rate' => 0, 'deduction_amount' => $deductionAmount,
            ]],
        ];
    }

    public function test_a_delivered_invoice_cannot_be_cancelled_or_moved_back(): void
    {
        $this->markDelivered();

        foreach (['cancelled', 'ordered', 'ready_for_delivery'] as $status) {
            $this->actingAs($this->admin)->postJson("/admin/sales/{$this->saleId}/update-delivery-status", ['delivery_status' => $status])
                ->assertStatus(422);
        }

        $sale = DB::table('sale_masters')->find($this->saleId);
        $this->assertSame('delivered', $sale->delivery_status);
        $this->assertNotNull($sale->delivered_at, 'the delivery record is kept');

        // saving the same status (for example to collect a due) still works
        $this->actingAs($this->admin)->postJson("/admin/sales/{$this->saleId}/update-delivery-status", ['delivery_status' => 'delivered'])->assertSuccessful();
    }

    public function test_an_undelivered_invoice_can_still_move_forward(): void
    {
        $this->actingAs($this->admin)->postJson("/admin/sales/{$this->saleId}/update-delivery-status", ['delivery_status' => 'ready_for_delivery'])->assertSuccessful();
        $this->actingAs($this->admin)->postJson("/admin/sales/{$this->saleId}/update-delivery-status", ['delivery_status' => 'delivered'])->assertSuccessful();
        $this->assertNotNull(DB::table('sale_masters')->where('id', $this->saleId)->value('delivered_at'));
    }

    public function test_status_lists_offer_nothing_after_delivery(): void
    {
        $this->assertCount(1, \App\Enums\DeliveryStatus::casesFrom('delivered', false));
        $this->assertContains(\App\Enums\DeliveryStatus::CANCELLED, \App\Enums\DeliveryStatus::casesFrom('ordered', false));
    }

    public function test_staff_can_return_inside_the_window_with_the_charge(): void
    {
        $this->markDelivered(3);
        $staff = $this->userWithPermissions(['sale_return.create', 'sale_return.view']);

        $this->actingAs($staff)->post('/admin/sale-returns', $this->returnPayload(20))->assertSessionHas('success');
        $row = DB::table('sale_returns')->orderByDesc('id')->first();
        $this->assertEquals(180, (float) $row->credit_note_amount);
        $this->assertNull($row->policy_exception);
    }

    public function test_staff_cannot_skip_the_charge_but_a_manager_can_with_a_reason(): void
    {
        $this->markDelivered(3);
        $staff = $this->userWithPermissions(['sale_return.create']);
        $manager = $this->userWithPermissions(['sale_return.create', 'sale_return.policy_override']);

        $this->actingAs($staff)->post('/admin/sale-returns', $this->returnPayload(5))->assertSessionHas('warning');
        $this->assertSame(0, DB::table('sale_returns')->count());

        $this->actingAs($manager)->post('/admin/sale-returns', $this->returnPayload(0, ''))->assertSessionHas('warning');
        $this->assertSame(0, DB::table('sale_returns')->count(), 'a waiver needs a written reason');

        $this->actingAs($manager)->post('/admin/sale-returns', $this->returnPayload(0, 'Defective unit, charge waived'))->assertSessionHas('success');
        $row = DB::table('sale_returns')->first();
        $this->assertSame('charge_waived', $row->policy_exception);
        $this->assertEquals($manager->id, $row->approved_by);
    }

    public function test_a_return_after_the_window_needs_a_manager(): void
    {
        $this->markDelivered(20);
        $staff = $this->userWithPermissions(['sale_return.create']);
        $manager = $this->userWithPermissions(['sale_return.create', 'sale_return.policy_override']);

        $this->actingAs($staff)->post('/admin/sale-returns', $this->returnPayload(20))->assertSessionHas('warning');
        $this->assertSame(0, DB::table('sale_returns')->count());

        $this->actingAs($manager)->post('/admin/sale-returns', $this->returnPayload(20, 'Approved by owner, device faulty'))->assertSessionHas('success');
        $this->assertSame('outside_window', DB::table('sale_returns')->value('policy_exception'));
    }

    public function test_the_invoice_lookup_reports_the_policy(): void
    {
        $this->markDelivered(10);
        $res = $this->actingAs($this->admin)->getJson('/admin/sale-returns/items/by-sale?invoice_no=' . $this->invoice)->assertSuccessful()->json();

        $this->assertTrue($res['policy']['outside_window']);
        $this->assertEquals(10, $res['policy']['charge_percent']);
        $this->assertSame(7, $res['policy']['window_days']);
    }

    public function test_settings_can_change_the_window_and_charge(): void
    {
        $setting = Setting::first();
        $this->actingAs($this->admin)->put('/admin/setting/' . $setting->id, [
            'company_name' => $setting->company_name, 'mobile' => '01700000000', 'return_window_days' => 14, 'return_charge_percent' => 15,
        ])->assertSessionHasNoErrors();

        $this->assertSame(14, (int) Setting::first()->return_window_days);
        $this->assertEquals(15, (float) Setting::first()->return_charge_percent);
    }
}
