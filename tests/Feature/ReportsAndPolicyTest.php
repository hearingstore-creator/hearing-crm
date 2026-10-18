<?php

namespace Tests\Feature;

use App\Jobs\SendSmsCampaign;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Reports per branch (Batch 9) and the smaller policy fixes (Batch 10) from the Part 2 audit. */
class ReportsAndPolicyTest extends TestCase
{
    use RefreshDatabase, CrmFixtures;

    protected User $branchStaff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
        // A staff account whose home branch is the test branch
        $this->branchStaff = $this->userWithPermissions(['ledger.index', 'sales_performance.index', 'ticket_list.index', 'add_ticket.create']);
        $this->branchStaff->forceFill(['vendor_id' => $this->branch->id])->save();
    }

    protected function ledgerRow(?int $vendorId, float $amount, string $details): void
    {
        DB::table('ledgers')->insert(['vendor_id' => $vendorId, 'invoice_no' => uniqid('L'), 'income_amount' => $amount, 'expense_amount' => 0, 'amount_type' => 'Income', 'date' => date('Y-m-d'), 'details' => $details, 'created_at' => now()]);
    }

    public function test_ledger_shows_all_branches_to_head_office_and_only_own_branch_to_branch_staff(): void
    {
        $this->ledgerRow(null, 100, 'HQ income');
        $this->ledgerRow($this->branch->id, 50, 'Branch income');
        $details = fn ($response) => collect($response->viewData('incomes'))->pluck('details')->sort()->values()->all();

        $all = $this->actingAs($this->admin)->post('/admin/report/ledger', ['type' => 'Income', 'branch' => 'all']);
        $this->assertSame(['Branch income', 'HQ income'], $details($all));

        $oneBranch = $this->actingAs($this->admin)->post('/admin/report/ledger', ['type' => 'Income', 'branch' => (string) $this->branch->id]);
        $this->assertSame(['Branch income'], $details($oneBranch));

        // The staff user asks for everything / head office, but stays in their branch
        $staff = $this->actingAs($this->branchStaff)->post('/admin/report/ledger', ['type' => 'Income', 'branch' => 'all', 'vendor_id' => 'hq']);
        $this->assertSame(['Branch income'], $details($staff));
    }

    public function test_branch_staff_report_filter_is_forced_to_their_branch(): void
    {
        $this->actingAs($this->branchStaff)->get('/admin/report/sales-performance?vendor_id=hq')
            ->assertOk()
            ->assertViewHas('vendor_id', (string) $this->branch->id);
    }

    public function test_sms_campaign_cap_and_quiet_hours(): void
    {
        Queue::fake();
        config(['crm.sms_campaign_admin_threshold' => 1]);
        $leadIds = [
            DB::table('leads')->insertGetId(['full_name' => 'A', 'phone_number' => '01711111111', 'created_at' => now()]),
            DB::table('leads')->insertGetId(['full_name' => 'B', 'phone_number' => '01722222222', 'created_at' => now()]),
        ];
        $send = fn (User $user) => $this->actingAs($user)->from('/admin/sms')->post('/admin/sms/send', [
            'campaign_name' => 'Test', 'message_body' => 'Hello', 'audience_type' => 'lead_ids',
            'filter_criteria' => json_encode(['lead_ids' => $leadIds]),
        ]);

        $sender = $this->userWithPermissions(['compose_&_send.index', 'compose_&_send.create']);
        $send($sender)->assertSessionHas('warning', fn ($w) => str_contains($w, 'Super Admin'));
        Queue::assertNothingPushed();

        Carbon::setTestNow(Carbon::parse('2026-10-18 22:30', 'Asia/Dhaka'));
        $send($this->admin)->assertSessionHas('success', fn ($s) => str_contains($s, 'quiet hours'));
        Queue::assertPushed(SendSmsCampaign::class, fn ($job) => $job->delay !== null);
        Carbon::setTestNow();
    }

    public function test_sms_centre_is_head_office_only(): void
    {
        $smsPermissions = ['compose_&_send.index', 'compose_&_send.create', 'templates.index', 'sms_automation.index'];

        $branchSender = $this->userWithPermissions($smsPermissions);
        $branchSender->forceFill(['vendor_id' => $this->branch->id])->save();
        foreach (['/admin/sms', '/admin/sms-templates', '/admin/sms-automations', '/admin/sms-log'] as $uri) {
            $this->actingAs($branchSender)->get($uri)->assertForbidden();
        }
        $this->assertNotContains('SMS Centre', $branchSender->fresh()->accessibleMenus()->pluck('name')->all());

        $hqSender = $this->userWithPermissions($smsPermissions);
        $this->actingAs($hqSender)->get('/admin/sms-templates')->assertOk();
        $this->assertContains('SMS Centre', $hqSender->fresh()->accessibleMenus()->pluck('name')->all());
    }

    public function test_points_follow_sale_edits_and_removed_redemptions_come_back(): void
    {
        DB::table('membership_tiers')->insert(['name' => 'Silver', 'min_purchase' => 0, 'max_purchase' => 1000000, 'earn_percentage' => 1, 'referral_reward_points' => 0, 'created_at' => now()]);
        $productId = $this->product('BAT');
        $this->postPurchase($this->admin, $this->supplier(), [[$productId, 'LOT-P', '', 10]])->assertSessionHasNoErrors();
        $batchId = (int) DB::table('batches')->where('product_id', $productId)->value('id');
        $customerId = $this->patient();
        DB::table('reward_ledgers')->insert(['customer_id' => $customerId, 'type' => 'earn', 'points' => 100, 'expiry_date' => now()->addYear(), 'created_at' => now()]);

        $this->postSale($this->admin, $customerId, [[$productId, $batchId, null, 1]], 0, ['total_amount' => 1000, 'redeem_points' => 50])->assertSessionHasNoErrors();
        $sale = DB::table('sale_masters')->orderByDesc('id')->first();
        $earned = fn () => (int) DB::table('reward_ledgers')->where('sale_master_id', $sale->id)->where('type', 'earn')->whereNull('deleted_at')->value('points');
        $redeemed = fn () => DB::table('reward_ledgers')->where('sale_master_id', $sale->id)->where('type', 'redeem')->whereNull('deleted_at')->count();
        $this->assertSame(10, $earned());
        $this->assertSame(1, $redeemed());

        $detail = DB::table('sale_details')->where('sale_master_id', $sale->id)->first();
        $this->actingAs($this->admin)->from('/admin/sale')->put("/admin/sale/{$sale->id}", [
            'customer_id' => $customerId, 'payment_type_id' => 1, 'date' => date('Y-m-d'), 'total_amount' => 3000, 'paid' => 0, 'vat' => 0, 'total_discount' => 0,
            'redeem_points' => 0,
            'old' => [['id' => $detail->id, 'product_id' => $productId, 'qty' => 1, 'purchase_price' => 100, 'unit_price' => 3000, 'discount' => 0]],
            'old_batch_formatted' => json_encode([$batchId]), 'old_serial_formatted' => json_encode([null]),
            'batch_id_formatted' => '[]', 'serial_no_formatted' => '[]',
        ])->assertSessionHasNoErrors();

        $this->assertSame(30, $earned(), 'earned points follow the new total');
        $this->assertSame(0, $redeemed(), 'removed redemption gives the points back');
    }

    public function test_passwords_need_eight_characters_and_confirmation(): void
    {
        $roleId = DB::table('roles')->where('name', 'Super Admin')->value('id');
        $create = fn (array $over) => $this->actingAs($this->admin)->from('/admin/user/create')->post('/admin/user', array_merge(
            ['name' => 'P', 'email' => uniqid('p') . '@test.test', 'roles' => [$roleId]], $over));

        $create(['password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $create(['password' => 'long-enough-1', 'password_confirmation' => 'different-1'])->assertSessionHasErrors('password');
        $create(['password' => 'long-enough-1', 'password_confirmation' => 'long-enough-1'])->assertSessionHasNoErrors();
    }

    public function test_activity_log_keeps_24_months(): void
    {
        DB::table('activity_logs')->insert([
            ['user_id' => $this->admin->id, 'subject_type' => 'x', 'subject_id' => 1, 'action' => 'old', 'created_at' => now()->subMonths(30)],
            ['user_id' => $this->admin->id, 'subject_type' => 'x', 'subject_id' => 1, 'action' => 'recent', 'created_at' => now()->subMonths(2)],
        ]);
        Artisan::call('crm:prune-activity-log');
        $this->assertDatabaseMissing('activity_logs', ['action' => 'old']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'recent']);
    }

    public function test_database_backup_includes_migrations_and_is_logged(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/database-backup');
        $response->assertOk();
        $zipPath = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($zipPath) === true);
        $sql = $zip->getFromName('database_backup.sql');
        $zip->close();
        @unlink($zipPath);

        $this->assertStringContainsString('CREATE TABLE `migrations`', $sql);
        $this->assertDatabaseHas('activity_logs', ['action' => 'database_backup_downloaded', 'user_id' => $this->admin->id]);
    }

    public function test_tickets_are_kept_per_branch(): void
    {
        $customerId = $this->patient();
        DB::table('tickets')->insert(['subject' => 'HQ complaint', 'customer_id' => $customerId, 'status' => 'Open', 'priority' => 'Medium', 'vendor_id' => null, 'created_at' => now()]);

        $this->actingAs($this->branchStaff);
        Ticket::create(['subject' => 'Branch complaint', 'customer_id' => $customerId, 'status' => 'Open', 'priority' => 'Medium']);
        $this->assertDatabaseHas('tickets', ['subject' => 'Branch complaint', 'vendor_id' => $this->branch->id]);
        $this->assertSame(['Branch complaint'], Ticket::pluck('subject')->all());

        $this->actingAs($this->admin);
        $this->assertCount(2, Ticket::all());
    }
}
