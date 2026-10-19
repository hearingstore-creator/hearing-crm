<?php

namespace Tests\Feature;

use App\Models\LoginEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Administration step 1: deactivated people cannot sign in or stay in, last sign-in, forced password change, profile page. */
class LoginSecurityTest extends TestCase
{
    use RefreshDatabase, CrmFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
    }

    public function test_inactive_user_cannot_sign_in_and_gets_a_clear_message(): void
    {
        $u = $this->userWithPermissions(['dashboard.index']);
        $u->update(['status' => 0]);

        $this->post('/login', ['email' => $u->email, 'password' => 'secret-pass'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame('blocked', LoginEvent::where('user_id', $u->id)->value('event'));
    }

    public function test_deactivating_signs_someone_out_on_their_next_click(): void
    {
        $u = $this->userWithPermissions(['dashboard.index']);
        $this->actingAs($u)->get('/admin/dashboard')->assertSuccessful();

        $u->update(['status' => 0]);

        $this->actingAs($u->fresh())->get('/admin/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_sign_in_records_last_login_and_a_login_event(): void
    {
        $u = $this->userWithPermissions(['dashboard.index']);

        $this->post('/login', ['email' => $u->email, 'password' => 'secret-pass']);
        $this->assertAuthenticatedAs($u);
        $this->post('/logout');
        $this->post('/login', ['email' => $u->email, 'password' => 'wrong-pass']);

        $u->refresh();
        $this->assertNotNull($u->last_login_at);
        $this->assertSame(['login', 'failed'], LoginEvent::where('user_id', $u->id)->orderBy('id')->pluck('event')->all());
    }

    public function test_new_user_and_admin_reset_must_change_password_first(): void
    {
        $this->actingAs($this->admin)->post('/admin/user', [
            'name' => 'New Hire', 'email' => 'hire@test.test', 'password' => 'temp-pass-1', 'password_confirmation' => 'temp-pass-1',
            'roles' => [\App\Models\Role::create(['name' => 'R ' . uniqid()])->id],
        ])->assertSessionHasNoErrors();

        $hire = User::where('email', 'hire@test.test')->firstOrFail();
        $this->assertTrue($hire->must_change_password);

        // Every admin page sends them to the profile page until they choose a password
        $this->actingAs($hire)->get('/admin/dashboard')->assertRedirect(route('profile.show'));
        $this->actingAs($hire)->get(route('profile.show'))->assertSuccessful()->assertSee('forced-notice', false);
    }

    public function test_changing_own_password_clears_the_flag_and_checks_the_current_one(): void
    {
        $u = $this->userWithPermissions(['dashboard.index']);
        $u->update(['must_change_password' => true]);

        $this->actingAs($u)->put(route('profile.password'), [
            'current_password' => 'nope-nope', 'password' => 'brand-new-1', 'password_confirmation' => 'brand-new-1',
        ])->assertSessionHasErrors('current_password');
        $this->assertTrue($u->fresh()->must_change_password);

        $this->actingAs($u)->put(route('profile.password'), [
            'current_password' => 'secret-pass', 'password' => 'brand-new-1', 'password_confirmation' => 'brand-new-1',
        ])->assertSessionHasNoErrors();

        $u->refresh();
        $this->assertFalse($u->must_change_password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('brand-new-1', $u->password));
        $this->actingAs($u)->get('/admin/dashboard')->assertSuccessful();
    }

    public function test_admin_setting_a_password_for_someone_forces_a_change_but_own_password_does_not(): void
    {
        $u = $this->userWithPermissions(['dashboard.index']);
        $this->actingAs($this->admin)->put('/admin/user/' . $u->id, [
            'name' => $u->name, 'email' => $u->email, 'password' => 'reset-pass-1', 'password_confirmation' => 'reset-pass-1',
            'roles' => $u->roles->pluck('id')->all(), 'status' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertTrue($u->fresh()->must_change_password);

        $this->actingAs($this->admin)->put('/admin/user/' . $this->admin->id, [
            'name' => $this->admin->name, 'email' => $this->admin->email, 'password' => 'my-own-pass-1', 'password_confirmation' => 'my-own-pass-1',
            'roles' => $this->admin->roles->pluck('id')->all(), 'status' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertFalse($this->admin->fresh()->must_change_password);
    }

    public function test_user_page_shows_last_sign_in(): void
    {
        $u = $this->userWithPermissions(['dashboard.index']);
        LoginEvent::record('login', $u->email, $u->id);

        $this->actingAs($this->admin)->get('/admin/user/' . $u->id . '/edit')
            ->assertSuccessful()->assertSee('signin-history', false)->assertSee('Signed in');
    }
}
