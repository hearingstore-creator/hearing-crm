<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Users / Roles cannot be used to gain more access, and expenses are cancelled by reversal (Part 2 audit, Batch 7). */
class AdminAccessTest extends TestCase
{
    use RefreshDatabase, CrmFixtures;

    protected User $manager;
    protected Role $superRole;
    protected Role $staffRole;
    protected Role $powerRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
        $this->superRole = Role::where('name', 'Super Admin')->firstOrFail();

        // A manager who may manage users and roles, plus a little sales access
        $this->manager = $this->userWithPermissions(['users.index', 'users.create', 'users.edit', 'users.delete', 'roles.index', 'roles.edit', 'sale_list.index', 'add_sale.create']);
        // A role inside the manager's permissions, and one beyond them
        $this->staffRole = Role::create(['name' => 'Counter Staff']);
        $this->staffRole->permissions()->sync(Permission::whereIn('name', ['sale_list.index'])->pluck('id'));
        $this->powerRole = Role::create(['name' => 'Accountant']);
        $this->powerRole->permissions()->sync(Permission::whereIn('name', ['expense.delete', 'ledger.index'])->pluck('id'));
    }

    protected function newUser(array $roleIds)
    {
        return $this->actingAs($this->manager)->from('/admin/user/create')->post('/admin/user', [
            'name' => 'New Person', 'email' => uniqid('n') . '@test.test', 'password' => 'secret-123', 'roles' => $roleIds,
        ]);
    }

    public function test_manager_cannot_create_super_admin_or_higher_role(): void
    {
        $this->newUser([$this->superRole->id])->assertSessionHasErrors('roles');
        $this->newUser([$this->powerRole->id])->assertSessionHasErrors('roles');
        $this->newUser([$this->staffRole->id])->assertSessionHasNoErrors();
    }

    public function test_manager_cannot_touch_super_admin_account(): void
    {
        $this->actingAs($this->manager)->get("/admin/user/{$this->admin->id}/edit")->assertForbidden();
        $this->actingAs($this->manager)->put("/admin/user/{$this->admin->id}", [
            'name' => 'Hijacked', 'email' => 'mine@test.test', 'password' => 'takeover-1', 'roles' => [$this->superRole->id], 'status' => 1,
        ])->assertForbidden();
        $this->assertSame('admin@gmail.com', $this->admin->fresh()->email);
    }

    public function test_manager_cannot_change_own_roles(): void
    {
        $ownRoles = $this->manager->roles()->pluck('roles.id')->all();
        $this->actingAs($this->manager)->from("/admin/user/{$this->manager->id}/edit")->put("/admin/user/{$this->manager->id}", [
            'name' => 'Manager', 'email' => $this->manager->email, 'roles' => array_merge($ownRoles, [$this->superRole->id]), 'status' => 1,
        ])->assertSessionHasErrors('roles');
        $this->assertFalse($this->manager->fresh()->isSuperAdmin());
    }

    public function test_delete_rules(): void
    {
        $this->actingAs($this->manager)->from('/admin/user')->delete("/admin/user/{$this->manager->id}")
            ->assertSessionHas('warning', fn ($w) => str_contains($w, 'own account'));
        $this->actingAs($this->manager)->from('/admin/user')->delete("/admin/user/{$this->admin->id}")
            ->assertSessionHas('warning', fn ($w) => str_contains($w, 'Super Admin'));
        $this->actingAs($this->admin)->from('/admin/user')->delete("/admin/user/{$this->branch->id}")
            ->assertSessionHas('warning', fn ($w) => str_contains($w, 'Branches screen'));

        $this->newUser([$this->staffRole->id]);
        $fresh = User::orderByDesc('id')->first();
        DB::table('activity_logs')->insert(['user_id' => $fresh->id, 'subject_type' => 'x', 'subject_id' => 1, 'action' => 'created', 'created_at' => now()]);
        $this->actingAs($this->manager)->from('/admin/user')->delete("/admin/user/{$fresh->id}")
            ->assertSessionHas('warning', fn ($w) => str_contains($w, 'Inactive'));

        $this->newUser([$this->staffRole->id]);
        $unused = User::orderByDesc('id')->first();
        $this->actingAs($this->manager)->from('/admin/user')->delete("/admin/user/{$unused->id}")->assertSessionHas('success');
        $this->actingAs($this->manager)->delete('/admin/user/999999')->assertNotFound();
    }

    public function test_role_editor_cannot_grant_beyond_own_permissions(): void
    {
        $managerRole = $this->manager->roles()->first();
        $expenseDelete = Permission::where('name', 'expense.delete')->value('id');

        $this->actingAs($this->manager)->get("/admin/roles/{$this->superRole->id}/edit")->assertForbidden();
        $this->actingAs($this->manager)->get("/admin/roles/{$managerRole->id}/edit")->assertForbidden();
        $this->actingAs($this->manager)->get("/admin/roles/{$this->powerRole->id}/edit")->assertForbidden();

        $this->actingAs($this->manager)->from('/admin/roles')->put("/admin/roles/{$this->staffRole->id}", ['permissions' => [$expenseDelete]])
            ->assertSessionHas('warning');
        $this->assertFalse($this->staffRole->permissions()->where('permissions.id', $expenseDelete)->exists());

        $addSale = Permission::where('name', 'add_sale.create')->value('id');
        $this->actingAs($this->manager)->from('/admin/roles')->put("/admin/roles/{$this->staffRole->id}", ['permissions' => [$addSale]])
            ->assertSessionHas('success');
    }

    public function test_only_manual_expenses_are_cancelled_and_by_reversal(): void
    {
        $headId = DB::table('income_expense_heads')->insertGetId(['head_type' => 'Expense', 'head_name' => 'Office', 'status' => 1, 'created_at' => now()]);
        $this->actingAs($this->admin)->post('/admin/expense', ['income_expense_head_id' => $headId, 'date' => date('Y-m-d'), 'paid' => 300, 'details' => 'Printer ink']);
        $manual = DB::table('ledgers')->where('details', 'Printer ink')->first();
        $system = DB::table('ledgers')->insertGetId(['income_expense_head_id' => $headId, 'invoice_no' => 'T-DP-1', 'expense_amount' => 50, 'income_amount' => 0, 'amount_type' => 'Expense', 'date' => date('Y-m-d'), 'type' => 'Doctor Payment', 'ref_invoice_no' => 'DP-T-1', 'created_at' => now()]);

        $accountant = $this->userWithPermissions(['expense.index', 'expense.delete']);
        $this->actingAs($accountant)->delete("/admin/expense/{$manual->id}", ['reason' => 'Entered twice'])->assertForbidden();
        $this->actingAs($this->admin)->from('/admin/expense')->delete("/admin/expense/{$manual->id}")->assertSessionHasErrors('reason');

        $this->actingAs($this->admin)->from('/admin/expense')->delete("/admin/expense/$system", ['reason' => 'Trying a system row'])
            ->assertSessionHas('warning');
        $this->assertDatabaseHas('ledgers', ['id' => $system, 'type' => 'Doctor Payment', 'deleted_at' => null]);

        $this->actingAs($this->admin)->from('/admin/expense')->delete("/admin/expense/{$manual->id}", ['reason' => 'Entered twice'])
            ->assertSessionHas('success');
        $this->assertDatabaseHas('ledgers', ['id' => $manual->id, 'type' => 'Expense (Reversed)', 'deleted_at' => null]);
        $this->assertDatabaseHas('ledgers', ['type' => 'Expense Reversal', 'expense_amount' => -300]);
        $net = (float) DB::table('ledgers')->where('income_expense_head_id', $headId)->where('details', 'like', '%Printer ink%')->whereNull('deleted_at')->sum('expense_amount');
        $this->assertEqualsWithDelta(0, $net, 0.01);
    }
}
