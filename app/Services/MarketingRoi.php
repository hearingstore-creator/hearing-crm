<?php

namespace App\Services;

use App\Enums\MarketingChannel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Marketing return: spend, enquiries, appointments, trials, sales, revenue and gross profit per channel,
 * campaign, branch or month, and the ratios built from them.
 *
 * Who gets the credit (touch):
 *   last  – the enquiry (deal) the sale came from; a sale without a deal falls back to the patient's first touch
 *   first – how the patient first reached us (lead)
 * A sale still without a source, but with a doctor / referrer on it, counts for the "Doctor / referrer" channel.
 * Anything else is "Unknown / direct", shown openly.
 *
 * Basis:
 *   period – sales made in the period against the spend of the period
 *   cohort – what the enquiries of the period bought, whenever they bought it (hearing-aid decisions take weeks)
 *
 * Acquisition cost = ad spend + referral commission on the attributed sales.
 */
class MarketingRoi
{
    private Collection $campaigns;
    private array $codes = [];

    public function __construct(
        private Carbon $from,
        private Carbon $to,
        private string $group = 'channel',     // channel | campaign | branch | month
        private string $basis = 'period',      // period | cohort
        private string $touch = 'last',        // last | first
        private ?int $branchId = null,          // null = all branches
    ) {
        $this->from = $from->copy()->startOfDay();
        $this->to = $to->copy()->endOfDay();
        $this->campaigns = DB::table('marketing_campaigns')->get(['id', 'name', 'code', 'channel', 'vendor_id'])->keyBy('id');
        foreach ($this->campaigns as $c) {
            $this->codes[mb_strtolower((string) $c->code)] = $c->id;
        }
    }

    /** @return array{rows: array, total: array, quality: array, unattributed: array} */
    public function build(): array
    {
        $rows = [];
        $add = function (string $key, string $label, array $values) use (&$rows) {
            $rows[$key] ??= ['key' => $key, 'label' => $label] + array_fill_keys(['spend', 'enquiries', 'appointments', 'trials', 'sales', 'revenue', 'gross_profit', 'commission'], 0.0);
            foreach ($values as $k => $v) {
                $rows[$key][$k] += $v;
            }
        };

        foreach ($this->spend() as $s) {
            [$key, $label] = $this->groupOf($s->campaign_id ? ['campaign_id' => (int) $s->campaign_id, 'channel' => $this->campaigns[$s->campaign_id]->channel ?? 'other'] : ['channel' => 'other'], $s->vendor_id, Carbon::parse($s->month));
            $add($key, $label, ['spend' => (float) $s->amount]);
        }

        [$enquiries, $reached, $leadOf] = $this->enquiries();
        foreach ($enquiries as $e) {
            [$key, $label] = $this->groupOf($e['attr'], $e['vendor_id'], $e['created_at']);
            $add($key, $label, [
                'enquiries'    => $e['count'],
                'appointments' => $reached[$e['deal_ids_key']]['appointment'] ?? 0,
                'trials'       => $reached[$e['deal_ids_key']]['trial'] ?? 0,
            ]);
        }

        foreach ($this->sales($enquiries, $leadOf) as $s) {
            [$key, $label] = $this->groupOf($s['attr'], $s['vendor_id'], $s['month']);
            $add($key, $label, ['sales' => 1, 'revenue' => $s['revenue'], 'gross_profit' => $s['gross_profit'], 'commission' => $s['commission']]);
        }

        $rows = collect($rows)->map(fn ($r) => $this->ratios($r))
            ->sortByDesc(fn ($r) => [$r['spend'] + $r['revenue'], $r['enquiries']])->values()->all();
        if ($this->group === 'month') {
            usort($rows, fn ($a, $b) => strcmp($a['key'], $b['key']));
        }

        $total = ['key' => 'total', 'label' => 'Total'] + array_fill_keys(['spend', 'enquiries', 'appointments', 'trials', 'sales', 'revenue', 'gross_profit', 'commission'], 0.0);
        foreach ($rows as $r) {
            foreach (['spend', 'enquiries', 'appointments', 'trials', 'sales', 'revenue', 'gross_profit', 'commission'] as $k) {
                $total[$k] += $r[$k];
            }
        }

        $known = collect($enquiries)->filter(fn ($e) => $e['attr']['channel'] !== MarketingChannel::UNKNOWN->value)->sum('count');
        $all = collect($enquiries)->sum('count');

        return [
            'rows'  => $rows,
            'total' => $this->ratios($total),
            'quality' => ['enquiries' => (int) $all, 'with_source' => (int) $known, 'pct' => $all ? (int) round($known / $all * 100) : null],
            'unattributed' => collect($enquiries)->filter(fn ($e) => $e['attr']['channel'] === MarketingChannel::UNKNOWN->value)->take(50)->map(fn ($e) => $e['who'])->values()->all(),
        ];
    }

