<?php

namespace Tests\Feature;

use App\Models\SupportSetting;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\TicketNote;
use App\Models\User;
use App\Services\TicketAutomation;
use App\Support\DataScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Support plan step 3: alerts, SLA and reminders, auto-close, solved SMS, task alerts, morning summary, settings. */
class SupportAutomationTest extends TestCase
{
    use RefreshDatabase, CrmFixtures;

    protected int $patientId;
    protected User $anika;
    protected User $babul;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
        Http::fake();
        Carbon::setTestNow('2026-03-10 10:00:00');   // Tuesday
        $this->patientId = $this->patient();
        $this->anika = $this->staff('Anika');
        $this->babul = $this->staff('Babul');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function as($user)
    {
        DataScope::reset();

        return $this->actingAs($user);
    }

    protected function staff(string $name): User
    {
        return User::forceCreate(['name' => $name, 'email' => strtolower($name) . '@test.test', 'password' => bcrypt('secret-pass'), 'type' => null, 'status' => 1]);
    }

    protected function ticket(array $over = [], ?string $opened = null): Ticket
    {
        $ticket = Ticket::create(array_merge(['subject' => 'Left ear distortion', 'description' => 'x', 'customer_id' => $this->patientId, 'status' => 'Open', 'priority' => 'High'], $over));
        if ($opened) {
            DB::table('tickets')->where('id', $ticket->id)->update(['created_at' => $opened, 'updated_at' => $opened]);
            DB::table('ticket_notes')->where('ticket_id', $ticket->id)->update(['created_at' => $opened, 'updated_at' => $opened]);
        }
        DB::table('notifications')->delete();   // start each scenario from a clean bell

        return $ticket->fresh();
    }

    /** Bell alerts a user holds, optionally of one category. */
    protected function alerts(User $user, ?string $category = null)
    {
        return DB::table('notifications')->where('notifiable_id', $user->id)->get()
            ->map(fn ($n) => json_decode($n->data, true))
            ->when($category, fn ($c) => $c->where('category', $category))->values();
    }

    // ── events ──

    public function test_a_new_ticket_tells_its_owner_or_the_managers_when_nobody_owns_it(): void
    {
        $this->as($this->babul);
        $owned = Ticket::create(['subject' => 'Owned by Anika', 'description' => 'x', 'customer_id' => $this->patientId, 'status' => 'Open', 'priority' => 'Low', 'assigned_to' => $this->anika->id]);
        $this->assertCount(1, $this->alerts($this->anika, 'ticket_assigned'));
        $this->assertStringContainsString($owned->number, $this->alerts($this->anika, 'ticket_assigned')[0]['title']);
        $this->assertCount(0, $this->alerts($this->admin), 'managers are not told about a ticket that has an owner');

        $loose = Ticket::create(['subject' => 'Nobody owns this', 'description' => 'x', 'customer_id' => $this->patientId, 'status' => 'Open', 'priority' => 'Low']);
        $this->assertCount(1, $this->alerts($this->admin, 'ticket_new'));
        $this->assertStringContainsString($loose->number, $this->alerts($this->admin, 'ticket_new')[0]['text']);

        // Nobody is told about their own action
        DB::table('notifications')->delete();
        $this->as($this->anika);
        Ticket::create(['subject' => 'Mine, by me', 'description' => 'x', 'customer_id' => $this->patientId, 'status' => 'Open', 'priority' => 'Low', 'assigned_to' => $this->anika->id]);
        $this->assertCount(0, $this->alerts($this->anika));
    }

    public function test_giving_a_ticket_to_someone_tells_them_but_not_when_they_take_it_themselves(): void
    {
        $this->as($this->admin);
        $ticket = $this->ticket();

        $ticket->update(['assigned_to' => $this->anika->id]);
        $this->assertCount(1, $this->alerts($this->anika, 'ticket_assigned'));
        $this->assertStringContainsString('was given to you', $this->alerts($this->anika, 'ticket_assigned')[0]['title']);

        $this->as($this->babul);
        $ticket->fresh()->update(['assigned_to' => $this->babul->id]);
        $this->assertCount(0, $this->alerts($this->babul), 'taking it yourself is not announced');
        $this->assertCount(1, $this->alerts($this->anika, 'ticket_assigned'));
    }

