<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use App\Support\DataScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Support plan step 4: ticket links (invoice, device, warranty, repair job) and tickets and tasks in My work. */
class SupportLinksTest extends TestCase
{
    use RefreshDatabase, CrmFixtures;

    protected int $patientId;
    protected int $productId;
    protected int $saleId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
        Http::fake();
        Carbon::setTestNow('2026-03-10 10:00:00');
        $this->patientId = $this->patient();

        // A hearing aid with 12 months customer warranty, sold with a serial
        $this->productId = $this->product('Phoenix 5');
        DB::table('products')->where('id', $this->productId)->update(['warranty_sales_months' => 12]);
        $this->postPurchase($this->admin, $this->supplier(), [[$this->productId, '', 'SN-LNK-1,SN-LNK-2', 2]])->assertSessionHasNoErrors();
        $this->postSale($this->admin, $this->patientId, [[$this->productId, null, ['SN-LNK-1'], 1]])->assertSessionHasNoErrors();
        $this->saleId = (int) DB::table('sale_masters')->orderByDesc('id')->value('id');
        DataScope::reset();
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

    protected function ticket(array $over = []): Ticket
    {
        return Ticket::create(array_merge(['subject' => 'Left ear distortion', 'description' => 'x', 'customer_id' => $this->patientId, 'status' => 'Open', 'priority' => 'High'], $over));
    }

    protected function form(array $over = []): array
    {
        return array_merge(['subject' => 'Charging issue', 'description' => 'Not charging', 'customer_id' => $this->patientId, 'status' => 'Open', 'priority' => 'Medium'], $over);
    }

    // ── device picker ──

    public function test_the_device_list_shows_what_the_patient_bought_and_nothing_else(): void
    {
        $this->as($this->admin);
        $invoice = DB::table('sale_masters')->where('id', $this->saleId)->value('sale_invoice_no');
        $until = Carbon::parse(DB::table('sale_masters')->where('id', $this->saleId)->value('date'))->addMonths(12);

        $list = $this->getJson('/admin/ticket-devices?customer_id=' . $this->patientId)->assertOk()->json();
        $this->assertCount(1, $list);
        $this->assertSame($this->saleId . '|SN-LNK-1', $list[0]['value']);
        $this->assertStringContainsString($invoice, $list[0]['text']);
        $this->assertStringContainsString('Phoenix 5', $list[0]['text']);
        $this->assertStringContainsString('SN-LNK-1', $list[0]['text']);
        $this->assertStringContainsString('warranty to ' . $until->format('d M Y'), $list[0]['text']);

        $this->assertSame([], $this->getJson('/admin/ticket-devices?customer_id=' . $this->patient())->assertOk()->json());
        $this->assertSame([], $this->getJson('/admin/ticket-devices')->assertOk()->json());

        $this->as($this->userWithPermissions(['dashboard.index']))->getJson('/admin/ticket-devices?customer_id=' . $this->patientId)->assertForbidden();
    }

    public function test_a_ticket_can_be_linked_to_a_device_of_its_own_patient_only(): void
    {
        $this->as($this->admin);

        $this->post('/admin/ticket', $this->form(['device' => $this->saleId . '|SN-LNK-1']))->assertSessionHasNoErrors();
        $ticket = Ticket::where('subject', 'Charging issue')->first();
        $this->assertSame($this->saleId, (int) $ticket->sale_master_id);
        $this->assertSame('SN-LNK-1', $ticket->serial_no);

        // Somebody else's invoice, or a serial that is not on the invoice, is refused
        $stranger = $this->patient();
        $this->post('/admin/ticket', $this->form(['subject' => 'Wrong patient', 'customer_id' => $stranger, 'device' => $this->saleId . '|SN-LNK-1']))->assertSessionHasErrors('device');
        $this->post('/admin/ticket', $this->form(['subject' => 'Wrong serial', 'device' => $this->saleId . '|SN-NOT-HERE']))->assertSessionHasErrors('device');
        $this->assertSame(0, Ticket::whereIn('subject', ['Wrong patient', 'Wrong serial'])->count());

        // No device chosen is fine
        $this->post('/admin/ticket', $this->form(['subject' => 'No device', 'device' => '']))->assertSessionHasNoErrors();
        $this->assertNull(Ticket::where('subject', 'No device')->value('sale_master_id'));
    }

