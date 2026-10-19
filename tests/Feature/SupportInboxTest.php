<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use App\Support\DataScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Support plan step 2: inbox with saved views, search, due timers and the patient search box. */
class SupportInboxTest extends TestCase
{
    use RefreshDatabase, CrmFixtures;

    /** @var array<string, Ticket> */
    protected array $t = [];
    protected User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
        // Tuesday 10:00, so "due today" and "this week" do not depend on when the suite runs
        Carbon::setTestNow('2026-03-10 10:00:00');
        $this->other = User::forceCreate(['name' => 'Other Staff', 'email' => 'other@test.test', 'password' => bcrypt('secret-pass'), 'type' => null, 'status' => 1]);
        $this->actingAs($this->admin);
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

    /** One ticket; $opened is the time it was opened. */
    protected function ticket(string $key, string $priority, string $status, ?int $owner, string $opened, array $over = []): Ticket
    {
        $patient = $over['customer_id'] ?? $this->patient();
        $ticket = Ticket::create(array_merge([
            'subject' => "Ticket $key", 'description' => 'x', 'customer_id' => $patient, 'status' => $status, 'priority' => $priority, 'assigned_to' => $owner,
        ], array_diff_key($over, ['customer_id' => 1])));
        DB::table('tickets')->where('id', $ticket->id)->update(['created_at' => $opened, 'resolved_at' => in_array($status, ['Solved', 'Closed']) ? ($over['resolved_at'] ?? '2026-03-10 09:00:00') : null]);

        return $this->t[$key] = $ticket->fresh();
    }

    /** The seven tickets the view tests rely on (now = Tue 10:00). */
    protected function seedTickets(): void
    {
        $me = $this->admin->id;
        $this->ticket('A', 'Critical', 'Open', $me, '2026-03-10 04:00:00');        // due 08:00: overdue 2h
        $this->ticket('B', 'High', 'In Progress', null, '2026-03-10 08:00:00');    // due tomorrow 08:00
        $this->ticket('C', 'Medium', 'Open', $this->other->id, '2026-03-10 09:30:00');
        $this->ticket('D', 'Low', 'Pending', $this->other->id, '2026-03-08 09:00:00');
        $this->ticket('E', 'Medium', 'On Hold', $this->other->id, '2026-03-02 09:00:00');
        $this->ticket('F', 'Medium', 'Solved', $me, '2026-03-09 09:00:00');         // resolved today
        $this->ticket('G', 'Low', 'Closed', null, '2026-02-01 09:00:00', ['resolved_at' => '2026-02-03 09:00:00']);
        $this->ticket('H', 'Critical', 'Open', null, '2026-03-10 09:00:00');       // due 13:00: today
    }

    protected function subjectsOnPage(string $url): array
    {
        $html = $this->get($url)->assertOk()->getContent();
        preg_match_all('/Ticket ([A-H])<\/span>/', $html, $m);

        return $m[1];
    }

    // ── views ──

    public function test_saved_views_count_and_list_the_right_tickets(): void
    {
        $this->seedTickets();
        $page = $this->get('/admin/ticket?view=open')->assertOk();

        foreach (['mine' => 1, 'unassigned' => 2, 'today' => 1, 'overdue' => 1, 'waiting' => 2, 'solved' => 1, 'open' => 6, 'all' => 8] as $view => $n) {
            $page->assertSeeInOrder(['id="view-' . $view . '"', '<span class="n">' . $n . '</span>'], false);
        }

        $this->assertEqualsCanonicalizing(['A'], $this->subjectsOnPage('/admin/ticket?view=mine'));
        $this->assertEqualsCanonicalizing(['B', 'H'], $this->subjectsOnPage('/admin/ticket?view=unassigned'));
        $this->assertEqualsCanonicalizing(['H'], $this->subjectsOnPage('/admin/ticket?view=today'));
        $this->assertEqualsCanonicalizing(['A'], $this->subjectsOnPage('/admin/ticket?view=overdue'));
        $this->assertEqualsCanonicalizing(['D', 'E'], $this->subjectsOnPage('/admin/ticket?view=waiting'));
        $this->assertEqualsCanonicalizing(['F'], $this->subjectsOnPage('/admin/ticket?view=solved'));
        $this->assertEqualsCanonicalizing(['A', 'B', 'C', 'D', 'E', 'H'], $this->subjectsOnPage('/admin/ticket?view=open'));
        $this->assertCount(8, $this->subjectsOnPage('/admin/ticket?view=all'));
    }

