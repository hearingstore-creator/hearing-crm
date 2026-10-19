<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\RoleAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Administration step 3: People list with search and filters, one-click deactivate, password reset, access preview. */
class PeoplePageTest extends TestCase
{
    use RefreshDatabase, CrmFixtures;

    protected User $anika;
    protected User $babul;
    protected Role $desk;

    protected function setUp(): void
    {
        parent::setUp();
        RoleAccess::forget();
        $this->setUpCrm();

        $this->desk = Role::create(['name' => 'Desk']);
        $this->desk->permissions()->sync(\App\Models\Permission::whereIn('name', RoleAccess::templatePermissionNames('Front Desk / Sales'))->pluck('id'));
        $this->anika = User::forceCreate(['name' => 'Anika Rahman', 'email' => 'anika@test.test', 'password' => bcrypt('secret-pass'), 'status' => 1, 'vendor_id' => $this->branch->id, 'last_login_at' => now()->subHours(2)]);
        $this->anika->roles()->attach($this->desk->id);
        $this->babul = User::forceCreate(['name' => 'Babul Hossain', 'email' => 'babul@test.test', 'password' => bcrypt('secret-pass'), 'status' => 1]);
    }

    public function test_list_searches_and_filters_by_role_branch_and_status(): void
    {
        $page = $this->actingAs($this->admin);

        $page->get('/admin/user?q=anika')->assertSee('Anika Rahman')->assertDontSee('Babul Hossain');
        $page->get('/admin/user?role=' . $this->desk->id)->assertSee('Anika Rahman')->assertDontSee('Babul Hossain');
        $page->get('/admin/user?branch=' . $this->branch->id)->assertSee('Anika Rahman')->assertDontSee('Babul Hossain');
        $page->get('/admin/user?branch=head')->assertSee('Babul Hossain')->assertDontSee('Anika Rahman');
        $page->get('/admin/user?status=norole')->assertSee('Babul Hossain')->assertDontSee('Anika Rahman');
    }

    public function test_deactivated_people_are_filterable_and_branch_logins_are_not_called_no_role(): void
    {
        $this->babul->update(['status' => 0]);

        $this->actingAs($this->admin)->get('/admin/user?status=inactive')->assertSee('Babul Hossain')->assertDontSee('Anika Rahman');
        // the branch login has no role by design, so it is not counted as "no role yet"
        $this->actingAs($this->admin)->get('/admin/user?status=norole')->assertDontSee('data-user="' . $this->branch->id . '"', false);
    }

    public function test_deactivate_and_activate_from_the_list(): void
    {
        $this->actingAs($this->admin)->post(route('user.status', $this->anika->id), ['status' => 0])->assertSessionHas('success');
        $this->assertSame(0, (int) $this->anika->fresh()->status);

        $this->actingAs($this->admin)->post(route('user.status', $this->anika->id), ['status' => 1])->assertSessionHas('success');
        $this->assertSame(1, (int) $this->anika->fresh()->status);
    }

    public function test_you_cannot_deactivate_yourself_or_the_last_super_admin(): void
    {
        $this->actingAs($this->admin)->post(route('user.status', $this->admin->id), ['status' => 0])->assertSessionHas('warning');
        $this->assertSame(1, (int) $this->admin->fresh()->status);

        // a second Super Admin acting on the only other one
        $second = User::forceCreate(['name' => 'Second', 'email' => 's2@test.test', 'password' => bcrypt('secret-pass'), 'status' => 1]);
        $second->roles()->attach(Role::where('name', 'Super Admin')->value('id'));
        $this->actingAs($second)->post(route('user.status', $this->admin->id), ['status' => 0])->assertSessionHas('success');
        $this->assertSame(0, (int) $this->admin->fresh()->status);
    }

    public function test_reset_password_gives_a_temporary_one_and_forces_a_change(): void
    {
        $response = $this->actingAs($this->admin)->post(route('user.reset', $this->anika->id));
        $response->assertSessionHas('success');

        preg_match('/: (\w{10}) —/', session('success'), $m);
        $this->assertNotEmpty($m[1] ?? null);
        $anika = $this->anika->fresh();
        $this->assertTrue(Hash::check($m[1], $anika->password));
        $this->assertTrue($anika->must_change_password);

        $this->actingAs($this->admin)->post(route('user.reset', $this->admin->id))->assertSessionHas('warning');
    }

    public function test_someone_without_edit_permission_cannot_deactivate_or_reset(): void
    {
        $viewer = $this->userWithPermissions(['users.index']);
        $this->actingAs($viewer)->post(route('user.status', $this->anika->id), ['status' => 0])->assertForbidden();
        $this->actingAs($viewer)->post(route('user.reset', $this->anika->id))->assertForbidden();
        $this->assertSame(1, (int) $this->anika->fresh()->status);
    }

    public function test_access_page_summarises_the_roles_in_plain_words(): void
    {
        $this->actingAs($this->admin)->get(route('user.show', $this->anika->id))
            ->assertSuccessful()->assertSee('access-summary', false)->assertSee('Add and edit')->assertSee('Desk');

        $this->actingAs($this->admin)->get(route('user.show', $this->babul->id))->assertSee('sees nothing');
        $this->actingAs($this->admin)->get(route('user.show', $this->branch->id))->assertSee('fixed-access', false);
    }
}