    public function test_editing_keeps_the_link_unless_the_form_changes_it_or_the_patient(): void
    {
        $this->as($this->admin);
        $ticket = $this->ticket(['sale_master_id' => $this->saleId, 'serial_no' => 'SN-LNK-1']);
        $body = ['subject' => 'Edited', 'description' => 'x', 'customer_id' => $this->patientId, 'status' => 'Open', 'priority' => 'High'];

        // A form that does not send the picker leaves the link alone
        $this->put('/admin/ticket/' . $ticket->id, $body)->assertSessionHasNoErrors();
        $this->assertSame($this->saleId, (int) $ticket->fresh()->sale_master_id);

        // The edit page shows the current choice
        $this->get('/admin/ticket/' . $ticket->id . '/edit')->assertOk()->assertSee('<option value="' . $this->saleId . '|SN-LNK-1" selected>', false);

        // Choosing "None" clears it
        $this->put('/admin/ticket/' . $ticket->id, $body + ['device' => ''])->assertSessionHasNoErrors();
        $this->assertNull($ticket->fresh()->sale_master_id);

        // A different patient never keeps the old patient's invoice
        $ticket->update(['sale_master_id' => $this->saleId, 'serial_no' => 'SN-LNK-1']);
        $this->put('/admin/ticket/' . $ticket->id, array_merge($body, ['customer_id' => $this->patient()]))->assertSessionHasNoErrors();
        $this->assertNull($ticket->fresh()->sale_master_id);
    }

    public function test_the_create_form_can_arrive_with_a_device_chosen(): void
    {
        $this->as($this->admin);
        $this->get('/admin/ticket/create?customer_id=' . $this->patientId . '&sale_master_id=' . $this->saleId . '&serial=SN-LNK-1')->assertOk()
            ->assertSee('<option value="' . $this->saleId . '|SN-LNK-1" selected>', false);
    }

    // ── ticket page ──

    public function test_the_ticket_page_shows_invoice_device_and_warranty(): void
    {
        $this->as($this->admin);
        $ticket = $this->ticket(['sale_master_id' => $this->saleId, 'serial_no' => 'SN-LNK-1']);
        $invoice = DB::table('sale_masters')->where('id', $this->saleId)->value('sale_invoice_no');
        $until = Carbon::parse(DB::table('sale_masters')->where('id', $this->saleId)->value('date'))->addMonths(12);

        $page = $this->get('/admin/ticket/' . $ticket->id)->assertOk()->assertSee('id="linked-card"', false)->assertSee($invoice)->assertSee('Phoenix 5')->assertSee('SN-LNK-1')->getContent();
        $this->assertStringContainsString('Valid to ' . $until->format('d M Y'), $page);
        $this->assertStringContainsString('id="link-invoice"', $page);

        // Later the warranty has ended; a product without warranty says so
        Carbon::setTestNow($until->copy()->addMonths(3));
        $this->get('/admin/ticket/' . $ticket->id)->assertSee('Ended ' . $until->format('d M Y'));
        DB::table('products')->where('id', $this->productId)->update(['warranty_sales_months' => null]);
        $this->get('/admin/ticket/' . $ticket->id)->assertSee('No warranty on this product');

        // Nothing linked: no invoice or device rows (an open ticket still offers the repair button)
        $plain = $this->get('/admin/ticket/' . $this->ticket(['subject' => 'Plain'])->id)->assertOk()->getContent();
        $this->assertStringNotContainsString('id="link-invoice"', $plain);
        $this->assertStringNotContainsString('>Warranty<', $plain);
    }

    // ── repair job ──

    public function test_the_open_repair_button_depends_on_rights_and_state(): void
    {
        $this->as($this->admin);
        $ticket = $this->ticket(['sale_master_id' => $this->saleId, 'serial_no' => 'SN-LNK-1']);

        $html = $this->get('/admin/ticket/' . $ticket->id)->assertOk()->getContent();
        $this->assertStringContainsString('id="btn-open-repair"', $html);
        $this->assertStringContainsString('customer_id=' . $this->patientId . '&amp;serial=SN-LNK-1&amp;ticket_id=' . $ticket->id, $html);

        $ticket->update(['status' => 'Solved']);
        $this->get('/admin/ticket/' . $ticket->id)->assertDontSee('id="btn-open-repair"', false);
        $ticket->update(['status' => 'Open']);

        $noRepair = $this->userWithPermissions(['ticket_list.index', 'ticket_list.edit']);
        $this->as($noRepair)->get('/admin/ticket/' . $ticket->id)->assertOk()->assertDontSee('id="btn-open-repair"', false);

        // The intake wizard carries the ticket along
        $this->as($this->admin)->get('/admin/repairs/create?serial=SN-LNK-1&customer_id=' . $this->patientId . '&ticket_id=' . $ticket->id)->assertOk()
            ->assertSee('name="ticket_id" value="' . $ticket->id . '"', false);
    }