    public function test_reopening_tells_the_owner_and_closing_does_not(): void
    {
        $this->as($this->admin);
        $ticket = $this->ticket(['assigned_to' => $this->anika->id]);

        $ticket->update(['status' => 'Solved']);
        $ticket->fresh()->update(['status' => 'Closed']);
        $this->assertCount(0, $this->alerts($this->anika, 'ticket_reopened'));

        $ticket->fresh()->update(['status' => 'Open']);
        $this->assertCount(1, $this->alerts($this->anika, 'ticket_reopened'));
        $this->assertStringContainsString('was reopened', $this->alerts($this->anika, 'ticket_reopened')[0]['title']);
    }

    public function test_an_automation_failure_never_stops_a_ticket_from_saving(): void
    {
        $this->as($this->admin);
        $this->app->bind(TicketAutomation::class, fn () => throw new \RuntimeException('automation is down'));

        $ticket = Ticket::create(['subject' => 'Saved anyway', 'description' => 'x', 'customer_id' => $this->patientId, 'status' => 'Open', 'priority' => 'Low', 'assigned_to' => $this->anika->id]);
        $ticket->update(['status' => 'Solved']);

        $this->assertSame('Solved', Ticket::find($ticket->id)->status->value);
        $this->assertSame(1, TicketNote::where('ticket_id', $ticket->id)->where('kind', 'status')->count());
    }

    // ── scheduled jobs ──

    public function test_overdue_tickets_alert_the_owner_and_managers_once(): void
    {
        $this->as($this->admin);
        $late = $this->ticket(['assigned_to' => $this->anika->id, 'priority' => 'Critical'], '2026-03-10 04:00:00');       // due 08:00
        $fine = $this->ticket(['assigned_to' => $this->anika->id, 'priority' => 'Low'], '2026-03-10 04:00:00');
        $waiting = $this->ticket(['assigned_to' => $this->anika->id, 'priority' => 'Critical', 'status' => 'Pending'], '2026-03-01 04:00:00');
        $done = $this->ticket(['assigned_to' => $this->anika->id, 'priority' => 'Critical', 'status' => 'Solved'], '2026-03-01 04:00:00');
        DB::table('notifications')->delete();
        auth()->logout();

        // A dry run announces nothing and uses nothing up
        $this->artisan('crm:automations', ['task' => 'tickets', '--dry' => true])->expectsOutputToContain('Tickets: 2 (dry run)')->assertSuccessful();
        $this->assertCount(0, $this->alerts($this->anika));
        $this->assertSame(0, DB::table('automation_logs')->where('automation', 'ticket_overdue')->count());

        $this->artisan('crm:automations', ['task' => 'tickets'])->expectsOutputToContain('Tickets: 2')->assertSuccessful();   // 1 overdue + 1 reminder for the old Pending ticket
        $this->assertCount(1, $this->alerts($this->anika, 'ticket_stale'));
        $this->assertCount(1, $this->alerts($this->anika, 'ticket_overdue'));
        $this->assertCount(1, $this->alerts($this->admin, 'ticket_overdue'));
        $this->assertStringContainsString($late->number . ' is overdue', $this->alerts($this->anika, 'ticket_overdue')[0]['title']);
        $this->assertStringContainsString('2 hours', $this->alerts($this->anika, 'ticket_overdue')[0]['text']);

        // Running it again changes nothing
        $this->artisan('crm:automations', ['task' => 'tickets'])->expectsOutputToContain('Tickets: 0')->assertSuccessful();
        $this->assertCount(1, $this->alerts($this->anika, 'ticket_overdue'));
    }

    public function test_the_branch_login_hears_about_its_own_overdue_ticket_and_the_deadline_follows_the_settings(): void
    {
        $branchTicket = $this->as($this->branch)->ticket(['priority' => 'High'], '2026-03-08 09:00:00');   // due Mon 09:00
        auth()->logout();
        $this->artisan('crm:automations', ['task' => 'tickets'])->assertSuccessful();
        $this->assertCount(1, $this->alerts($this->branch, 'ticket_overdue'));
        $this->assertCount(1, $this->alerts($this->admin, 'ticket_overdue'));

        // A longer allowance moves the deadline of a fresh ticket
        SupportSetting::current()->update(['sla_high_hours' => 200]);
        SupportSetting::forget();
        $this->assertSame(200, Ticket::slaHoursFor('High'));
        $other = $this->as($this->admin)->ticket(['priority' => 'High'], '2026-03-08 09:00:00');
        auth()->logout();
        $this->artisan('crm:automations', ['task' => 'tickets'])->assertSuccessful();
        // (creating the second ticket cleared the bell) nothing new: the longer allowance keeps it from being overdue
        $this->assertCount(0, $this->alerts($this->admin, 'ticket_overdue'));
    }

