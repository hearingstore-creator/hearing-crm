<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\Pipeline;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Pipeline insights for deals CREATED in the chosen period: funnel, time in stage, speed-to-lead,
 * lost reasons, campaign / branch / owner performance, members vs non-members, weighted forecast.
 * Branch users see their branch (Deal scope); professionals their own deals.
 */
class DealReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:deal_reports.index');
    }

    public function index(Request $request): View
    {
        $from = $this->date($request->from, now()->subDays(89));
        $to = $this->date($request->to, now());
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }
        $isHq = !auth()->user()->branchId();
        $group = in_array($request->group, ['branch', 'owner', 'source', 'campaign'], true) ? $request->group : 'campaign';

        $deals = $this->scoped($request, $isHq)
            ->whereBetween('deals.created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->with(['stage:id,name,sort_order,color,probability', 'owner:id,name', 'vendor:id,name', 'lostReason:id,name',
                'lead:id,full_name', 'lead.customer' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'lead_id', 'created_at')])
            ->get();
        $ids = $deals->pluck('id');
        $stages = Pipeline::default()->activeStages()->get(['id', 'name', 'sort_order', 'color', 'probability']);

        $won = $deals->where('status', Deal::WON);
        $lost = $deals->where('status', Deal::LOST);
        $closed = $won->count() + $lost->count();

        return view('admin.deal-report.index', [
            'from' => $from, 'to' => $to, 'group' => $group, 'isHq' => $isHq,
            'branches' => \App\Classes\Helper::branchOptions(),
            'summary' => [
                'deals'    => $deals->count(),
                'won'      => $won->count(),
                'wonValue' => $won->sum('value'),
                'winRate'  => $closed ? round($won->count() / $closed * 100) : null,
                'daysToWin' => $won->count() ? round($won->avg(fn ($d) => $d->created_at->diffInHours($d->won_at) / 24), 1) : null,
                'speed'    => $this->speedToLead($ids),
            ],
            'funnel'    => $this->funnel($deals, $stages),
            'timeInStage' => $this->timeInStage($ids, $stages),
            'lostReasons' => $lost->groupBy(fn ($d) => $d->lostReason->name ?? 'Not given')->map->count()->sortDesc(),
            'breakdown' => $this->breakdown($deals, $group),
            'members'   => $this->members($deals),
            'forecast'  => $this->forecast($request, $isHq, $stages),
            'trials'    => $this->trials($from, $to, $isHq ? null : auth()->user()->branchId()),
        ]);
    }

    private function scoped(Request $request, bool $isHq): Builder
    {
        $q = Deal::query();
        if (auth()->user()->isProfessional()) {
            $q->where('deals.owner_id', auth()->id());
        }
        if ($isHq && $request->filled('branch')) {
            $request->branch === 'hq' ? $q->whereNull('deals.vendor_id') : $q->where('deals.vendor_id', (int) $request->branch);
        }

        return $q;
    }

    /** How many deals reached each stage (furthest stage from history or current stage), then won. */
    private function funnel(Collection $deals, Collection $stages): array
    {
        $order = $stages->pluck('sort_order', 'id');
        $reachedFromHistory = DB::table('lead_status_histories')->whereIn('deal_id', $deals->pluck('id'))->whereNotNull('to_stage_id')
            ->groupBy('deal_id')->selectRaw('deal_id, GROUP_CONCAT(DISTINCT to_stage_id) as stage_ids')->pluck('stage_ids', 'deal_id');

        $reached = $deals->map(function ($d) use ($order, $reachedFromHistory) {
            $orders = collect(explode(',', (string) ($reachedFromHistory[$d->id] ?? '')))->filter()->map(fn ($id) => $order[$id] ?? 0);
            return max($orders->max() ?? 0, $order[$d->stage_id] ?? 0);
        });

        $rows = $stages->map(fn ($s) => ['name' => $s->name, 'color' => $s->color, 'count' => $reached->filter(fn ($o) => $o >= $s->sort_order)->count()]);
        $rows->push(['name' => 'Won', 'color' => '#10b981', 'count' => $deals->where('status', Deal::WON)->count()]);
        $top = max(1, $rows->first()['count'] ?? 1);

        return $rows->map(fn ($r) => $r + ['pct' => round($r['count'] / $top * 100)])->all();
    }

    /** Average days spent in each stage (from consecutive stage history entries). */
    private function timeInStage(Collection $ids, Collection $stages): array
    {
        $rows = DB::table('lead_status_histories')->whereIn('deal_id', $ids)->whereNotNull('to_stage_id')
            ->orderBy('deal_id')->orderBy('created_at')->orderBy('id')->get(['deal_id', 'to_stage_id', 'created_at']);

        $durations = [];
        foreach ($rows->groupBy('deal_id') as $list) {
            $list = $list->values();
            foreach ($list as $i => $h) {
                $next = $list[$i + 1] ?? null;
                if ($next && $next->to_stage_id != $h->to_stage_id) {
                    $durations[$h->to_stage_id][] = Carbon::parse($h->created_at)->diffInHours(Carbon::parse($next->created_at)) / 24;
                }
            }
        }

        return $stages->map(fn ($s) => [
            'name' => $s->name, 'color' => $s->color,
            'days' => !empty($durations[$s->id]) ? round(array_sum($durations[$s->id]) / count($durations[$s->id]), 1) : null,
            'n'    => count($durations[$s->id] ?? []),
        ])->all();
    }

    /**
     * First contact (finished call/visit/SMS/WhatsApp/email) per deal, judged against the deal's own deadline:
     * the first planned call (set with office hours and holidays). Median minutes, % on time, and per owner.
     */
    private function speedToLead(Collection $ids): ?array
    {
        $contact = ['call', 'visit', 'sms', 'whatsapp', 'email'];
        $rows = DB::table('deals as d')
            ->whereIn('d.id', $ids)
            ->leftJoin('users as u', 'u.id', '=', 'd.owner_id')
            ->selectRaw('d.id, d.created_at, u.name as owner')
            ->selectSub(DB::table('lead_follow_ups')->whereColumn('deal_id', 'd.id')->where('status', 'completed')
                ->whereIn('activity_type', $contact)->selectRaw('MIN(COALESCE(completed_at, updated_at))'), 'first_contact')
            ->selectSub(DB::table('lead_follow_ups')->whereColumn('deal_id', 'd.id')->where('activity_type', 'call')
                ->selectRaw('MIN(follow_up_date)'), 'deadline')
            ->get()
            ->filter(fn ($r) => $r->first_contact)
            ->map(function ($r) {
                $created = \Carbon\Carbon::parse($r->created_at);
                $first = \Carbon\Carbon::parse($r->first_contact);
                $deadline = $r->deadline ? \Carbon\Carbon::parse($r->deadline) : $created->copy()->addMinutes(15);

                return ['owner' => $r->owner ?? 'Unassigned', 'mins' => max(0, (int) $created->diffInMinutes($first)), 'onTime' => $first->lte($deadline->max($created->copy()->addMinutes(15)))];
            });

        if ($rows->isEmpty()) {
            return null;
        }

        $median = fn (Collection $c) => $c->sort()->values()[intdiv($c->count(), 2)];
        $pct = fn (Collection $c) => round($c->where('onTime', true)->count() / $c->count() * 100);

        return [
            'median'  => $median($rows->pluck('mins')),
            'onTime'  => $pct($rows),
            'n'       => $rows->count(),
            'byOwner' => $rows->groupBy('owner')->map(fn ($c, $name) => [
                'name' => $name, 'n' => $c->count(), 'median' => $median($c->pluck('mins')), 'onTime' => $pct($c),
            ])->sortBy('onTime')->values(),
        ];
    }

    private function breakdown(Collection $deals, string $group): Collection
    {
        $key = match ($group) {
            'branch'   => fn ($d) => $d->vendor->name ?? 'Head Office',
            'owner'    => fn ($d) => $d->owner->name ?? 'Unassigned',
            'source'   => fn ($d) => \App\Enums\SourceOfEntry::tryFrom((string) $d->source)?->label() ?? 'Not set',
            default    => fn ($d) => $d->utm_campaign ?: ($d->source === 'website' ? 'Website (no campaign)' : 'No campaign'),
        };

        return $deals->groupBy($key)->map(function ($list, $name) {
            $won = $list->where('status', Deal::WON);
            $closed = $won->count() + $list->where('status', Deal::LOST)->count();
            return [
                'name' => $name, 'deals' => $list->count(), 'open' => $list->where('status', Deal::OPEN)->count(),
                'won' => $won->count(), 'lost' => $closed - $won->count(),
                'winRate' => $closed ? round($won->count() / $closed * 100) : null,
                'wonValue' => $won->sum('value'), 'openValue' => $list->where('status', Deal::OPEN)->sum('value'),
            ];
        })->sortByDesc('deals')->values();
    }

    /** Existing members (customer before the deal started) vs new people. */
    private function members(Collection $deals): array
    {
        return $deals->groupBy(fn ($d) => $d->lead?->customer && $d->lead->customer->created_at < $d->created_at ? 'Members' : 'Non-members')
            ->map(function ($list) {
                $won = $list->where('status', Deal::WON);
                $closed = $won->count() + $list->where('status', Deal::LOST)->count();
                return ['deals' => $list->count(), 'winRate' => $closed ? round($won->count() / $closed * 100) : null,
                    'avgWon' => $won->count() ? $won->avg('value') : null];
            })->all();
    }

    /** All open deals now (not limited to the period): value × stage probability. */
    private function forecast(Request $request, bool $isHq, Collection $stages): array
    {
        $rows = $this->scoped($request, $isHq)->open()->selectRaw('deals.stage_id, COUNT(*) c, COALESCE(SUM(deals.value), 0) v')
            ->groupBy('deals.stage_id')->get()->keyBy('stage_id');

        return $stages->map(fn ($s) => [
            'name' => $s->name, 'color' => $s->color, 'probability' => $s->probability,
            'count' => (int) ($rows[$s->id]->c ?? 0), 'value' => (float) ($rows[$s->id]->v ?? 0),
            'weighted' => (float) ($rows[$s->id]->v ?? 0) * $s->probability / 100,
        ])->all();
    }

    /**
     * Trials of the period: per model, how many deals tried it and how many of those were won;
     * and how the trials went (the reasons people did not buy).
     */
    private function trials(Carbon $from, Carbon $to, ?int $branchId): array
    {
        $rows = DB::table('deal_trials as t')->join('deals as d', 'd.id', '=', 't.deal_id')->whereNull('d.deleted_at')
            ->whereBetween('t.created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($branchId, fn ($q) => $q->where('d.vendor_id', $branchId))
            ->get(['t.deal_id', 't.product_label', 't.result', 't.kind', 'd.status']);

        $byModel = $rows->groupBy('product_label')->map(function ($list, $label) {
            $deals = $list->unique('deal_id');
            $won = $deals->where('status', Deal::WON)->count();

            return ['model' => $label, 'tried' => $deals->count(), 'won' => $won, 'rate' => $deals->count() ? round($won / $deals->count() * 100) : null,
                'takeHome' => $list->where('kind', 'take_home')->count()];
        })->sortByDesc('tried')->values()->all();

        $results = $rows->whereNotNull('result')->groupBy('result')->map->count()->sortDesc();

        return ['byModel' => $byModel, 'results' => $results, 'total' => $rows->unique('deal_id')->count(),
            'won' => $rows->unique('deal_id')->where('status', Deal::WON)->count()];
    }

    private function date(?string $value, Carbon $default): Carbon
    {
        try {
            return $value ? Carbon::parse($value) : $default;
        } catch (\Throwable) {
            return $default;
        }
    }
}