    private function ratios(array $r): array
    {
        $cost = $r['spend'] + $r['commission'];
        $div = fn ($a, $b) => $b > 0 ? round($a / $b, 2) : null;

        return $r + [
            'acquisition_cost' => round($cost, 2),
            'cost_per_enquiry' => $div($r['spend'], $r['enquiries']),
            'cost_per_appointment' => $div($r['spend'], $r['appointments']),
            'cost_per_sale'    => $div($cost, $r['sales']),
            'roas'             => $div($r['revenue'], $cost),
            'poas'             => $div($r['gross_profit'], $cost),
            'enquiry_to_sale'  => $r['enquiries'] > 0 ? round($r['sales'] / $r['enquiries'] * 100, 1) : null,
        ];
    }

    // ── grouping ─────────────────────────────────────────────────────────

    /** @return array{0: string, 1: string} */
    private function groupOf(array $attr, $vendorId, Carbon $date): array
    {
        return match ($this->group) {
            'campaign' => $this->campaignGroup($attr),
            'branch'   => ['b:' . ($vendorId ?: 0), $this->branchName($vendorId)],
            'month'    => [$date->format('Y-m'), $date->format('M Y')],
            default    => ['ch:' . $attr['channel'], MarketingChannel::tryFrom($attr['channel'])?->label() ?? ucfirst($attr['channel'])],
        };
    }

    private function campaignGroup(array $attr): array
    {
        if (!empty($attr['campaign_id']) && isset($this->campaigns[$attr['campaign_id']])) {
            return ['c:' . $attr['campaign_id'], $this->campaigns[$attr['campaign_id']]->name];
        }
        if (!empty($attr['utm_campaign'])) {
            return ['u:' . mb_strtolower($attr['utm_campaign']), $attr['utm_campaign'] . ' (code not set up as a campaign)'];
        }
        $label = MarketingChannel::tryFrom($attr['channel'])?->label() ?? ucfirst($attr['channel']);

        return ['nc:' . $attr['channel'], $label . ' – no campaign'];
    }

    private function branchName($vendorId): string
    {
        static $names = null;
        $names ??= DB::table('users')->where('type', 'vendor')->pluck('name', 'id');

        return $vendorId ? ($names[$vendorId] ?? 'Branch #' . $vendorId) : 'Head office';
    }

    // ── attribution ──────────────────────────────────────────────────────

    /** campaign → utm source → form source; in that order. */
    private function attribute(?int $campaignId, ?string $utmCampaign, ?string $utmSource, ?string $source): array
    {
        $campaignId = $campaignId ?: ($utmCampaign !== null && $utmCampaign !== '' ? ($this->codes[mb_strtolower(trim($utmCampaign))] ?? null) : null);
        if ($campaignId && isset($this->campaigns[$campaignId])) {
            return ['campaign_id' => $campaignId, 'channel' => $this->campaigns[$campaignId]->channel, 'utm_campaign' => null];
        }
        $channel = MarketingChannel::fromUtmSource($utmSource) ?? MarketingChannel::fromSource($source) ?? MarketingChannel::UNKNOWN;

        return ['campaign_id' => null, 'channel' => $channel->value, 'utm_campaign' => $utmCampaign ?: null];
    }

    // ── data ─────────────────────────────────────────────────────────────

    private function spend(): Collection
    {
        return DB::table('marketing_spends')
            ->whereBetween('month', [$this->from->copy()->startOfMonth()->toDateString(), $this->to->copy()->startOfMonth()->toDateString()])
            ->when($this->branchId, fn ($q) => $q->where('vendor_id', $this->branchId))
            ->get(['campaign_id', 'amount', 'vendor_id', 'month']);
    }

