<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketNote;
use App\Models\User;
use App\Support\DataScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Support plan step 1: ticket page, timeline of notes and changes, owner and status quick actions, list fixes. */
class SupportTicketTest extends TestCase
{
    use RefreshDatabase, CrmFixtures;

    protected int $patientId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
        $this->patientId = $this->patient();
    }

    protected function as($user)
    {
        DataScope::reset();

        return $this->actingAs($user);
    }

    protected function staff(string $name = 'Staff One'): User
    {
        return User::forceCreate(['name' => $name, 'email' => uniqid('s') . '@test.test', 'password' => bcrypt('secret-pass'), 'type' => null, 'status' => 1]);
    }

    protected function ticket(array $over = []): Ticket
    {
        return Ticket::create(array_merge(['subject' => 'Left ear distortion', 'description' => 'Sound is crackly', 'customer_id' => $this->patientId, 'status' => 'Open', 'priority' => 'High'], $over));
    }

    public function test_the_ticket_page_shows_details_and_a_ticket_number(): void
    {
        $this->as($this->admin);
        $ticket = $this->ticket();

        $this->assertSame('TK-' . str_pad((string) $ticket->id, 4, '0', STR_PAD_LEFT), $ticket->number);
        $this->get('/admin/ticket/' . $ticket->id)->assertOk()
            ->assertSee($ticket->number)->assertSee('Left ear distortion')->assertSee('Sound is crackly')->assertSee('Timeline')->assertSee('Ticket created');

        // The list links to it
        $this->get('/admin/ticket')->assertOk()->assertSee('ticket-open-' . $ticket->id, false)->assertSee($ticket->number);
    }

    public function test_page_access_follows_permissions_and_branches(): void
    {
        $hq = $this->as($this->admin)->ticket();
        $branchTicket = $this->as($this->branch)->ticket(['subject' => 'Branch ticket']);

        $this->as($this->userWithPermissions(['dashboard.index']))->get('/admin/ticket/' . $hq->id)->assertForbidden();

        // Staff of a branch open their own branch's ticket, never head office's
        $branchStaff = $this->userWithPermissions(['ticket_list.index']);
        $branchStaff->forceFill(['vendor_id' => $this->branch->id])->save();
        $this->as($branchStaff)->get('/admin/ticket/' . $branchTicket->id)->assertOk();
        $this->get('/admin/ticket/' . $hq->id)->assertNotFound();

        // Head office (Super Admin) can open a branch ticket
        $this->as($this->admin)->get('/admin/ticket/' . $branchTicket->id)->assertOk()->assertSee('Branch ticket');
    }

    public function test_notes_and_every_change_are_added_to_the_timeline(): void
    {
        $this->as($this->admin);
        $a = $this->staff('Anika');
        $b = $this->staff('Babul');
        $ticket = $this->ticket(['assigned_to' => $a->id]);

        $this->post('/admin/ticket/' . $ticket->id . '/notes', ['body' => 'Asked the patient to bring the device'])->assertSessionHasNoErrors();
        $this->post('/admin/ticket/' . $ticket->id . '/assign', ['assigned_to' => $b->id])->assertSessionHasNoErrors();
        $this->patch('/admin/ticket/' . $ticket->id . '/status', ['status' => 'In Progress'])->assertSessionHasNoErrors();
        $this->put('/admin/ticket/' . $ticket->id, [
            'subject' => $ticket->subject, 'description' => $ticket->description, 'customer_id' => $this->patientId,
            'assigned_to' => '', 'status' => 'In Progress', 'priority' => 'Critical',
        ])->assertSessionHasNoErrors();

        $lines = TicketNote::where('ticket_id', $ticket->id)->orderBy('id')->pluck('body', 'kind')->all();
        $bodies = TicketNote::where('ticket_id', $ticket->id)->orderBy('id')->pluck('body')->all();
        $this->assertContains('Ticket created and assigned to Anika', $bodies);
        $this->assertContains('Asked the patient to bring the device', $bodies);
        $this->assertContains('Reassigned from Anika to Babul', $bodies);
        $this->assertContains('Status changed from Open to In Progress', $bodies);
        $this->assertContains('Unassigned (was Babul)', $bodies);
        $this->assertContains('Priority changed from High to Critical', $bodies);
        $this->assertSame($this->admin->id, TicketNote::where('kind', 'note')->value('user_id'));

        // A form save that changes nothing adds nothing
        $count = TicketNote::count();
        $this->patch('/admin/ticket/' . $ticket->id . '/status', ['status' => 'In Progress']);
        $this->assertSame($count, TicketNote::count());

        $this->get('/admin/ticket/' . $ticket->id)->assertOk()->assertSee('Asked the patient to bring the device')->assertSee('Reassigned from Anika to Babul');
    }

    public function test_a_change_made_with_nobody_signed_in_is_recorded_as_the_system(): void
    {
        $ticket = $this->as($this->admin)->ticket();
        auth()->logout();

        Ticket::withoutGlobalScopes()->find($ticket->id)->update(['status' => 'Pending']);

        $note = TicketNote::where('kind', 'status')->first();
        $this->assertNull($note->user_id);
        $this->as($this->admin)->get('/admin/ticket/' . $ticket->id)->assertSee('System');
    }

    public function test_resolved_time_is_set_for_solved_and_closed_and_cleared_on_reopen(): void
    {
        $this->as($this->admin);
        $ticket = $this->ticket();
        $this->assertNull($ticket->resolved_at);

        $this->patch('/admin/ticket/' . $ticket->id . '/status', ['status' => 'Solved']);
        $solvedAt = $ticket->fresh()->resolved_at;
        $this->assertNotNull($solvedAt);

        // Solved to Closed keeps the time the ticket was first resolved
        $this->patch('/admin/ticket/' . $ticket->id . '/status', ['status' => 'Closed']);
        $this->assertEquals($solvedAt->timestamp, $ticket->fresh()->resolved_at->timestamp);

        $this->patch('/admin/ticket/' . $ticket->id . '/status', ['status' => 'Open']);
        $this->assertNull($ticket->fresh()->resolved_at);

        // A ticket closed straight from Open is resolved too
        $other = $this->ticket(['subject' => 'Closed fast']);
        $this->patch('/admin/ticket/' . $other->id . '/status', ['status' => 'Closed']);
        $this->assertNotNull($other->fresh()->resolved_at);
    }

    public function test_tickets_cannot_be_given_to_a_branch_login_and_the_picker_lists_people_only(): void
    {
        $this->as($this->admin);
        $person = $this->staff('Real Person');
        $ticket = $this->ticket();

        $this->post('/admin/ticket/' . $ticket->id . '/assign', ['assigned_to' => $this->branch->id])->assertSessionHasErrors('assigned_to');
        $this->assertNull($ticket->fresh()->assigned_to);
        $this->post('/admin/ticket/' . $ticket->id . '/assign', ['assigned_to' => $person->id])->assertSessionHasNoErrors();
        $this->assertSame($person->id, (int) $ticket->fresh()->assigned_to);

        $page = $this->get('/admin/ticket/' . $ticket->id)->assertOk()->getContent();
        $this->assertStringContainsString('Real Person', $page);
        $this->assertStringNotContainsString('<option value="' . $this->branch->id . '"', $page);
        $this->get('/admin/ticket/create')->assertOk()->assertDontSee('Test Branch');

        // New ticket for a branch login is refused too
        $this->post('/admin/ticket', ['subject' => 'x', 'description' => 'y', 'customer_id' => $this->patientId, 'assigned_to' => $this->branch->id, 'status' => 'Open', 'priority' => 'Low'])
            ->assertSessionHasErrors('assigned_to');

        // A legacy ticket that already sits with a branch login can still be edited without touching the owner
        $legacy = $this->ticket(['subject' => 'Legacy', 'assigned_to' => $this->branch->id]);
        $this->put('/admin/ticket/' . $legacy->id, ['subject' => 'Legacy edited', 'description' => 'y', 'customer_id' => $this->patientId, 'assigned_to' => $this->branch->id, 'status' => 'Open', 'priority' => 'Low'])
            ->assertSessionHasNoErrors();
    }

    public function test_forms_cannot_move_a_ticket_to_another_branch(): void
    {
        $this->as($this->admin);
        $this->post('/admin/ticket', ['subject' => 'Mine', 'description' => 'y', 'customer_id' => $this->patientId, 'status' => 'Open', 'priority' => 'Low', 'vendor_id' => $this->branch->id])
            ->assertSessionHasNoErrors();
        $this->assertNull(Ticket::withoutGlobalScopes()->where('subject', 'Mine')->value('vendor_id'));
    }

    public function test_the_description_is_shown_as_plain_text_and_the_forms_have_no_rich_text_editor(): void
    {
        $this->as($this->admin);
        $ticket = $this->ticket(['description' => '<p>Hello <b>there</b></p><script>alert(1)</script>']);

        $page = $this->get('/admin/ticket/' . $ticket->id)->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $page);
        $this->assertStringContainsString('Hello there', $page);

        foreach (['/admin/ticket/create', '/admin/ticket/' . $ticket->id . '/edit'] as $url) {
            $form = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('summernote', $form);
            $this->assertStringContainsString('<textarea name="description"', $form);
        }
    }

    public function test_the_date_filter_includes_tickets_created_on_the_end_day(): void
    {
        $this->as($this->admin);
        $late = $this->ticket(['subject' => 'Created late on the end day']);
        DB::table('tickets')->where('id', $late->id)->update(['created_at' => '2026-03-10 18:30:00']);
        $early = $this->ticket(['subject' => 'Created before the range']);
        DB::table('tickets')->where('id', $early->id)->update(['created_at' => '2026-03-01 09:00:00']);

        $this->get('/admin/ticket?start_date=2026-03-05&end_date=2026-03-10')->assertOk()
            ->assertSee('Created late on the end day')->assertDontSee('Created before the range');
    }

    public function test_creating_a_ticket_opens_its_page(): void
    {
        $this->as($this->admin);
        $this->post('/admin/ticket', ['subject' => 'Charging issue', 'description' => 'Not charging', 'customer_id' => $this->patientId, 'status' => 'Open', 'priority' => 'Medium'])
            ->assertRedirect('/admin/ticket/' . Ticket::where('subject', 'Charging issue')->value('id'));
    }
}
