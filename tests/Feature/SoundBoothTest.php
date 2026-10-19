<?php

namespace Tests\Feature;

use App\Models\Resource;
use App\Models\User;
use App\Services\AppointmentAvailabilityEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Sound booth screen: a new branch gets a booth and booth services become bookable. */
class SoundBoothTest extends TestCase
{
    use RefreshDatabase, CrmFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
    }

    private function booth(int $branchId, string $name = 'Booth 1', bool $active = true): Resource
    {
        return Resource::create(['vendor_id' => $branchId, 'name' => $name, 'type' => 'sound_booth', 'capacity' => 1, 'is_active' => $active]);
    }

    private function branchStaff(array $perms): User
    {
        $user = $this->userWithPermissions($perms, 'staff');
        $user->forceFill(['vendor_id' => $this->branch->id])->save();

        return $user;
    }

    public function test_menu_and_permissions_come_from_the_acl_config(): void
    {
        foreach (['index', 'create', 'edit', 'delete'] as $action) {
            $this->assertTrue(DB::table('permissions')->where('name', "sound_booths.$action")->exists(), $action);
        }
    }

    public function test_admin_can_add_rename_deactivate_and_delete_a_booth(): void
    {
        $this->actingAs($this->admin)->get('/admin/sound-booths')->assertOk()->assertSee('No active sound booth here');

        $this->actingAs($this->admin)->post('/admin/sound-booths', ['vendor_id' => $this->branch->id, 'name' => 'Sound Booth 1'])->assertSessionHasNoErrors();
        $booth = Resource::firstOrFail();
        $this->assertSame('sound_booth', $booth->type);
        $this->assertSame($this->branch->id, (int) $booth->vendor_id);
        $this->assertTrue($booth->is_active);

        // duplicate name at the same branch is refused
        $this->actingAs($this->admin)->post('/admin/sound-booths', ['vendor_id' => $this->branch->id, 'name' => 'Sound Booth 1'])->assertSessionHasErrors('name');
        $this->assertSame(1, Resource::count());

        $this->actingAs($this->admin)->put("/admin/sound-booths/{$booth->id}", ['name' => 'Audiometry Booth'])->assertSessionHasNoErrors();
        $booth->refresh();
        $this->assertSame('Audiometry Booth', $booth->name);
        $this->assertFalse($booth->is_active, 'unchecked Active means deactivated');

        $this->actingAs($this->admin)->delete("/admin/sound-booths/{$booth->id}")->assertSessionHas('success');
        $this->assertSame(0, Resource::count());
    }

    public function test_a_booth_with_appointments_cannot_be_deleted(): void
    {
        $booth = $this->booth($this->branch->id);
        DB::table('appointment_holds')->insert([
            'token' => str_repeat('h', 40), 'vendor_id' => $this->branch->id, 'resource_id' => $booth->id,
            'hold_date' => '2026-12-01', 'start_time' => '10:00:00', 'end_time' => '10:30:00',
            'expires_at' => now()->addMinutes(5), 'created_at' => now(),
        ]);

        $this->actingAs($this->admin)->delete("/admin/sound-booths/{$booth->id}")->assertSessionHas('warning');
        $this->assertSame(1, Resource::count());
    }

    public function test_branch_user_only_sees_and_changes_their_own_branch(): void
    {
        $other = User::forceCreate(['name' => 'Other Branch', 'email' => 'other@test.test', 'password' => bcrypt('x-secret-1'), 'type' => 'vendor', 'status' => 1]);
        $this->booth($this->branch->id, 'Mine');
        $theirs = $this->booth($other->id, 'Theirs');
        $user = $this->branchStaff(['sound_booths.index', 'sound_booths.create', 'sound_booths.edit', 'sound_booths.delete']);

        $this->actingAs($user)->get('/admin/sound-booths')->assertOk()->assertSee('Mine')->assertDontSee('Theirs');

        // a posted foreign branch id is ignored: the booth lands on their own branch
        $this->actingAs($user)->post('/admin/sound-booths', ['vendor_id' => $other->id, 'name' => 'New one'])->assertSessionHasNoErrors();
        $this->assertSame($this->branch->id, (int) Resource::where('name', 'New one')->value('vendor_id'));

        $this->actingAs($user)->put("/admin/sound-booths/{$theirs->id}", ['name' => 'Hacked'])->assertNotFound();
        $this->actingAs($user)->delete("/admin/sound-booths/{$theirs->id}")->assertNotFound();
        $this->assertSame('Theirs', $theirs->fresh()->name);
    }

    public function test_screen_needs_its_permission(): void
    {
        $user = $this->branchStaff(['roster.index']);
        $this->actingAs($user)->get('/admin/sound-booths')->assertForbidden();
        $this->actingAs($user)->post('/admin/sound-booths', ['vendor_id' => $this->branch->id, 'name' => 'X'])->assertForbidden();
    }

    public function test_roster_warns_when_the_branch_has_no_booth(): void
    {
        $this->actingAs($this->admin)->get('/admin/roster?branch_id=' . $this->branch->id)->assertOk()->assertSee('no active sound booth');
        $this->booth($this->branch->id);
        $this->actingAs($this->admin)->get('/admin/roster?branch_id=' . $this->branch->id)->assertOk()->assertDontSee('no active sound booth');
    }

    public function test_booth_service_becomes_bookable_once_the_branch_has_a_booth(): void
    {
        $date = Carbon::now('Asia/Dhaka')->addDays(3);
        $audiologist = User::forceCreate(['name' => 'Audi', 'email' => 'audi@test.test', 'password' => bcrypt('x-secret-1'), 'type' => 'professional', 'status' => 1]);
        DB::table('staff_schedules')->insert(['user_id' => $audiologist->id, 'vendor_id' => $this->branch->id, 'day_of_week' => $date->dayOfWeekIso, 'start_time' => '10:00:00', 'end_time' => '16:00:00', 'is_active' => 1, 'created_at' => now()]);
        $serviceId = DB::table('appointment_services')->insertGetId([
            'name' => 'Pure Tone Audiometry', 'category' => 'test', 'duration_minutes' => 30, 'buffer_after_minutes' => 10,
            'requires_resource' => 1, 'resource_type' => 'sound_booth', 'status' => 1, 'created_at' => now(),
        ]);

        $engine = app(AppointmentAvailabilityEngine::class);
        $before = $engine->getAvailableTimes($this->branch->id, $serviceId, $date->toDateString());
        $this->assertSame('No sound booth available at this branch', $before['closure_reason']);
        $this->assertEmpty($before['slots']);

        $this->actingAs($this->admin)->post('/admin/sound-booths', ['vendor_id' => $this->branch->id, 'name' => 'Sound Booth 1'])->assertSessionHasNoErrors();
        $after = $engine->getAvailableTimes($this->branch->id, $serviceId, $date->toDateString());
        $this->assertNotEmpty($after['slots'], 'Pure Tone Audiometry is bookable after adding a booth');

        // deactivating the booth closes it again
        $this->actingAs($this->admin)->put('/admin/sound-booths/' . Resource::value('id'), ['name' => 'Sound Booth 1'])->assertSessionHasNoErrors();
        $this->assertEmpty($engine->getAvailableTimes($this->branch->id, $serviceId, $date->toDateString())['slots']);
    }
}
