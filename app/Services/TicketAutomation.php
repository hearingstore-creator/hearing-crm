<?php

namespace App\Services;

use App\Enums\SmsAutomationTrigger;
use App\Models\Customer;
use App\Models\SupportSetting;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\CrmAlert;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Automation for support tickets and tasks, built on the same pieces as the lead automations:
 * bell alerts (CrmNotifier, users can mute a category) and SMS Centre texts, and every scheduled action is
 * logged in automation_logs so running a job twice never repeats an alert, SMS or close.
 *
 * Event hooks (called from the Ticket model, never able to break a save):
 *   created   - owner told (or the people who run support, when nobody owns it yet)
 *   assigned  - the new owner told, unless they gave the ticket to themselves
 *   reopened  - owner told when a Solved / Closed ticket comes back
 *   solved    - "ticket solved" SMS to the patient (SMS Centre text, inactive until switched on)
 *
 * Scheduled jobs (php artisan crm:automations tickets | tasks; digest adds support lines):
 *   overdue   - ticket past its due time: owner and managers, once
 *   stale     - no update for N days: owner, once per quiet spell
 *   autoClose - Solved for N days with no reopen: closed by the system
 *   tasks     - task due tomorrow / overdue: assignee, once each
 */
class TicketAutomation
{
    public function __construct(private CrmNotifier $notifier, private SmsAutomationService $sms)
    {
    }

    // ───────────────────────── events ─────────────────────────

    public function created(Ticket $ticket): void
    {
        $this->safely(function () use ($ticket) {
            if ($ticket->assigned_to) {
                if ($ticket->assigned_to !== auth()->id()) {
                    $this->alert([$this->user($ticket->assigned_to)], $ticket, $ticket->number . ' is yours', $ticket->subject, 'fa-ticket-alt', 'info', 'ticket_assigned');
                }

                return;
            }
            $this->alert($this->managers($ticket), $ticket, 'New ticket needs an owner', $ticket->number . ' · ' . $ticket->subject, 'fa-ticket-alt', 'warning', 'ticket_new');
        });
    }

    public function assigned(Ticket $ticket): void
    {
        $this->safely(function () use ($ticket) {
            if ($ticket->assigned_to && $ticket->assigned_to !== auth()->id()) {
                $this->alert([$this->user($ticket->assigned_to)], $ticket, $ticket->number . ' was given to you', $ticket->subject, 'fa-user-check', 'info', 'ticket_assigned');
            }
        });
    }

    public function reopened(Ticket $ticket): void
    {
        $this->safely(function () use ($ticket) {
            $to = $ticket->assigned_to ? [$this->user($ticket->assigned_to)] : $this->managers($ticket);
            $this->alert($to, $ticket, $ticket->number . ' was reopened', $ticket->subject, 'fa-undo', 'warning', 'ticket_reopened');
        });
    }

    public function solved(Ticket $ticket): void
    {
        $this->safely(function () use ($ticket) {
            $customer = \App\Classes\Helper::readAllBranches(fn () => Customer::with('lead')->find($ticket->customer_id));
            $phone = $customer?->lead?->phone_number;
            if (!$phone) {
                return;
            }
            $this->sms->fire(SmsAutomationTrigger::TICKET_SOLVED, $phone, 'customer', (int) $customer->id, [
                'name' => $customer->lead->full_name, 'customer_name' => $customer->lead->full_name,
                'ticket_id' => str_pad((string) $ticket->id, 4, '0', STR_PAD_LEFT),
                'subject' => $ticket->subject,
                'days' => SupportSetting::current()->auto_close_days,
            ], 'ticket-solved:' . $ticket->id . ':' . optional($ticket->resolved_at)->timestamp);
        });
    }

    // ───────────────────────── scheduled jobs ─────────────────────────

    /** Tickets past their due time (clock running: Open / In Progress). Returns how many were announced. */
    public function overdue(bool $dry = false): int
    {
        $n = 0;
        $tickets = $this->openTickets()->whereIn('status', ['Open', 'In Progress'])->whereRaw(Ticket::dueSql() . ' < ?', [now()])->get();

        foreach ($tickets as $ticket) {
            if (!$this->claim('ticket-overdue:' . $ticket->id, 'ticket_overdue', $dry)) {
                continue;
            }
            $late = (int) abs(now()->diffInMinutes($ticket->due_at, false));
            $to = collect($this->managers($ticket))->push($ticket->assigned_to ? $this->user($ticket->assigned_to) : null);
            $dry || $this->alert($to, $ticket, $ticket->number . ' is overdue', $ticket->subject . ' · ' . $this->span($late) . ' past its ' . $ticket->priority->value . ' deadline', 'fa-stopwatch', 'danger', 'ticket_overdue');
            $n++;
        }

        return $n;
    }

    /** Tickets nobody has touched for stale_days. One reminder per quiet spell. */
    public function stale(bool $dry = false): int
    {
        $days = max(1, SupportSetting::current()->stale_days);
        $n = 0;

        foreach ($this->openTickets()->with('notes')->get() as $ticket) {
            $last = collect([$ticket->updated_at, optional($ticket->notes->first())->created_at])->filter()->max();
            if (!$last || $last->gt(now()->subDays($days))) {
                continue;
            }
            if (!$this->claim('ticket-stale:' . $ticket->id . ':' . $last->toDateString(), 'ticket_stale', $dry)) {
                continue;
            }
            $to = $ticket->assigned_to ? [$this->user($ticket->assigned_to)] : $this->managers($ticket);
            $dry || $this->alert($to, $ticket, $ticket->number . ' has had no update for ' . $days . ' days', $ticket->subject, 'fa-hourglass-half', 'warning', 'ticket_stale');
            $n++;
        }

        return $n;
    }