    /**
     * Enquiries of the period with their source and how far they got.
     * last touch: one row per deal created in the period; first touch: one row per lead created in the period.
     */
    private function enquiries(): array
    {
        // Appointment / trial are steps of the default (hearing care) pipeline; deals in other pipelines count as
        // enquiries and sales, never as appointments or trials (their stage orders would not compare)
        $defaultId = \App\Models\Pipeline::default()->id;
        $stageOrder = DB::table('pipeline_stages')->where('pipeline_id', $defaultId)->pluck('sort_order', 'id');
        $keyOrder = DB::table('pipeline_stages')->where('pipeline_id', $defaultId)->whereNotNull('status_key')->pluck('sort_order', 'status_key');
        $apptAt = $keyOrder['counsel'] ?? $keyOrder['store_appointment'] ?? PHP_INT_MAX;
        $trialAt = $keyOrder['trial'] ?? PHP_INT_MAX;

        $base = DB::table('deals as d')->join('leads as l', 'l.id', '=', 'd.lead_id')
            ->whereNull('d.deleted_at')->whereNull('l.deleted_at')
            ->when($this->branchId, fn ($q) => $q->where('d.vendor_id', $this->branchId));
        $cols = ['d.id', 'd.lead_id', 'd.vendor_id', 'd.created_at', 'd.status', 'd.stage_id', 'd.campaign_id', 'd.utm_campaign', 'd.utm_source', 'd.source',
            'l.created_at as lead_created_at', 'l.full_name', 'l.campaign_id as l_campaign_id', 'l.utm_campaign as l_utm_campaign', 'l.utm_source as l_utm_source', 'l.source_of_entry',
            'd.owner_id'];

        $deals = $this->touch === 'first'
            ? (clone $base)->whereBetween('l.created_at', [$this->from, $this->to])->get($cols)
            : (clone $base)->whereBetween('d.created_at', [$this->from, $this->to])->get($cols);

        $history = DB::table('lead_status_histories')->whereIn('deal_id', $deals->pluck('id'))->whereNotNull('to_stage_id')
            ->get(['deal_id', 'to_stage_id'])->groupBy('deal_id');
        $owners = DB::table('users')->whereIn('id', $deals->pluck('owner_id')->filter()->unique())->pluck('name', 'id');

        $progress = function ($d) use ($history, $stageOrder) {
            if ($d->status === 'won') {
                return PHP_INT_MAX;
            }
            $orders = collect($history[$d->id] ?? [])->map(fn ($h) => $stageOrder[$h->to_stage_id] ?? 0);

            return max($orders->max() ?? 0, $stageOrder[$d->stage_id] ?? 0);
        };

        $rows = [];
        $reached = [];
        $leadOf = [];   // deal id => enquiry row key (for cohort sales)
        $groupBy = $this->touch === 'first' ? 'lead_id' : 'id';
        foreach ($deals->groupBy($groupBy) as $k => $list) {
            $d = $list->sortBy('id')->first();
            $attr = $this->touch === 'first'
                ? $this->attribute($d->l_campaign_id, $d->l_utm_campaign, $d->l_utm_source, $d->source_of_entry)
                : $this->attribute($d->campaign_id, $d->utm_campaign, $d->utm_source, $d->source ?: $d->source_of_entry);
            if ($this->touch === 'first' && $attr['channel'] === MarketingChannel::UNKNOWN->value) {
                // no first touch recorded: fall back to the first enquiry's source
                $attr = $this->attribute($d->campaign_id, $d->utm_campaign, $d->utm_source, $d->source);
            }
            $max = $list->map($progress)->max();
            $key = $groupBy . ':' . $k;
            $reached[$key] = ['appointment' => (int) ($max >= $apptAt), 'trial' => (int) ($max >= $trialAt)];
            foreach ($list as $one) {
                $leadOf[$one->id] = $key;
            }
            $rows[$key] = [
                'deal_ids_key' => $key, 'count' => 1, 'attr' => $attr, 'vendor_id' => $d->vendor_id,
                'created_at' => Carbon::parse($this->touch === 'first' ? $d->lead_created_at : $d->created_at),
                'lead_id' => $d->lead_id,
                'who' => ['lead_id' => $d->lead_id, 'name' => $d->full_name, 'owner' => $owners[$d->owner_id] ?? '—', 'date' => Carbon::parse($d->created_at)->format('d M Y')],
            ];
        }

        return [$rows, $reached, $leadOf];
    }

