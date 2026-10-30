<?php

use App\Enums\MarketingChannel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data only, safe to run again: every utm_campaign already seen on website deals becomes a campaign,
 * those deals are linked to it, and leads without a first touch take it from their earliest website enquiry.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $codes = DB::table('deals')->whereNull('deleted_at')->whereNotNull('utm_campaign')->where('utm_campaign', '!=', '')
            ->selectRaw('LOWER(TRIM(utm_campaign)) as code, MIN(utm_campaign) as name, MIN(created_at) as first_at')
            ->groupByRaw('LOWER(TRIM(utm_campaign))')->get();

        foreach ($codes as $c) {
            if ($c->code === '' || DB::table('marketing_campaigns')->where('code', $c->code)->exists()) {
                continue;
            }
            $source = DB::table('deals')->whereRaw('LOWER(TRIM(utm_campaign)) = ?', [$c->code])->whereNotNull('utm_source')
                ->selectRaw('utm_source, COUNT(*) as n')->groupBy('utm_source')->orderByDesc('n')->value('utm_source');
            DB::table('marketing_campaigns')->insert([
                'name' => mb_substr($c->name, 0, 150), 'code' => mb_substr($c->code, 0, 150),
                'channel' => (MarketingChannel::fromUtmSource($source) ?? MarketingChannel::WEBSITE)->value,
                'start_date' => substr((string) $c->first_at, 0, 10), 'status' => 'active',
                'notes' => 'Created from website enquiries that already carried this campaign code.',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        foreach (DB::table('marketing_campaigns')->get(['id', 'code']) as $c) {
            DB::table('deals')->whereNull('campaign_id')->whereRaw('LOWER(TRIM(utm_campaign)) = ?', [$c->code])->update(['campaign_id' => $c->id]);
        }

        // Leads with no first touch: copy it from their earliest enquiry that has one
        $rows = DB::table('deals as d')->join('leads as l', 'l.id', '=', 'd.lead_id')
            ->whereNull('l.campaign_id')->whereNull('l.utm_source')->whereNull('l.utm_campaign')
            ->where(fn ($q) => $q->whereNotNull('d.utm_source')->orWhereNotNull('d.utm_campaign'))
            ->orderBy('d.id')->get(['d.lead_id', 'd.campaign_id', 'd.utm_source', 'd.utm_medium', 'd.utm_campaign']);
        foreach ($rows->groupBy('lead_id') as $leadId => $list) {
            $first = $list->first();
            DB::table('leads')->where('id', $leadId)->update([
                'campaign_id' => $first->campaign_id, 'utm_source' => $first->utm_source,
                'utm_medium' => $first->utm_medium, 'utm_campaign' => $first->utm_campaign,
            ]);
        }
    }

    public function down(): void
    {
        // Links stay; the campaigns can be ended or deleted in Marketing → Campaigns.
    }
};
