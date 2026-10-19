<?php

namespace App\Console\Commands;

use App\Enums\ActivityType;
use App\Enums\SmsAutomationTrigger;
use App\Models\Deal;
use App\Models\LeadFollowUp;
use App\Models\LostReason;
use App\Models\User;
use App\Notifications\CrmAlert;
use App\Services\CrmNotifier;
use App\Services\DealService;
use App\Services\SmsAutomationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Lead automations, run by the scheduler (routes/console.php). Every action is logged in automation_logs,
 * so running a task twice never repeats an alert, SMS or deal.
 *
 *   php artisan crm:automations sla          – website lead not called within its SLA → alert owner + Super Admin
 *   php artisan crm:automations appointments – SMS reminder for tomorrow's appointments
 *   php artisan crm:automations reengage     – "Not ready" lost 90+ days ago → new deal + call
 *   php artisan crm:automations points       – SMS 7 days before membership points expire
 *   php artisan crm:automations warranty     – SMS 30 days before a product warranty ends
 *   php artisan crm:automations digest       – 8 AM summary in the bell (per owner + Super Admin), plus each person's tickets and tasks
 *   php artisan crm:automations tickets      – overdue tickets, tickets with no update, Solved tickets that close by themselves
 *   php artisan crm:automations tasks        – tasks due tomorrow or overdue
 */
class CrmAutomations extends Command
{
    private const TASKS = ['sla', 'appointments', 'reengage', 'points', 'warranty', 'digest', 'tickets', 'tasks'];

    protected $signature = 'crm:automations {task : sla|appointments|reengage|points|warranty|digest|tickets|tasks} {--dry : show what would happen, change nothing}';
    protected $description = 'Run one lead automation';

    public function __construct(private CrmNotifier $notifier, private DealService $deals, private SmsAutomationService $sms)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $task = $this->argument('task');
        if (!in_array($task, self::TASKS, true)) {
            $this->error('Unknown task.');
            return self::INVALID;
        }

        $count = $this->{$task}();
        $this->info(ucfirst($task) . ': ' . $count . ($this->option('dry') ? ' (dry run)' : ''));

