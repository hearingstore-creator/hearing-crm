<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Audit 29 Oct 2026, stage 1: confirming a debit note needs purchase_return.edit (F-01); date filters include the end day (F-03). */
class AuditStage1Test extends TestCase
{
    use DatabaseTransactions, CrmFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
    }

    // ── F-01 purchase return confirm ──

    private function pendingReturn(): int
    {
        return DB::table('purchase_returns')->insertGetId([
            'return_no' => 'PR-T-' . uniqid(), 'supplier_id' => $this->supplier(), 'vendor_id' => null,
            'date' => now()->toDateString(), 'total_amount' => 1000, 'status' => 'pending', 'created_at' => now(),
        ]);
    }

    private function confirmPayload(): array
    {
        return ['supplier_doc_no' => 'DN-1', 'supplier_doc_date' => now()->toDateString(), 'accepted_amount' => 800];
    }

    public function test_confirming_a_debit_note_needs_the_edit_permission(): void
    {
        $id = $this->pendingReturn();
        $viewer = $this->userWithPermissions(['purchase_return.index', 'purchase_return.view']);

        $this->actingAs($viewer)->post("/admin/purchase-returns/{$id}/confirm", $this->confirmPayload())->assertForbidden();

        $this->assertSame('pending', DB::table('purchase_returns')->where('id', $id)->value('status'));
        $this->assertSame(0, DB::table('supplier_ledgers')->where('ref_invoice_no', DB::table('purchase_returns')->where('id', $id)->value('return_no'))->count());
    }

    public function test_a_user_with_the_edit_permission_still_confirms(): void
    {
        $id = $this->pendingReturn();
        $editor = $this->userWithPermissions(['purchase_return.index', 'purchase_return.view', 'purchase_return.edit']);

        $this->actingAs($editor)->post("/admin/purchase-returns/{$id}/confirm", $this->confirmPayload())->assertRedirect();

        $return = DB::table('purchase_returns')->where('id', $id)->first();
        $this->assertSame('confirmed', $return->status);
        $this->assertEquals(800, (float) DB::table('supplier_ledgers')->where('ref_invoice_no', $return->return_no)->sum('debit'));
    }

    // ── F-03 end day included ──

    public function test_lead_funnel_counts_leads_created_later_on_the_end_date(): void
    {
        DB::table('leads')->insert([
            'full_name' => 'Afternoon Lead', 'phone_number' => '017' . random_int(10000000, 99999999), 'created_at' => '2001-02-03 15:30:00',
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/report/lead-funnel?start_date=2001-02-03&end_date=2001-02-03');

        $response->assertOk();
        $this->assertSame(1, (int) $response->viewData('totalLeads'));
    }

    public function test_referrals_list_includes_the_end_date(): void
    {
        DB::table('referrals')->insert([
            'referrer_id' => $this->patient(), 'referred_customer_id' => $this->patient(), 'referral_code_used' => 'TST',
            'status' => 'pending', 'created_at' => '2001-02-03 18:00:00',
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/membership/referrals?start_date=2001-02-03&end_date=2001-02-03');

        $response->assertOk();
        $this->assertSame(1, $response->viewData('referrals')->total());
    }
}