    public function test_a_ticket_with_no_update_reminds_its_owner_once_per_quiet_spell(): void
    {
        $this->as($this->admin);
        $quiet = $this->ticket(['assigned_to' => $this->anika->id, 'priority' => 'Low'], '2026-03-05 09:00:00');   // untouched for 5 days
        $fresh = $this->ticket(['assigned_to' => $this->anika->id, 'priority' => 'Low'], '2026-03-09 12:00:00');   // 22 hours
        $unowned = $this->ticket(['priority' => 'Low'], '2026-03-05 09:00:00');
        DB::table('notifications')->delete();
        auth()->logout();

        $this->artisan('crm:automations', ['task' => 'tickets'])->assertSuccessful();
        $this->assertCount(1, $this->alerts($this->anika, 'ticket_stale'));
        $this->assertStringContainsString($quiet->number, $this->alerts($this->anika, 'ticket_stale')[0]['title']);
        $this->assertCount(1, $this->alerts($this->admin, 'ticket_stale'), 'a ticket nobody owns goes to the managers');

        $this->artisan('crm:automations', ['task' => 'tickets'])->assertSuccessful();
        $this->assertCount(1, $this->alerts($this->anika, 'ticket_stale'));

        // Somebody touches it, then it goes quiet again: a new reminder
        Carbon::setTestNow('2026-03-11 10:00:00');
        $this->as($this->anika);
        $quiet->fresh()->addNote('note', 'Called the patient');
        auth()->logout();
        Carbon::setTestNow('2026-03-14 10:30:00');
        $this->artisan('crm:automations', ['task' => 'tickets'])->assertSuccessful();
        $stale = $this->alerts($this->anika, 'ticket_stale');
        $this->assertCount(2, $stale->filter(fn ($a) => str_contains($a['title'], $quiet->number)), 'a second reminder after the next quiet spell');
        $this->assertCount(1, $stale->filter(fn ($a) => str_contains($a['title'], $fresh->number)), 'the other ticket went quiet in the meantime');
    }

    public function test_solved_tickets_close_by_themselves_after_the_set_days_unless_reopened(): void
    {
        $this->as($this->admin);
        $old = $this->ticket(['assigned_to' => $this->anika->id]);
        $old->update(['status' => 'Solved']);
        DB::table('tickets')->where('id', $old->id)->update(['resolved_at' => '2026-03-06 09:00:00']);   // 4 days ago
        $recent = $this->ticket();
        $recent->update(['status' => 'Solved']);
        DB::table('tickets')->where('id', $recent->id)->update(['resolved_at' => '2026-03-09 09:00:00']); // 1 day ago
        $reopened = $this->ticket();
        $reopened->update(['status' => 'Solved']);
        $reopened->fresh()->update(['status' => 'In Progress']);
        auth()->logout();

        $this->artisan('crm:automations', ['task' => 'tickets', '--dry' => true])->expectsOutputToContain('(dry run)')->assertSuccessful();
        $this->assertSame('Solved', $old->fresh()->status->value);

        $this->artisan('crm:automations', ['task' => 'tickets'])->assertSuccessful();
        $this->assertSame('Closed', $old->fresh()->status->value);
        $this->assertNotNull($old->fresh()->resolved_at);
        $this->assertSame('Solved', $recent->fresh()->status->value);
        $this->assertSame('In Progress', $reopened->fresh()->status->value);

        $bodies = TicketNote::where('ticket_id', $old->id)->pluck('body')->all();
        $this->assertContains('Status changed from Solved to Closed', $bodies);
        $this->assertContains('Closed automatically: solved 3 days ago and not reopened', $bodies);
        $this->assertCount(0, $this->alerts($this->anika, 'ticket_reopened'), 'closing by itself is not a reopen');

        // Longer allowance in the settings
        SupportSetting::current()->update(['auto_close_days' => 10]);
        SupportSetting::forget();
        DB::table('tickets')->where('id', $recent->id)->update(['resolved_at' => '2026-02-20 09:00:00']);
        $this->artisan('crm:automations', ['task' => 'tickets'])->assertSuccessful();
        $this->assertSame('Closed', $recent->fresh()->status->value);
    }