        return self::SUCCESS;
    }

    /** Website leads whose first call is overdue. */
    private function sla(): int
    {
        $overdue = LeadFollowUp::with(['deal.lead:id,full_name,phone_number', 'assignedUser'])
            ->where('status', 'pending')->where('activity_type', ActivityType::CALL->value)
            ->where('follow_up_notes', 'like', 'Website lead – call within%')
            ->where('follow_up_date', '<', now())
            ->where('follow_up_date', '>', now()->subDays(2))   // older ones are covered by the digest
            ->get();

        $n = 0;
        foreach ($overdue as $a) {
            if (!$a->deal || $a->deal->status !== Deal::OPEN || !$this->claim("sla:activity:{$a->id}", 'sla')) {
                continue;
            }
            $late = (int) \Illuminate\Support\Carbon::parse($a->follow_up_date)->diffInMinutes(now());
            $this->notifier->send($this->notifier->superAdmins()->push($a->assignedUser), new CrmAlert(
                'Website lead waiting ' . $late . ' min',
                ($a->deal->lead->full_name ?? 'Lead') . ' · ' . ($a->deal->lead->phone_number ?? '') . ' has not been called yet.',
                url('admin/deals?deal=' . $a->deal_id),
                'fa-stopwatch', 'danger', 'web_lead_sla',
            ));
            $n++;
        }

        return $n;
    }

    /** Tomorrow's scheduled/confirmed appointments → SMS, using the active "Appointment Reminder" automation text. */
    private function appointments(): int
    {
        if (!$this->sms->active(SmsAutomationTrigger::APPOINTMENT_REMINDER)) {
            $this->line('No active "Appointment Reminder" SMS automation – skipped.');
            return 0;
        }

        $tomorrow = Carbon::today('Asia/Dhaka')->addDay()->format('Y-m-d');

        $rows = DB::table('appointments as a')
            ->leftJoin('customers as c', 'c.id', '=', 'a.customer_id')
            ->leftJoin('leads as l', function ($j) {
                $j->on('l.id', '=', DB::raw('COALESCE(a.lead_id, c.lead_id)'));
            })
            ->leftJoin('appointment_services as s', 's.id', '=', 'a.service_id')
            ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
            ->leftJoin('users as b', 'b.id', '=', 'a.branch_id')
            ->whereNull('a.deleted_at')
            ->whereIn('a.status', ['scheduled', 'booked', 'confirmed'])
            ->where('a.is_time_unknown', 0)
            ->where('a.needs_reschedule', 0)
            ->whereDate('a.appointment_date', $tomorrow)
            ->get([
                'a.id', 'a.appointment_date', 'a.start_time', 'a.end_time',
                'l.id as lead_id', 'l.full_name', 'l.phone_number',
                's.name as service', 'u.name as professional',
                'b.company_name as branch_name', 'b.address as branch_address'
            ]);

        $n = 0;
        foreach ($rows as $r) {
            if (!$r->phone_number) {
                continue;
            }

            $timeStr = $r->start_time ? date('g:i A', strtotime($r->start_time)) : '';
            if ($timeStr && $r->end_time) {
                $timeStr .= ' - ' . date('g:i A', strtotime($r->end_time));
            }

            $branch = $r->branch_name ?: 'Universal Hearing Center';

            $n += (int) $this->sendSms(SmsAutomationTrigger::APPOINTMENT_REMINDER, "appt-sms:{$r->id}", $r->phone_number, 'lead', (int) $r->lead_id, [
                'name'             => $r->full_name,
                'customer_name'    => $r->full_name,
                'date'             => date('d M Y', strtotime($r->appointment_date)),
                'appointment_date' => date('d M Y', strtotime($r->appointment_date)),
                'time'             => $timeStr,
                'branch'           => $branch,
                'branch_name'      => $branch,
                'branch_address'   => $r->branch_address ?? '',
                'service'          => $r->service ?? 'Clinical Service',
                'professional'     => $r->professional ?? 'Specialist',
            ]);
        }

        return $n;
    }

    /** "Not ready" deals lost 90+ days ago, person has no open deal → reopen the conversation as a new deal. */
    private function reengage(): int
    {
        $reasonIds = LostReason::where('name', 'like', '%not ready%')->pluck('id');
        if ($reasonIds->isEmpty()) {
            return 0;
        }

        $lost = Deal::withoutGlobalScopes()->whereNull('deleted_at')->with('lead')
            ->where('status', Deal::LOST)->whereIn('lost_reason_id', $reasonIds)
            ->where('lost_at', '<=', now()->subDays(90))->where('lost_at', '>', now()->subDays(365))
            ->whereNotExists(fn ($q) => $q->from('deals as d2')->whereColumn('d2.lead_id', 'deals.lead_id')
                ->whereNull('d2.deleted_at')->where(fn ($w) => $w->where('d2.status', Deal::OPEN)->orWhereColumn('d2.id', '>', 'deals.id')))
            ->get();

        $n = 0;
        foreach ($lost as $old) {
            if (!$old->lead || !$this->claim("reengage:deal:{$old->id}", 'reengage')) {
                continue;
            }
            $deal = $this->deals->openNewDeal($old->lead, [
                'vendor_id' => $old->vendor_id, 'owner_id' => $old->owner_id, 'inquiry_for' => $old->inquiry_for,
                'value' => $old->value, 'source' => $old->source, 'temperature' => 'cold',
            ]);
            $this->deals->addActivity($deal, ActivityType::CALL, now()->setTime(11, 0)->toDateTimeString(),
                'Re-engage: was "Not ready" on ' . $old->lost_at->format('d M Y') . ($old->lost_note ? ' – ' . $old->lost_note : ''));
            $n++;
        }

        return $n;
    }

    /** Points expiring in exactly 7 days → one SMS per customer per expiry date. */
    private function points(): int
    {
        if (!$this->sms->active(SmsAutomationTrigger::POINTS_EXPIRING)) {
            $this->line('No active "Points Expiring" SMS automation – skipped.');
            return 0;
        }

        $date = today()->addDays(7)->toDateString();
        $rows = DB::table('reward_ledgers as r')
            ->join('customers as c', 'c.id', '=', 'r.customer_id')
            ->join('leads as l', 'l.id', '=', 'c.lead_id')
            ->whereNull('r.deleted_at')->whereNull('c.deleted_at')->where('r.points', '>', 0)
            ->whereDate('r.expiry_date', $date)
            ->groupBy('c.id', 'l.full_name', 'l.phone_number')
            ->get(['c.id', 'l.full_name', 'l.phone_number', DB::raw('SUM(r.points) as points')]);

        $n = 0;
        foreach ($rows as $r) {
            // Read-only: the membership module stays the source of truth for the balance
            $available = (float) optional(\App\Models\Customer::withoutGlobalScopes()->find($r->id))->getAvailableRedeemPoints();
            $points = min((float) $r->points, $available);
            if ($points <= 0 || !$r->phone_number) {
                continue;
            }
            $n += (int) $this->sendSms(SmsAutomationTrigger::POINTS_EXPIRING, "points-sms:{$r->id}:{$date}", $r->phone_number, 'customer', (int) $r->id, [
                'name' => $r->full_name, 'customer_name' => $r->full_name, 'points' => number_format($points),
                'expiry_date' => date('d M Y', strtotime($date)), 'date' => date('d M Y', strtotime($date)),
            ]);
        }

        return $n;
    }

    /** Product warranty ending in exactly 30 days → one SMS per sale item (sale date + product warranty months). */
    private function warranty(): int
    {
        if (!$this->sms->active(SmsAutomationTrigger::WARRANTY_EXPIRING)) {
            $this->line('No active "Warranty Expiring" SMS automation – skipped.');
            return 0;
        }

        $target = today()->addDays(30);
        $rows = DB::table('sale_details as d')
            ->join('sale_masters as m', 'm.id', '=', 'd.sale_master_id')
            ->join('products as p', 'p.id', '=', 'd.product_id')
            ->join('customers as c', 'c.id', '=', 'm.customer_id')
            ->join('leads as l', 'l.id', '=', 'c.lead_id')
            ->whereNull('m.deleted_at')->whereNull('c.deleted_at')->whereNull('d.deleted_at')
            ->where('p.warranty_sales_months', '>', 0)
            ->where('m.date', '>=', today()->subYears(10))
            ->get(['d.id', 'm.date', 'p.model as product', 'p.warranty_sales_months', 'c.id as customer_id', 'c.customer_uid', 'l.full_name', 'l.phone_number']);

        $n = 0;
        foreach ($rows as $r) {
            $expiry = \Illuminate\Support\Carbon::parse($r->date)->addMonths((int) $r->warranty_sales_months);
            if (!$expiry->isSameDay($target) || !$r->phone_number) {
                continue;
            }
            $n += (int) $this->sendSms(SmsAutomationTrigger::WARRANTY_EXPIRING, "warranty-sms:{$r->id}", $r->phone_number, 'customer', (int) $r->customer_id, [
                'name' => $r->full_name, 'customer_name' => $r->full_name, 'customer_id' => $r->customer_uid,
                'product' => $r->product, 'warranty_expiry_date' => $expiry->format('d M Y'), 'date' => $expiry->format('d M Y'),
            ]);
        }

        return $n;
    }

    /** Morning summary: each owner gets their day; Super Admins also get the company picture. */
    private function digest(): int
    {
        if (!$this->claim('digest:' . today()->toDateString(), 'digest')) {
            return 0;
        }

        $pending = LeadFollowUp::where('status', 'pending')->whereNotNull('deal_id')
            ->whereHas('deal', fn ($q) => $q->withoutGlobalScopes()->where('status', Deal::OPEN))
            ->selectRaw('assigned_user, SUM(follow_up_date < ?) overdue, SUM(DATE(follow_up_date) = ?) today', [today(), today()->toDateString()])
            ->groupBy('assigned_user')->get()->keyBy('assigned_user');

        $rotting = Deal::withoutGlobalScopes()->whereNull('deleted_at')->open()->with('stage')->get()
            ->filter->isRotting()->countBy('owner_id');

        $n = 0;
        $owners = User::where('status', 1)->whereIn('id', $pending->keys()->merge($rotting->keys())->filter()->unique())->get();
        foreach ($owners as $u) {
            $p = $pending->get($u->id);
            $parts = array_filter([
                ($p->overdue ?? 0) ? $p->overdue . ' overdue' : null,
                ($p->today ?? 0) ? $p->today . ' due today' : null,
                ($rotting[$u->id] ?? 0) ? $rotting[$u->id] . ' stuck deal' . ($rotting[$u->id] > 1 ? 's' : '') : null,
            ]);
            if ($parts) {
                $this->notifier->send([$u], new CrmAlert('Good morning – your day', implode(' · ', $parts), url('admin/deals?owner=me'), 'fa-sun', ($p->overdue ?? 0) ? 'warning' : 'info', 'daily_digest'));
                $n++;
            }
        }

        $y = [today()->subDay()->startOfDay(), today()->startOfDay()];
        $company = array_filter([
            DB::table('deals')->whereNull('deleted_at')->whereBetween('created_at', $y)->count() . ' new deals',
            DB::table('deals')->whereNull('deleted_at')->whereBetween('won_at', $y)->count() . ' won',
            DB::table('deals')->whereNull('deleted_at')->whereBetween('lost_at', $y)->count() . ' lost',
            ($w = DB::table('lead_submissions')->whereBetween('created_at', $y)->where('status', '!=', 'spam')->count()) ? $w . ' from website' : null,
            ($r = $rotting->sum()) ? $r . ' stuck' : null,
        ]);
        $this->notifier->send($this->notifier->superAdmins(), new CrmAlert('Yesterday at a glance', implode(' · ', $company), url('admin/deal-reports'), 'fa-chart-line', 'info', 'company_daily'));

        // Each person's tickets and tasks (own line, own once-a-day key)
        $n += app(\App\Services\TicketAutomation::class)->digest((bool) $this->option('dry'));

        return $n + 1;
    }

    // ------------------------------------------------------------------

    /** Tickets: overdue alerts, no-update reminders, auto-close of Solved tickets. */
    private function tickets(): int
    {
        $support = app(\App\Services\TicketAutomation::class);
        $dry = (bool) $this->option('dry');

        return $support->overdue($dry) + $support->stale($dry) + $support->autoClose($dry);
    }

    /** Tasks due tomorrow / overdue. */
    private function tasks(): int
    {
        return app(\App\Services\TicketAutomation::class)->tasks((bool) $this->option('dry'));
    }

    private function claim(string $key, string $automation): bool
    {
        return $this->option('dry') ? !DB::table('automation_logs')->where('key', $key)->exists() : $this->notifier->once($key, $automation);
    }

    /** SMS through the central service (active text, opt-out, daily cap, once per key). Dry run only prints. */
    private function sendSms(SmsAutomationTrigger $trigger, string $key, string $phone, string $type, int $id, array $vars): bool
    {
        if ($this->option('dry')) {
            if (DB::table('automation_logs')->where('key', $key)->exists()) {
                return false;
            }
            $this->line("SMS → $phone");
            return true;
        }

        return $this->sms->fire($trigger, $phone, $type, $id, $vars, $key);
    }
}