    /** Solved tickets that nobody reopened within auto_close_days are closed by the system. */
    public function autoClose(bool $dry = false): int
    {
        $days = max(1, SupportSetting::current()->auto_close_days);
        $n = 0;

        $tickets = Ticket::withoutGlobalScopes()->where('status', 'Solved')->where('resolved_at', '<=', now()->subDays($days))->get();
        foreach ($tickets as $ticket) {
            if (!$dry) {
                $ticket->status = 'Closed';
                $ticket->save();
                $ticket->addNote('system', 'Closed automatically: solved ' . $days . ' days ago and not reopened');
            }
            $n++;
        }

        return $n;
    }

    /** Tasks due tomorrow, and tasks past their due date, each announced once to the person they belong to. */
    public function tasks(bool $dry = false): int
    {
        $n = 0;
        $open = Task::withoutGlobalScopes()->whereNotIn('status', ['completed', 'cancelled'])->whereNotNull('assigned_to')->whereNotNull('due_date');

        foreach ((clone $open)->whereDate('due_date', today()->addDay())->get() as $task) {
            if ($this->claim('task-due:' . $task->id . ':' . $task->due_date->toDateString(), 'task_due', $dry)) {
                $dry || $this->notifier->send([$this->user($task->assigned_to)], new CrmAlert('Task due tomorrow', $task->subject, url('admin/task'), 'fa-tasks', 'info', 'task_due'));
                $n++;
            }
        }
        foreach ((clone $open)->whereDate('due_date', '<', today())->get() as $task) {
            if ($this->claim('task-overdue:' . $task->id . ':' . $task->due_date->toDateString(), 'task_due', $dry)) {
                $dry || $this->notifier->send([$this->user($task->assigned_to)], new CrmAlert('Task overdue', $task->subject . ' was due ' . $task->due_date->format('d M'), url('admin/task'), 'fa-exclamation-circle', 'danger', 'task_due'));
                $n++;
            }
        }

        return $n;
    }

    /** The morning summary of tickets and tasks, one line per person who has something. */
    public function digest(bool $dry = false): int
    {
        $due = Ticket::dueSql();
        $tickets = $this->openTickets()->whereNotNull('assigned_to')
            ->selectRaw("assigned_to, SUM(status IN ('Open','In Progress') AND $due < ?) overdue, SUM(status IN ('Open','In Progress') AND $due >= ? AND $due <= ?) today", [now(), now(), now()->endOfDay()])
            ->groupBy('assigned_to')->get()->keyBy('assigned_to');

        $tasks = Task::withoutGlobalScopes()->whereNotIn('status', ['completed', 'cancelled'])->whereNotNull('assigned_to')->whereNotNull('due_date')
            ->selectRaw('assigned_to, SUM(due_date < ?) overdue, SUM(due_date = ?) today', [today(), today()->toDateString()])
            ->groupBy('assigned_to')->get()->keyBy('assigned_to');

        $n = 0;
        foreach (User::where('status', 1)->whereIn('id', $tickets->keys()->merge($tasks->keys())->unique())->get() as $user) {
            $t = $tickets->get($user->id);
            $k = $tasks->get($user->id);
            $parts = array_filter([
                ($t->overdue ?? 0) ? $t->overdue . ' overdue ticket' . ($t->overdue > 1 ? 's' : '') : null,
                ($t->today ?? 0) ? $t->today . ' ticket' . ($t->today > 1 ? 's' : '') . ' due today' : null,
                ($k->overdue ?? 0) ? $k->overdue . ' overdue task' . ($k->overdue > 1 ? 's' : '') : null,
                ($k->today ?? 0) ? $k->today . ' task' . ($k->today > 1 ? 's' : '') . ' due today' : null,
            ]);
            if (!$parts || !$this->claim('support-digest:' . $user->id . ':' . today()->toDateString(), 'support_digest', $dry)) {
                continue;
            }
            $dry || $this->notifier->send([$user], new CrmAlert('Your support work today', implode(' · ', $parts), url('admin/ticket?view=mine'), 'fa-headset', (($t->overdue ?? 0) || ($k->overdue ?? 0)) ? 'warning' : 'info', 'support_digest'));
            $n++;
        }

        return $n;
    }

    // ───────────────────────── helpers ─────────────────────────

    /** Tickets still being worked on (not Solved / Closed), across every branch. */
    private function openTickets()
    {
        return Ticket::withoutGlobalScopes()->whereNotIn('status', ['Solved', 'Closed']);
    }

    /** The people who run support for a ticket: the branch login of its branch, and the owners (Super Admins). */
    private function managers(Ticket $ticket): array
    {
        return $this->notifier->superAdmins()->push($this->notifier->branchAdmin($ticket->vendor_id))->filter()->all();
    }

    private function user($id): ?User
    {
        return $id ? User::where('status', 1)->find($id) : null;
    }

    private function alert(iterable $users, Ticket $ticket, string $title, string $text, string $icon, string $tone, string $category): void
    {
        // Never tell people about their own action
        $users = collect($users)->filter()->reject(fn ($u) => $u->id === auth()->id());
        $this->notifier->send($users, new CrmAlert($title, $text, url('admin/ticket/' . $ticket->id), $icon, $tone, $category));
    }

    private function claim(string $key, string $automation, bool $dry): bool
    {
        return $dry ? !DB::table('automation_logs')->where('key', $key)->exists() : $this->notifier->once($key, $automation);
    }

    private function span(int $minutes): string
    {
        return $minutes >= 2880 ? intdiv($minutes, 1440) . ' days' : ($minutes >= 120 ? intdiv($minutes, 60) . ' hours' : max($minutes, 1) . ' minutes');
    }

    private function safely(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            Log::warning('Ticket automation failed', ['error' => $e->getMessage()]);
        }
    }
}