    public function test_the_default_view_is_my_work_then_all_open_and_older_links_show_everything(): void
    {
        $this->seedTickets();
        $this->assertSame(['A'], $this->subjectsOnPage('/admin/ticket'));

        // Somebody with nothing assigned starts on all open tickets
        $lead = $this->userWithPermissions(['ticket_list.index']);
        $this->as($lead);
        $this->assertCount(6, $this->subjectsOnPage('/admin/ticket'));

        // Older bookmarks (?status=, ?open_only=) keep working and are not hidden by a view
        $this->as($this->admin);
        $this->assertEqualsCanonicalizing(['F'], $this->subjectsOnPage('/admin/ticket?status=Solved'));
        $this->assertEqualsCanonicalizing(['A', 'B', 'C', 'D', 'E', 'H'], $this->subjectsOnPage('/admin/ticket?open_only=1'));
    }

    public function test_the_inbox_lists_what_needs_attention_first(): void
    {
        $this->seedTickets();

        // Overdue first, then by priority and age; tickets waiting on the patient after those; finished ones last
        $this->assertSame(['A', 'H', 'B', 'C', 'E', 'D'], $this->subjectsOnPage('/admin/ticket?view=open'));
        // History views are newest first
        $this->assertSame(['C', 'H', 'B', 'A', 'F', 'D', 'E', 'G'], $this->subjectsOnPage('/admin/ticket?view=all'));
    }

    // ── due timers ──

    public function test_due_labels_follow_priority_and_pause_while_waiting(): void
    {
        $this->seedTickets();

        $this->assertSame(['state' => 'overdue', 'label' => 'Overdue 2h', 'tone' => 'danger'], $this->t['A']->dueState());
        $this->assertSame('soon', $this->t['H']->dueState()['state']);
        $this->assertSame('Due in 3h', $this->t['H']->dueState()['label']);
        $this->assertSame('Due in 22h', $this->t['B']->dueState()['label']);
        $this->assertSame('Waiting on patient', $this->t['D']->dueState()['label']);
        $this->assertSame('done', $this->t['F']->dueState()['state']);

        $page = $this->get('/admin/ticket?view=open')->getContent();
        $this->assertStringContainsString('Overdue 2h', $page);
        $this->assertStringContainsString('Waiting on patient', $page);

        // The hours come from Support Settings, so a stricter rule turns the same ticket overdue
        \App\Models\SupportSetting::current()->update(['sla_critical_hours' => 1, 'sla_high_hours' => 1, 'sla_medium_hours' => 1, 'sla_low_hours' => 1]);
        \App\Models\SupportSetting::forget();
        $this->assertSame('overdue', $this->t['B']->fresh()->dueState()['state']);
        $this->assertEqualsCanonicalizing(['A', 'B'], $this->subjectsOnPage('/admin/ticket?view=overdue'));
    }

    // ── search and filters ──

    public function test_search_finds_tickets_by_number_subject_patient_name_and_phone(): void
    {
        $lead = DB::table('leads')->insertGetId(['full_name' => 'Rahima Begum', 'phone_number' => '01711223344', 'normalized_phone' => '8801711223344', 'created_at' => now()]);
        $customer = DB::table('customers')->insertGetId(['customer_uid' => 'C-SUP-1', 'lead_id' => $lead, 'referral_code' => 'SUP1', 'created_at' => now()]);
        $this->seedTickets();
        $special = $this->ticket('X', 'High', 'Open', null, '2026-03-10 09:40:00', ['subject' => 'Whistling in noisy rooms', 'customer_id' => $customer]);

        $found = fn (string $q) => $this->get('/admin/ticket?view=all&q=' . urlencode($q))->assertOk()->getContent();
        foreach (['Whistling', 'rahima', '01711223344', '+8801711-223344', $special->number, 'tk-' . $special->id, (string) $special->id] as $q) {
            $this->assertStringContainsString('Whistling in noisy rooms', $found($q), "search for '$q'");
        }
        $this->assertStringNotContainsString('Ticket A', $found('Rahima'));
        $this->assertStringContainsString('No tickets match', $found('zzzz-nothing'));
    }