    public function test_a_repair_job_opened_from_a_ticket_links_both_ways(): void
    {
        $this->as($this->admin);
        $ticket = $this->ticket(['sale_master_id' => $this->saleId, 'serial_no' => 'SN-LNK-1']);

        $this->post('/admin/repairs', [
            'serial_no' => 'SN-LNK-1', 'customer_id' => $this->patientId, 'job_type' => 'repair', 'warranty_status' => 'under_product_warranty',
            'reported_issues' => 'Not charging', 'ticket_id' => $ticket->id,
        ])->assertSessionHasNoErrors();

        $job = DB::table('repair_jobs')->orderByDesc('id')->first();
        $this->assertNotNull($job, 'the repair job was created');
        $this->assertSame($job->id, (int) $ticket->fresh()->repair_job_id);
        $this->assertContains('Repair job ' . $job->job_no . ' opened for this ticket', $ticket->notes()->pluck('body')->all());

        // The ticket page links to the job and no longer offers a second one; the job card links back
        $page = $this->get('/admin/ticket/' . $ticket->id)->assertOk()->assertSee($job->job_no)->getContent();
        $this->assertStringContainsString('id="link-repair"', $page);
        $this->assertStringNotContainsString('id="btn-open-repair"', $page);
        $this->get('/admin/repairs/' . $job->id)->assertOk()->assertSee('id="link-ticket"', false)->assertSee($ticket->number);

        // A job opened without a ticket changes nothing
        $other = $this->ticket(['subject' => 'Other']);
        $this->post('/admin/repairs', ['serial_no' => 'SN-LNK-2', 'customer_id' => $this->patientId, 'job_type' => 'repair', 'warranty_status' => 'out_of_warranty']);
        $this->assertNull($other->fresh()->repair_job_id);
    }

    // ── My work ──

    protected function myWork(User $user): string
    {
        return $this->as($user)->get('/admin/dashboard/today/block/my-day')->assertOk()->getContent();
    }

    public function test_my_work_lists_my_tickets_and_tasks_and_counts_them(): void
    {
        $mine = $this->as($this->admin)->ticket(['subject' => 'My critical one', 'priority' => 'Critical', 'assigned_to' => $this->admin->id]);
        DB::table('tickets')->where('id', $mine->id)->update(['created_at' => '2026-03-10 04:00:00']);        // due 08:00: overdue
        $this->ticket(['subject' => 'Not mine', 'assigned_to' => $this->userWithPermissions(['dashboard.index'])->id]);
        $this->ticket(['subject' => 'Nobody has this']);
        $this->ticket(['subject' => 'Mine but finished', 'status' => 'Solved', 'assigned_to' => $this->admin->id]);
        $mk = fn (string $subject, string $due, ?int $owner, string $status = 'pending') => Task::withoutGlobalScopes()->create([
            'subject' => $subject, 'description' => 'x', 'customer_id' => $this->patientId, 'assigned_to' => $owner, 'status' => $status, 'priority' => 'medium', 'due_date' => $due,
        ]);
        $mk('Send repair estimate', '2026-03-09', $this->admin->id);
        $mk('Deliver cleaning kit', '2026-03-11', $this->admin->id);
        $mk('Next week task', '2026-03-17', $this->admin->id);
        $mk('Done task', '2026-03-08', $this->admin->id, 'completed');
        $mk('Someone elses task', '2026-03-09', null);

        $html = $this->myWork($this->admin);

        foreach (['My critical one', 'Send repair estimate', 'Deliver cleaning kit'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        foreach (['Not mine', 'Nobody has this', 'Mine but finished', 'Next week task', 'Done task', 'Someone elses task'] as $text) {
            $this->assertStringNotContainsString($text, $html, $text);
        }
        $this->assertStringContainsString($mine->number . ' · My critical one', $html);
        $this->assertStringContainsString('Overdue 2h', $html);
        $this->assertStringContainsString('Overdue 1d', $html);
        $this->assertStringContainsString('Due tomorrow', $html);
        $this->assertStringContainsString('data-cat="support"', $html);
        $this->assertMatchesRegularExpression('/Tickets &amp; tasks <span class="chip-n">3<\/span>/', $html);
        $this->assertStringContainsString('id="md-cnt-todo">3<', $html);
        $this->assertStringContainsString('id="md-cnt-overdue">2<', $html);
        $this->assertStringContainsString(url('admin/ticket/' . $mine->id), $html);
    }

    public function test_my_work_hides_tickets_and_tasks_the_person_may_not_open(): void
    {
        $this->as($this->admin);
        $viewer = $this->userWithPermissions(['dashboard.index', 'dashboard.daily_lookout']);
        $this->ticket(['subject' => 'Assigned to viewer', 'assigned_to' => $viewer->id]);
        Task::withoutGlobalScopes()->create(['subject' => 'Viewer task', 'description' => 'x', 'customer_id' => $this->patientId, 'assigned_to' => $viewer->id, 'status' => 'pending', 'priority' => 'low', 'due_date' => '2026-03-10']);

        $html = $this->myWork($viewer);
        $this->assertStringNotContainsString('Assigned to viewer', $html);
        $this->assertStringNotContainsString('Viewer task', $html);

        // With the menu rights they appear
        $support = $this->userWithPermissions(['dashboard.index', 'ticket_list.index', 'task_list.index']);
        $this->ticket(['subject' => 'For the support agent', 'assigned_to' => $support->id]);
        $this->assertStringContainsString('For the support agent', $this->myWork($support));
    }
}