    /** Sales with their source, net revenue, gross profit and referral commission. */
    private function sales(array $enquiries, array $leadOf): array
    {
        $q = DB::table('sale_masters as s')->whereNull('s.deleted_at')
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->leftJoin('leads as l', 'l.id', '=', 'c.lead_id')
            ->when($this->branchId, fn ($q) => $q->where('s.vendor_id', $this->branchId));

        if ($this->basis === 'cohort') {
            if ($this->touch === 'first') {
                $q->whereIn('c.lead_id', collect($enquiries)->pluck('lead_id')->unique()->all() ?: [0]);
            } else {
                $q->whereIn('s.id', DB::table('deals')->whereIn('id', array_keys($leadOf) ?: [0])->whereNotNull('sale_master_id')->pluck('sale_master_id')->all() ?: [0]);
            }
        } else {
            $q->whereBetween('s.date', [$this->from->toDateString(), $this->to->toDateString()]);
        }

        $sales = $q->get(['s.id', 's.date', 's.vendor_id', 's.total_amount', 's.reference_by', 's.reference_amount',
            'l.id as lead_id', 'l.campaign_id as l_campaign_id', 'l.utm_campaign as l_utm_campaign', 'l.utm_source as l_utm_source', 'l.source_of_entry']);
        if ($sales->isEmpty()) {
            return [];
        }
        $ids = $sales->pluck('id')->all();

        $dealOf = DB::table('deals')->whereIn('sale_master_id', $ids)->whereNull('deleted_at')
            ->orderByDesc('id')->get(['id', 'sale_master_id', 'created_at', 'campaign_id', 'utm_campaign', 'utm_source', 'source'])->keyBy('sale_master_id');
        $cost = DB::table('sale_details')->whereIn('sale_master_id', $ids)
            ->selectRaw('sale_master_id, SUM(qty * COALESCE(purchase_price, 0)) as cost')->groupBy('sale_master_id')->pluck('cost', 'sale_master_id');
        $returns = DB::table('sale_returns')->whereIn('sale_master_id', $ids)->whereNull('deleted_at')->where('status', 'completed')
            ->selectRaw('sale_master_id, SUM(credit_note_amount - COALESCE(due_reduced_amount, 0)) as refunded, SUM(COALESCE(doctor_commission_adjusted, 0)) as commission_back')
            ->groupBy('sale_master_id')->get()->keyBy('sale_master_id');
        $returnedCost = DB::table('sale_return_details as rd')->join('sale_returns as r', 'r.id', '=', 'rd.sale_return_id')
            ->join('sale_details as sd', 'sd.id', '=', 'rd.sale_detail_id')
            ->whereIn('r.sale_master_id', $ids)->whereNull('r.deleted_at')->where('r.status', 'completed')
            ->selectRaw('r.sale_master_id, SUM(rd.return_qty * COALESCE(sd.purchase_price, 0)) as cost')->groupBy('r.sale_master_id')->pluck('cost', 'r.sale_master_id');

        $out = [];
        foreach ($sales as $s) {
            $deal = $dealOf[$s->id] ?? null;
            $first = $this->attribute($s->l_campaign_id, $s->l_utm_campaign, $s->l_utm_source, $s->source_of_entry);
            $attr = ($this->touch === 'last' && $deal)
                ? $this->attribute($deal->campaign_id, $deal->utm_campaign, $deal->utm_source, $deal->source ?: $s->source_of_entry)
                : $first;
            if ($this->touch === 'last' && $deal && $attr['channel'] === MarketingChannel::UNKNOWN->value) {
                $attr = $first;
            }
            if (in_array($attr['channel'], [MarketingChannel::UNKNOWN->value, MarketingChannel::OTHER->value], true) && !$attr['campaign_id'] && $s->reference_by) {
                $attr = ['campaign_id' => null, 'channel' => MarketingChannel::DOCTOR->value, 'utm_campaign' => null];
            }

            $ret = $returns[$s->id] ?? null;
            $revenue = (float) $s->total_amount - (float) ($ret->refunded ?? 0);
            $cogs = (float) ($cost[$s->id] ?? 0) - (float) ($returnedCost[$s->id] ?? 0);

            // cohort: month of the enquiry; period: month of the sale
            $month = $this->basis === 'cohort' && $deal ? Carbon::parse($deal->created_at) : Carbon::parse($s->date);

            $out[] = [
                'attr' => $attr, 'vendor_id' => $s->vendor_id, 'month' => $month,
                'revenue' => round($revenue, 2),
                'gross_profit' => round($revenue - $cogs, 2),
                'commission' => round(max(0, (float) $s->reference_amount - (float) ($ret->commission_back ?? 0)), 2),
            ];
        }

        return $out;
    }
}
