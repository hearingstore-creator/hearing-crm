<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Permission checks and branch isolation (audit Batches 1 and 3). */
class PermissionTest extends TestCase
{
    use RefreshDatabase, CrmFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
    }

    public function test_menus_added_by_migrations_survive_the_deploy_seeder(): void
    {
        foreach (["Today's Queue", 'Staff Rosters & Leave', 'Repair History', 'Report & Delivery Hub', 'Pending Services', 'Advances Report', 'Services Status', 'Doctor & Referral Statement'] as $menu) {
            $this->assertDatabaseHas('menus', ['name' => $menu]);
        }
        $this->assertDatabaseHas('permissions', ['name' => 'sale_fulfillment.index']);
        $this->assertDatabaseHas('permissions', ['name' => 'repair_jobs_board.payment']);
    }

    public function test_account_without_role_is_blocked_everywhere_that_used_to_be_open(): void
    {
        $nobody = User::forceCreate(['name' => 'No Role', 'email' => 'norole@test.test', 'password' => bcrypt('x'), 'status' => 1]);
        $this->actingAs($nobody);

        foreach ([
            ['get', '/admin/customer-search?search=ab'], ['get', '/admin/product-search'], ['get', '/admin/sales/unpaid-invoices'],
            ['post', '/admin/sales/collect-due'], ['post', '/admin/proposal/1/email'], ['get', '/admin/doctor/1/commission-rules'],
            ['post', '/admin/appointments/1/advance'], ['get', '/admin/roster'], ['get', '/admin/report/advances'],
            ['get', '/admin/repairs'], ['post', '/admin/repairs/rma/clear-settled'], ['post', '/admin/repairs/1/void-warranty'],
        ] as [$method, $uri]) {
            $this->json($method, $uri)->assertStatus(403);
        }
    }

    public function test_registration_is_closed(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_branch_login_cannot_delete_or_void_but_can_work(): void
    {
        $this->actingAs($this->branch);
        $this->json('get', '/admin/customer-search?search=ab')->assertOk();
        $this->json('get', '/admin/roster')->assertOk();
        $this->json('delete', '/admin/sale/999999', ['reason' => 'Test cancel'])->assertStatus(403);
        $this->json('delete', '/admin/purchase/999999', ['reason' => 'Test cancel'])->assertStatus(403);
        $this->json('post', '/admin/repairs/1/void-warranty')->assertStatus(403);
        $this->json('post', '/admin/repairs/rma/clear-settled')->assertStatus(403);
    }

    public function test_repair_job_of_another_branch_cannot_be_opened_by_id(): void
    {
        $customerId = $this->patient();
        $own = DB::table('repair_jobs')->insertGetId(['job_no' => 'T-OWN', 'customer_id' => $customerId, 'original_serial_no' => 'SN-OWN', 'vendor_id' => $this->branch->id, 'received_by' => $this->admin->id, 'created_by' => $this->admin->id, 'created_at' => now()]);
        $other = DB::table('repair_jobs')->insertGetId(['job_no' => 'T-HQ', 'customer_id' => $customerId, 'original_serial_no' => 'SN-HQ', 'vendor_id' => null, 'received_by' => $this->admin->id, 'created_by' => $this->admin->id, 'created_at' => now()]);

        $this->actingAs($this->branch);
        $this->get("/admin/repairs/$other")->assertNotFound();
        $this->json('post', "/admin/repairs/$other/update-status")->assertNotFound();
        $this->get("/admin/repairs/$own")->assertOk();
    }

    public function test_either_permission_syntax_works(): void
    {
        $viewer = $this->userWithPermissions(['sale_list.index']);
        $this->actingAs($viewer);
        $this->get('/admin/sales/fulfillment')->assertOk();
        $this->get('/admin/sales/pending-services')->assertOk();
        $this->json('post', '/admin/sales/collect-due')->assertStatus(403);
    }
}
