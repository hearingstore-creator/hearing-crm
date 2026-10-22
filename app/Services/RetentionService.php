<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Enums\SmsAutomationTrigger;
use App\Models\Lead;
use App\Models\NpsResponse;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Keeping patients (run daily by crm:automations retention):
 *  - upgrade: the latest hearing aid of a patient is old enough → optional new "Upgrade" deal with a call, and the SMS automation
 *  - service: each year on the purchase date → the yearly service SMS automation
 *  - survey:  some days after delivery → a short 0–10 question by SMS link; low scores alert the owner
 * Every message is sent at most once (automation_logs), and only while its SMS automation is switched on.
 */
class RetentionService
{
    /** Same idea as the dashboard: products in a hearing-aid category. */
    private const DEVICE_CATEGORY_PATTERN = '/hearing\s*aid|\bbte\b|\bite\b|\bitc\b|\biic\b|\bcic\b|\bric\b|\brie\b/i';

    public function __construct(private SmsAutomationService $sms, private CrmNotifier $notifier)
    {
    }

    /** @return array{upgrades: int, deals: int, service: int, surveys: int} */
    public function run(): array
    {
        [$upgrades, $deals] = $this->upgrades();

        return ['upgrades' => $upgrades, 'deals' => $deals, 'service' => $this->service(), 'surveys' => $this->surveys()];
    }

    public static function deviceProductIds(): array
    {
        $cats = DB::table('categories')->whereNull('deleted_at')->get(['id', 'category_name'])
            ->filter(fn ($c) => preg_match(self::DEVICE_CATEGORY_PATTERN, (string) $c->category_name))->pluck('id')->all();

        return $cats ? DB::table('products')->whereNull('deleted_at')->whereIn('category_id', $cats)->pluck('id')->all() : [];
    }

    /** Latest device sale per patient: sale id, date, model, customer, lead, phone, name, seller. */
    private function latestDevices(): Collection
    {
        $ids = self::deviceProductIds();
        if (!$ids) {
            return collect();
        }

        return DB::table('sale_details as sd')->join('sale_masters as m', 'm.id', '=', 'sd.sale_master_id')
            ->join('customers as c', 'c.id', '=', 'm.customer_id')->join('leads as l', 'l.id', '=', 'c.lead_id')
            ->leftJoin('products as p', 'p.id', '=', 'sd.product_id')
            ->whereNull('m.deleted_at')->whereNull('sd.deleted_at')->whereIn('sd.product_id', $ids)
            ->orderBy('m.date')->orderBy('m.id')
            ->get(['m.id as sale_id', 'm.date', 'm.vendor_id', 'm.created_by', 'p.model', 'c.id as customer_id', 'l.id as lead_id', 'l.full_name', 'l.phone_number'])
            ->groupBy('customer_id')->map->last()->values();
    }

    /** @return array{0: int, 1: int} SMS sent, deals opened */
    public function upgrades(): array
    {
        $setting = Setting::find(1);
        $months = max(6, (int) ($setting->upgrade_after_months ?? 36));
        $openDeal = (bool) ($setting->upgrade_opens_deal ?? false);
        $smsOn = (bool) $this->sms->active(SmsAutomationTrigger::UPGRADE_DUE);
        if (!$openDeal && !$smsOn) {
            return [0, 0];
        }

        $sent = 0;
        $opened = 0;
        foreach ($this->latestDevices() as $d) {
            $age = (int) Carbon::parse($d->date)->diffInMonths(now());
            if ($age < $months) {
                continue;
            }
            $years = (int) floor($age / 12);

            if ($openDeal && app(CrmNotifier::class)->once('upgrade-deal:' . $d->sale_id, 'upgrade')) {
                $hasOpen = DB::table('deals')->whereNull('deleted_at')->where('lead_id', $d->lead_id)->where('status', 'open')->exists();
                if (!$hasOpen && ($lead = Lead::withoutGlobalScopes()->find($d->lead_id))) {
                    $deals = app(DealService::class);
                    $deal = $deals->openNewDeal($lead, ['inquiry_for' => ['hearing_aid'], 'vendor_id' => $d->vendor_id, 'source' => 'other']
                        + ($d->created_by ? ['owner_id' => $d->created_by] : []));
                    $deals->addActivity($deal, ActivityType::CALL, DealService::dueInOfficeHours(60)->toDateTimeString(),
                        'Upgrade: ' . ($d->model ?: 'hearing aid') . ' bought ' . Carbon::parse($d->date)->format('d M Y') . " ({$years} years ago) – invite for a check and new models");
                    $opened++;
                }
            }

            if ($smsOn && $d->phone_number) {
                $sent += (int) $this->sms->fire(SmsAutomationTrigger::UPGRADE_DUE, $d->phone_number, 'customer', (int) $d->customer_id, [
                    'name' => $d->full_name, 'model' => $d->model ?: 'hearing aid', 'years' => (string) max(1, $years),
                ], 'upgrade-sms:' . $d->sale_id);
            }
        }

        return [$sent, $opened];
    }