    public function test_priority_and_owner_filters(): void
    {
        $this->seedTickets();

        $this->assertEqualsCanonicalizing(['A', 'H'], $this->subjectsOnPage('/admin/ticket?view=open&priority=Critical'));
        $this->assertEqualsCanonicalizing(['C', 'D', 'E'], $this->subjectsOnPage('/admin/ticket?view=open&assigned_to=' . $this->other->id));
        $this->assertEqualsCanonicalizing(['B', 'H'], $this->subjectsOnPage('/admin/ticket?view=open&assigned_to=none'));
        $this->assertEqualsCanonicalizing(['H'], $this->subjectsOnPage('/admin/ticket?view=open&assigned_to=none&priority=Critical'));
    }

    // ── branches and access ──

    public function test_counts_and_lists_follow_the_users_branch(): void
    {
        $hq = $this->ticket('A', 'High', 'Open', null, '2026-03-10 08:00:00');
        $branch = $this->as($this->branch)->ticket('B', 'High', 'Open', null, '2026-03-10 08:00:00');
        $this->as($this->admin);

        // Super Admin sees both branches' tickets
        $this->get('/admin/ticket?view=all')->assertOk()->assertSeeInOrder(['id="view-all"', '<span class="n">2</span>'], false);
        $this->assertEqualsCanonicalizing(['A', 'B'], $this->subjectsOnPage('/admin/ticket?view=all'));

        // Staff of the branch see only their own
        $staff = $this->userWithPermissions(['ticket_list.index']);
        $staff->forceFill(['vendor_id' => $this->branch->id])->save();
        $this->as($staff)->get('/admin/ticket?view=all')->assertSeeInOrder(['id="view-all"', '<span class="n">1</span>'], false);
        $this->assertSame(['B'], $this->subjectsOnPage('/admin/ticket?view=all'));
    }

    // ── patient search ──

    public function test_patient_search_finds_patients_across_branches_and_needs_permission(): void
    {
        $mk = fn (string $name, string $phone, ?int $vendor) => DB::table('customers')->insertGetId([
            'customer_uid' => 'C-' . $phone, 'referral_code' => 'R' . $phone, 'created_at' => now(),
            'lead_id' => DB::table('leads')->insertGetId(['full_name' => $name, 'phone_number' => $phone, 'normalized_phone' => '88' . $phone, 'vendor_id' => $vendor, 'created_at' => now()]),
        ]);
        $mk('Karim Uddin', '01899887766', $this->branch->id);
        $mk('Karima Sultana', '01322334455', null);

        $this->as($this->admin);
        $names = fn (string $q) => collect($this->getJson('/admin/ticket-patients?search=' . urlencode($q))->assertOk()->json())->pluck('text')->all();
        $this->assertSame([], $names('K'), 'two characters needed');
        $this->assertCount(2, $names('Karim'), 'head office finds patients of every branch');
        $this->assertStringContainsString('(01899887766)', $names('01899887766')[0]);
        $this->assertSame([], $names('nobody-like-this'));

        $this->as($this->userWithPermissions(['dashboard.index']))->getJson('/admin/ticket-patients?search=Karim')->assertForbidden();
        $this->as($this->userWithPermissions(['add_ticket.create']))->getJson('/admin/ticket-patients?search=Karim')->assertOk();
    }

    public function test_forms_use_the_search_box_instead_of_listing_every_patient(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->patient();
        }
        $chosen = $this->patient();
        $ticket = $this->ticket('A', 'High', 'Open', null, '2026-03-10 08:00:00', ['customer_id' => $chosen]);

        $create = $this->get('/admin/ticket/create')->assertOk()->getContent();
        $this->assertStringContainsString('class="form-control patient-search"', $create);
        $this->assertSame(0, preg_match_all('/<option value="\d+"[^>]*>Test Patient/', $create));

        // A patient can arrive preselected, and the edit form keeps the ticket's own patient
        $this->get('/admin/ticket/create?customer_id=' . $chosen)->assertSee('<option value="' . $chosen . '" selected>', false)->assertSee('Test Patient');
        $this->get('/admin/ticket/' . $ticket->id . '/edit')->assertOk()->assertSee('<option value="' . $chosen . '" selected>', false);
        $this->assertSame(1, substr_count($this->get('/admin/ticket/' . $ticket->id . '/edit')->getContent(), '<option value="' . $chosen . '" selected>'));
    }
}
