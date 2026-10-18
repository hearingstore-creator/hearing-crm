<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Header search (Ctrl+K), dashboard plan phase 1. */
class GlobalSearchTest extends TestCase
{
    use RefreshDatabase, CrmFixtures;

    protected int $hqPatient;
    protected int $branchPatient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
        $this->hqPatient = $this->patientWith('Rahima Begum', '01711223344', null);
        $this->branchPatient = $this->patientWith('Karim Uddin', '01899887766', $this->branch->id);
    }

    protected function patientWith(string $name, string $phone, ?int $vendorId): int
    {
        $lead = DB::table('leads')->insertGetId(['full_name' => $name, 'phone_number' => $phone, 'normalized_phone' => '88' . $phone, 'vendor_id' => $vendorId, 'created_at' => now()]);

        return DB::table('customers')->insertGetId(['customer_uid' => 'C-GS-' . $lead, 'lead_id' => $lead, 'referral_code' => 'GS' . $lead, 'created_at' => now()]);
    }

    protected function resultGroups($response): array
    {
        return collect($response->assertOk()->json('groups'))->keyBy('key')->all();
    }

    public function test_guests_cannot_search_and_short_queries_return_nothing(): void
    {
        $this->getJson('/admin/global-search?q=Rahima')->assertUnauthorized();
        $this->actingAs($this->admin)->getJson('/admin/global-search?q=R')->assertOk()->assertJson(['groups' => []]);
    }

    public function test_phone_matches_in_any_format_and_name_matches(): void
    {
        foreach (['01711223344', '+8801711223344', '8801711-223344'] as $typed) {
            $groups = $this->resultGroups($this->actingAs($this->admin)->getJson('/admin/global-search?q=' . urlencode($typed)));
            $this->assertSame('Rahima Begum', $groups['patients']['items'][0]['title'] ?? null, "phone typed as $typed");
        }
        $groups = $this->resultGroups($this->actingAs($this->admin)->getJson('/admin/global-search?q=Karim'));
        $this->assertSame('Karim Uddin', $groups['patients']['items'][0]['title'] ?? null, 'head office sees branch patients too');

        // Older record without a normalised phone is still found by phone
        $lead = DB::table('leads')->insertGetId(['full_name' => 'Old Record', 'phone_number' => '01555-123456', 'normalized_phone' => null, 'created_at' => now()]);
        DB::table('customers')->insert(['customer_uid' => 'C-OLD-1', 'lead_id' => $lead, 'referral_code' => 'OLD1', 'created_at' => now()]);
        $groups = $this->resultGroups($this->actingAs($this->admin)->getJson('/admin/global-search?q=' . urlencode('+880 1555 123456')));
        $this->assertSame('Old Record', $groups['patients']['items'][0]['title'] ?? null);
    }

    public function test_invoice_serial_job_and_actions_are_found(): void
    {
        $productId = $this->product('GS-AID');
        $this->postPurchase($this->admin, $this->supplier(), [[$productId, '', 'SN-GLOBAL-1', 1]])->assertSessionHasNoErrors();
        $this->postSale($this->admin, $this->hqPatient, [[$productId, null, ['SN-GLOBAL-1'], 1]])->assertSessionHasNoErrors();
        $invoice = DB::table('sale_masters')->orderByDesc('id')->value('sale_invoice_no');
        DB::table('repair_jobs')->insert(['job_no' => 'RJ-GS-77', 'customer_id' => $this->hqPatient, 'original_serial_no' => 'SN-GLOBAL-1', 'vendor_id' => null, 'received_by' => $this->admin->id, 'created_by' => $this->admin->id, 'created_at' => now()]);

        $this->actingAs($this->admin);
        $this->assertStringContainsString($invoice, $this->resultGroups($this->getJson('/admin/global-search?q=' . $invoice))['sales']['items'][0]['title']);
        $serial = $this->resultGroups($this->getJson('/admin/global-search?q=SN-GLOBAL-1'));
        $this->assertStringContainsString('serial=SN-GLOBAL-1', $serial['serials']['items'][0]['url']);
        $this->assertSame('Job RJ-GS-77', $serial['repairs']['items'][0]['title']);
        $this->assertSame('Job RJ-GS-77', $this->resultGroups($this->getJson('/admin/global-search?q=RJ-GS'))['repairs']['items'][0]['title']);
        $this->assertSame('New sale', $this->resultGroups($this->getJson('/admin/global-search?q=new sale'))['actions']['items'][0]['title']);
    }

    public function test_groups_follow_permissions_and_branches(): void
    {
        // Can search sales only: no patient group, no pages for menus they cannot open
        $salesOnly = $this->userWithPermissions(['sale_list.index']);
        $groups = $this->resultGroups($this->actingAs($salesOnly)->getJson('/admin/global-search?q=Rahima'));
        $this->assertArrayNotHasKey('patients', $groups);

        // Branch login: own branch's patients only
        $groups = $this->resultGroups($this->actingAs($this->branch)->getJson('/admin/global-search?q=01711223344'));
        $this->assertArrayNotHasKey('patients', $groups, 'head office patient is not visible to the branch');
        $groups = $this->resultGroups($this->actingAs($this->branch)->getJson('/admin/global-search?q=01899887766'));
        $this->assertSame('Karim Uddin', $groups['patients']['items'][0]['title'] ?? null);
    }
}