    /** Yearly service reminder on the anniversary of each patient's latest device purchase. */
    public function service(): int
    {
        if (!$this->sms->active(SmsAutomationTrigger::SERVICE_DUE)) {
            return 0;
        }
        $today = today();
        $n = 0;
        foreach ($this->latestDevices() as $d) {
            $bought = Carbon::parse($d->date);
            if (!$d->phone_number || $bought->year >= $today->year || $bought->month !== $today->month || $bought->day !== $today->day) {
                continue;
            }
            $n += (int) $this->sms->fire(SmsAutomationTrigger::SERVICE_DUE, $d->phone_number, 'customer', (int) $d->customer_id,
                ['name' => $d->full_name], 'service-sms:' . $d->sale_id . ':' . $today->year);
        }

        return $n;
    }

    /** Survey link some days after delivery (sales marked delivered in the Delivery Hub, or sale date when never marked). */
    public function surveys(): int
    {
        if (!$this->sms->active(SmsAutomationTrigger::NPS_SURVEY)) {
            return 0;
        }
        $days = max(1, (int) (Setting::find(1)?->nps_after_days ?? 7));
        $from = now()->subDays($days + 3)->startOfDay();
        $to = now()->subDays($days)->endOfDay();

        $sales = DB::table('sale_masters as m')->join('customers as c', 'c.id', '=', 'm.customer_id')->join('leads as l', 'l.id', '=', 'c.lead_id')
            ->whereNull('m.deleted_at')
            ->where(fn ($q) => $q->whereBetween('m.delivered_at', [$from, $to])
                ->orWhere(fn ($w) => $w->whereNull('m.delivered_at')->whereBetween('m.date', [$from->toDateString(), $to->toDateString()])))
            ->whereNotExists(fn ($q) => $q->from('nps_responses')->whereColumn('nps_responses.customer_id', 'm.customer_id')->where('nps_responses.created_at', '>=', now()->subDays(90)))
            ->get(['m.id', 'm.customer_id', 'm.vendor_id', 'l.full_name', 'l.phone_number']);

        $n = 0;
        foreach ($sales->unique('customer_id') as $s) {
            if (!$s->phone_number) {
                continue;
            }
            $survey = NpsResponse::create(['token' => Str::random(48), 'sale_master_id' => $s->id, 'customer_id' => $s->customer_id, 'vendor_id' => $s->vendor_id]);
            $ok = $this->sms->fire(SmsAutomationTrigger::NPS_SURVEY, $s->phone_number, 'customer', (int) $s->customer_id, [
                'name' => $s->full_name, 'company' => Setting::find(1)?->company_name ?? '', 'link' => url('s/' . $survey->token),
            ], 'nps-sms:' . $s->id);
            $ok ? $survey->update(['sent_at' => now()]) : $survey->delete();
            $n += (int) $ok;
        }

        return $n;
    }

    /** Answer from the survey page; a low score tells the owner / branch straight away. */
    public function answer(NpsResponse $survey, int $score, ?string $comment): void
    {
        $survey->update(['score' => max(0, min(10, $score)), 'comment' => $comment ? mb_substr(strip_tags($comment), 0, 1000) : null, 'answered_at' => now()]);
        if (NpsResponse::group($survey->score) !== 'detractor') {
            return;
        }
        $name = DB::table('customers as c')->join('leads as l', 'l.id', '=', 'c.lead_id')->where('c.id', $survey->customer_id)->value('l.full_name');
        $this->notifier->send($this->notifier->superAdmins()->push($this->notifier->branchAdmin($survey->vendor_id)), new \App\Notifications\CrmAlert(
            'Unhappy patient: ' . $survey->score . '/10',
            ($name ?? 'A patient') . ($survey->comment ? ': "' . Str::limit($survey->comment, 140) . '"' : ' gave a low score.') . ' Please call them.',
            url('admin/customers/' . $survey->customer_id), 'fa-frown', 'danger', 'deal_assigned'
        ));
    }

    /** NPS for a period: promoters % − detractors %. */
    public static function summary(Carbon $from, Carbon $to, ?int $branchId = null): array
    {
        $rows = NpsResponse::whereNotNull('answered_at')->whereBetween('answered_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($branchId, fn ($q) => $q->where('vendor_id', $branchId))->latest('answered_at')->get();
        $n = $rows->count();
        $promoters = $rows->filter(fn ($r) => $r->score >= 9)->count();
        $detractors = $rows->filter(fn ($r) => $r->score <= 6)->count();
        $sent = NpsResponse::whereNotNull('sent_at')->whereBetween('sent_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($branchId, fn ($q) => $q->where('vendor_id', $branchId))->count();

        return [
            'answers' => $n, 'sent' => $sent,
            'nps' => $n ? (int) round(($promoters - $detractors) / $n * 100) : null,
            'promoters' => $promoters, 'passives' => $n - $promoters - $detractors, 'detractors' => $detractors,
            'comments' => $rows->whereNotNull('comment')->take(8)->map(fn ($r) => ['score' => $r->score, 'comment' => $r->comment, 'date' => $r->answered_at->format('d M')])->values()->all(),
        ];
    }
}
