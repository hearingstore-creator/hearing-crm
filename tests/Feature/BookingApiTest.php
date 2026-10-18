<?php

namespace Tests\Feature;

use App\Models\LeadCaptureForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Website booking API protection and professional slot editing (audit Batch 5). */
class BookingApiTest extends TestCase
{
    use RefreshDatabase, CrmFixtures;

    protected array $form = ['key' => 'test-form-key', 'secret' => 'test-form-secret'];
    protected int $serviceId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
        (new LeadCaptureForm)->forceFill(['name' => 'Website', 'key' => $this->form['key'], 'secret' => $this->form['secret'], 'is_active' => 1])->save();
        $this->serviceId = DB::table('appointment_services')->insertGetId(['name' => 'Hearing Test', 'category' => 'test', 'duration_minutes' => 30, 'status' => 1, 'created_at' => now()]);
    }

    protected function signedPost(string $uri, array $body, bool $sign = true, ?int $ts = null)
    {
        $json = json_encode($body);
        $ts = $ts ?? time();
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        if ($sign) {
            $headers += ['HTTP_X_FORM_KEY' => $this->form['key'], 'HTTP_X_TIMESTAMP' => (string) $ts, 'HTTP_X_SIGNATURE' => hash_hmac('sha256', $ts . '.' . $json, $this->form['secret'])];
        }

        return $this->call('POST', $uri, [], [], [], $headers, $json);
    }

    protected function holdBody(string $ip): array
    {
        return ['branch_id' => $this->branch->id, 'service_id' => $this->serviceId, 'date' => date('Y-m-d', strtotime('+1 day')), 'start_time' => '10:00', 'client_ip' => $ip];
    }

    protected function bookBody(array $over = []): array
    {
        return array_merge(['name' => 'Visitor', 'phone' => '01712345678', 'hold_token' => str_repeat('x', 40), 'client_ip' => '192.0.2.50', 'elapsed_seconds' => 12], $over);
    }

    public function test_hold_and_book_require_a_valid_signature(): void
    {
        $this->signedPost('/api/v1/appointments/hold', $this->holdBody('203.0.113.1'), false)->assertStatus(401);
        $this->signedPost('/api/v1/appointments/hold', $this->holdBody('203.0.113.1'), true, time() - 3600)->assertStatus(401);
        $this->signedPost('/api/v1/appointments/book', $this->bookBody(), false)->assertStatus(401);
        // signed + valid: passes the guards and reaches slot logic (no roster -> slot not available)
        $this->signedPost('/api/v1/appointments/hold', $this->holdBody('203.0.113.1'))->assertStatus(409);
    }

    public function test_hold_limits_per_visitor(): void
    {
        foreach ([1, 2] as $i) {
            DB::table('appointment_holds')->insert(['token' => str_repeat((string) $i, 40), 'vendor_id' => $this->branch->id, 'service_id' => $this->serviceId, 'hold_date' => date('Y-m-d', strtotime('+1 day')), 'start_time' => '11:00:00', 'end_time' => '11:30:00', 'expires_at' => now('Asia/Dhaka')->addMinutes(5), 'ip_address' => '198.51.100.7', 'created_at' => now()]);
        }
        $this->signedPost('/api/v1/appointments/hold', $this->holdBody('198.51.100.7'))->assertStatus(429);

        for ($i = 0; $i < 6; $i++) {
            $this->signedPost('/api/v1/appointments/hold', $this->holdBody('198.51.100.20'))->assertStatus(409);
        }
        $this->signedPost('/api/v1/appointments/hold', $this->holdBody('198.51.100.20'))->assertStatus(429);
    }

    public function test_book_anti_spam_and_limits(): void
    {
        $leads = DB::table('leads')->count();
        $this->signedPost('/api/v1/appointments/book', $this->bookBody(['website' => 'bot']))->assertOk();
        $this->signedPost('/api/v1/appointments/book', $this->bookBody(['elapsed_seconds' => 1]))->assertOk();
        $this->assertSame($leads, DB::table('leads')->count(), 'bots create nothing');

        $body = $this->bookBody();
        unset($body['elapsed_seconds']);
        $this->signedPost('/api/v1/appointments/book', $body)->assertStatus(422);

        // same phone from different visitors: 2 per day, then limited (dummy hold -> 410 means it got through)
        $this->signedPost('/api/v1/appointments/book', $this->bookBody(['client_ip' => '192.0.2.71', 'phone' => '01912345678']))->assertStatus(410);
        $this->signedPost('/api/v1/appointments/book', $this->bookBody(['client_ip' => '192.0.2.72', 'phone' => '01912345678']))->assertStatus(410);
        $this->signedPost('/api/v1/appointments/book', $this->bookBody(['client_ip' => '192.0.2.73', 'phone' => '01912345678']))->assertStatus(429);

        config(['weblead.turnstile_secret' => 'test-secret']);
        $this->signedPost('/api/v1/appointments/book', $this->bookBody(['client_ip' => '192.0.2.90', 'phone' => '01612345678']))->assertStatus(422);
    }

    public function test_signed_lead_api_still_works(): void
    {
        $body = ['name' => 'Lead', 'phone' => '01512345678', 'client_ip' => '192.0.2.99', 'elapsed_seconds' => 20];
        $this->signedPost('/api/v1/leads', $body, false)->assertStatus(401);
        $this->signedPost('/api/v1/leads', $body)->assertStatus(202);
    }

    public function test_editing_a_professional_keeps_old_appointment_times(): void
    {
        $days = [DB::table('days')->insertGetId(['day' => 'Saturday']), DB::table('days')->insertGetId(['day' => 'Sunday'])];
        $slot1 = DB::table('time_slots')->insertGetId(['start_time' => '09:00:00', 'end_time' => '10:00:00', 'status' => 1, 'created_at' => now()]);
        $slot2 = DB::table('time_slots')->insertGetId(['start_time' => '10:00:00', 'end_time' => '11:00:00', 'status' => 1, 'created_at' => now()]);
        $pro = \App\Models\User::forceCreate(['name' => 'Pro', 'email' => 'pro@test.test', 'password' => bcrypt('x'), 'type' => 'professional', 'status' => 1, 'designation' => 'audiologist']);
        // ProfessionalTimeSlot is a Pivot (no id returned on create), so insert directly
        $keep = DB::table('professional_time_slots')->insertGetId(['user_id' => $pro->id, 'time_slot_id' => $slot1, 'day_id' => $days[0]]);
        $drop = DB::table('professional_time_slots')->insertGetId(['user_id' => $pro->id, 'time_slot_id' => $slot2, 'day_id' => $days[0]]);
        $appointmentId = DB::table('appointments')->insertGetId(['user_id' => $pro->id, 'professional_time_slot_id' => $drop, 'appointment_date' => date('Y-m-d', strtotime('-3 days')), 'status' => 'completed', 'created_at' => now()]);

        $this->actingAs($this->admin)->from('/admin/professionals')->put("/admin/professionals/{$pro->id}", [
            'name' => 'Pro', 'email' => 'pro@test.test', 'designation' => 'audiologist', 'status' => 1,
            'services' => [$this->serviceId], 'time_slots' => [$days[0] => [$slot1], $days[1] => [$slot2]],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('professional_time_slots', ['id' => $keep]);
        $this->assertDatabaseMissing('professional_time_slots', ['id' => $drop]);
        $this->assertDatabaseHas('professional_time_slots', ['user_id' => $pro->id, 'day_id' => $days[1], 'time_slot_id' => $slot2]);
        $appointment = DB::table('appointments')->find($appointmentId);
        $this->assertSame('10:00', substr((string) $appointment->start_time, 0, 5));
        $this->assertSame('11:00', substr((string) $appointment->end_time, 0, 5));
    }
}