    // ── solved SMS ──

    public function test_marking_a_ticket_solved_texts_the_patient_once_when_the_sms_text_is_on(): void
    {
        $this->as($this->admin);
        $lead = DB::table('leads')->insertGetId(['full_name' => 'Rahima Begum', 'phone_number' => '01711223344', 'normalized_phone' => '8801711223344', 'created_at' => now()]);
        $customer = DB::table('customers')->insertGetId(['customer_uid' => 'C-SMS-1', 'lead_id' => $lead, 'referral_code' => 'SMS1', 'created_at' => now()]);
        $ticket = $this->ticket(['customer_id' => $customer, 'subject' => 'Charging issue']);

        // The text ships switched off
        $automation = DB::table('sms_automations')->where('trigger_type', 'ticket_solved')->first();
        $this->assertNotNull($automation);
        $this->assertSame(0, (int) $automation->is_active);
        $ticket->update(['status' => 'Solved']);
        $this->assertSame(0, DB::table('sms_logs')->count());

        DB::table('sms_automations')->where('id', $automation->id)->update(['is_active' => 1]);
        $other = $this->ticket(['customer_id' => $customer, 'subject' => 'Second issue']);
        $other->update(['status' => 'Solved']);

        $this->assertSame(1, (int) DB::table('sms_automations')->where('id', $automation->id)->value('sent_count'));
        $log = DB::table('sms_logs')->first();
        $this->assertSame('01711223344', $log->phone_number);
        $this->assertStringContainsString('Rahima Begum', $log->message);
        $this->assertStringContainsString($other->id < 10000 ? '#' . str_pad((string) $other->id, 4, '0', STR_PAD_LEFT) : '', $log->message);
        $this->assertStringContainsString('3 days', $log->message);

        // Solved, closed, solved again: the patient is not texted twice
        $other->fresh()->update(['status' => 'Closed']);
        $other->fresh()->update(['status' => 'Solved']);
        $this->assertSame(1, (int) DB::table('sms_automations')->where('id', $automation->id)->value('sent_count'));
    }

    // ── tasks ──

    public function test_tasks_due_tomorrow_and_overdue_alert_their_owner_once(): void
    {
        $mk = fn (string $subject, string $due, ?int $owner, string $status = 'pending') => Task::withoutGlobalScopes()->create([
            'subject' => $subject, 'description' => 'x', 'customer_id' => $this->patientId, 'assigned_to' => $owner, 'status' => $status, 'priority' => 'medium', 'due_date' => $due,
        ]);
        $mk('Due tomorrow', '2026-03-11', $this->anika->id);
        $mk('Overdue', '2026-03-08', $this->anika->id);
        $mk('Due next week', '2026-03-17', $this->anika->id);
        $mk('Done already', '2026-03-08', $this->anika->id, 'completed');
        $mk('Nobody has it', '2026-03-11', null);
        DB::table('notifications')->delete();

        $this->artisan('crm:automations', ['task' => 'tasks'])->expectsOutputToContain('Tasks: 2')->assertSuccessful();
        $alerts = $this->alerts($this->anika, 'task_due');
        $this->assertCount(2, $alerts);
        $this->assertEqualsCanonicalizing(['Task due tomorrow', 'Task overdue'], $alerts->pluck('title')->all());

        $this->artisan('crm:automations', ['task' => 'tasks'])->expectsOutputToContain('Tasks: 0')->assertSuccessful();
        $this->assertCount(2, $this->alerts($this->anika, 'task_due'));
    }

    // ── morning summary, mutes ──

