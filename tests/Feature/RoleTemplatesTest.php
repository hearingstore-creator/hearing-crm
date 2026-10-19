<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Administration step 2: starter role templates, people counts, delete guard, plain-language access summary. */
class RoleTemplatesTest extends TestCase
{
    use RefreshDatabase, CrmFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        RoleAccess::forget();
        $this->setUpCrm();
    }

    public function test_every_template_resolves_to_real_permissions_and_never_company_wide_or_admin_ones(): void
    {
        foreach (array_keys(RoleAccess::templates()) as $name) {
            $names = RoleAccess::templatePermissionNames($name);
            $this->assertNotEmpty($names, $name);
            $this->assertSame(count($names), Permission::whereIn('name', $names)->distinct()->count('name'), "$name lists a permission that does not exist");
            $this->assertNotContains('vendor_list.view_all_branches', $names);
            $this->assertNotContains('users.create', $names, "$name must not manage users");
            $this->assertNotContains('database_backup.index', $names);
        }
    }

    public function test_front_desk_can_add_but_not_delete_and_audiologist_is_read_only_on_sales(): void
    {
        $desk = RoleAccess::templatePermissionNames('Front Desk / Sales');
        $this->assertContains('add_sale.create', $desk);
        $this->assertContains('sale_list.edit', $desk);
        $this->assertNotContains('sale_list.delete', $desk);
        $this->assertNotContains('ledger.index', $desk);

        $audio = RoleAccess::templatePermissionNames('Audiologist');
        $this->assertContains('sale_list.view', $audio);
        $this->assertNotContains('add_sale.create', $audio);
    }

    public function test_creating_a_role_from_a_template_grants_its_permissions_and_shows_the_summary(): void
    {
        $this->actingAs($this->admin)->post('/admin/roles', ['name' => 'Desk Mym', 'template' => 'Front Desk / Sales'])
            ->assertSessionHasNoErrors();

        $role = Role::where('name', 'Desk Mym')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            RoleAccess::templatePermissionNames('Front Desk / Sales'),
            $role->permissions()->pluck('permissions.name')->unique()->values()->all()
        );

        $summary = RoleAccess::summarise($role->permissions()->pluck('permissions.name'));
        $this->assertSame('work', $summary['Sales']);
        $this->assertSame('none', $summary['Accounts']);
        $this->assertSame('none', $summary['Administration']);

        $this->actingAs($this->admin)->get(route('roles.show', $role->id))->assertSuccessful()->assertSee('access-summary', false)->assertSee('Add and edit');
    }

    public function test_blank_role_has_no_permissions(): void
    {
        $this->actingAs($this->admin)->post('/admin/roles', ['name' => 'Blank one', 'template' => ''])->assertSessionHasNoErrors();
        $this->assertSame(0, Role::where('name', 'Blank one')->firstOrFail()->permissions()->count());
    }

    public function test_a_template_cannot_hand_out_permissions_the_creator_does_not_hold(): void
    {
        $limited = $this->userWithPermissions(['roles.index', 'roles.create', 'sales.index', 'add_sale.create']);

        $this->actingAs($limited)->post('/admin/roles', ['name' => 'Capped', 'template' => 'Branch Manager'])->assertSessionHasNoErrors();

        $granted = Role::where('name', 'Capped')->firstOrFail()->permissions()->pluck('permissions.name')->all();
        $this->assertEqualsCanonicalizing(['sales.index', 'add_sale.create'], $granted);
    }

    public function test_role_in_use_cannot_be_deleted_but_an_empty_one_can(): void
    {
        $role = Role::create(['name' => 'Temp role']);
        $u = User::forceCreate(['name' => 'X', 'email' => 'x@test.test', 'password' => bcrypt('secret-pass'), 'status' => 1]);
        $u->roles()->attach($role->id);

        $this->actingAs($this->admin)->delete(route('roles.destroy', $role->id))->assertSessionHas('warning');
        $this->assertDatabaseHas('roles', ['id' => $role->id]);

        $u->roles()->detach();
        $this->actingAs($this->admin)->delete(route('roles.destroy', $role->id))->assertSessionHas('success');
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);

        $super = Role::where('name', 'Super Admin')->firstOrFail();
        $this->actingAs($this->admin)->delete(route('roles.destroy', $super->id))->assertSessionHas('warning');
        $this->assertDatabaseHas('roles', ['id' => $super->id]);
    }

    public function test_list_shows_people_counts_and_warns_about_people_without_a_role(): void
    {
        User::forceCreate(['name' => 'Roleless Rina', 'email' => 'rina@test.test', 'password' => bcrypt('secret-pass'), 'status' => 1]);

        $this->actingAs($this->admin)->get('/admin/roles')->assertSuccessful()
            ->assertSee('no-role-warning', false)->assertSee('People');
    }
}