    public function test_the_morning_summary_lists_each_persons_tickets_and_tasks_once_a_day(): void
    {
        $this->as($this->admin);
        $this->ticket(['assigned_to' => $this->anika->id, 'priority' => 'Critical'], '2026-03-10 04:00:00');   // overdue
        $this->ticket(['assigned_to' => $this->anika->id, 'priority' => 'Critical'], '2026-03-10 09:00:00');   // due 13:00: today
        Task::withoutGlobalScopes()->create(['subject' => 'Call back', 'description' => 'x', 'customer_id' => $this->patientId, 'assigned_to' => $this->anika->id, 'status' => 'pending', 'priority' => 'low', 'due_date' => '2026-03-10']);
        $this->ticket(['assigned_to' => $this->babul->id, 'priority' => 'Low'], '2026-03-10 09:00:00');         // nothing due today
        DB::table('notifications')->delete();
        auth()->logout();

        $this->artisan('crm:automations', ['task' => 'digest'])->assertSuccessful();
        $mine = $this->alerts($this->anika, 'support_digest');
        $this->assertCount(1, $mine);
        $this->assertSame('1 overdue ticket · 1 ticket due today · 1 task due today', $mine[0]['text']);
        $this->assertCount(0, $this->alerts($this->babul, 'support_digest'));

        $this->artisan('crm:automations', ['task' => 'digest'])->assertSuccessful();
        $this->assertCount(1, $this->alerts($this->anika, 'support_digest'));
    }

    public function test_a_person_can_mute_a_category(): void
    {
        $this->anika->forceFill(['notification_mutes' => ['ticket_overdue']])->save();
        $this->as($this->admin);
        $this->ticket(['assigned_to' => $this->anika->id, 'priority' => 'Critical'], '2026-03-10 04:00:00');
        auth()->logout();

        $this->artisan('crm:automations', ['task' => 'tickets'])->assertSuccessful();
        $this->assertCount(0, $this->alerts($this->anika, 'ticket_overdue'));
        $this->assertCount(1, $this->alerts($this->admin, 'ticket_overdue'));
        $this->assertArrayHasKey('ticket_overdue', \App\Notifications\CrmAlert::CATEGORIES);
    }

    // ── settings screen ──

    public function test_the_settings_screen_saves_valid_rules_and_is_closed_to_others(): void
    {
        $this->as($this->admin)->get('/admin/support-settings')->assertOk()->assertSee('Time allowed to answer a ticket');

        $this->post('/admin/support-settings', ['sla_critical_hours' => 2, 'sla_high_hours' => 12, 'sla_medium_hours' => 48, 'sla_low_hours' => 96, 'stale_days' => 3, 'auto_close_days' => 5])
            ->assertSessionHasNoErrors();
        $row = SupportSetting::first();
        $this->assertSame([2, 12, 48, 96, 3, 5], [$row->sla_critical_hours, $row->sla_high_hours, $row->sla_medium_hours, $row->sla_low_hours, $row->stale_days, $row->auto_close_days]);
        $this->assertSame(12, Ticket::slaHoursFor('High'));

        foreach ([['sla_critical_hours' => 0], ['sla_high_hours' => 99999], ['stale_days' => 'abc'], ['auto_close_days' => '']] as $bad) {
            $this->post('/admin/support-settings', array_merge(['sla_critical_hours' => 2, 'sla_high_hours' => 12, 'sla_medium_hours' => 48, 'sla_low_hours' => 96, 'stale_days' => 3, 'auto_close_days' => 5], $bad))
                ->assertSessionHasErrors(array_key_first($bad));
        }

        $this->as($this->branch)->get('/admin/support-settings')->assertForbidden();
        $this->post('/admin/support-settings', [])->assertForbidden();
        $this->as($this->userWithPermissions(['dashboard.index']))->get('/admin/support-settings')->assertForbidden();
        $viewer = $this->userWithPermissions(['support_settings.index']);
        $this->as($viewer)->get('/admin/support-settings')->assertOk();
        $this->post('/admin/support-settings', [])->assertForbidden();
    }

    public function test_the_inbox_and_ticket_page_show_the_deadline_from_the_settings(): void
    {
        $this->as($this->admin);
        SupportSetting::current()->update(['sla_high_hours' => 30]);
        SupportSetting::forget();
        $ticket = $this->ticket(['priority' => 'High'], '2026-03-10 08:00:00');   // due Wed 14:00

        $this->get('/admin/ticket?view=open')->assertOk()->assertSee('High 30h');
        $this->get('/admin/ticket/' . $ticket->id)->assertOk()->assertSee('11 Mar, 02:00 PM')->assertSee('Due in 28h');
    }
}
