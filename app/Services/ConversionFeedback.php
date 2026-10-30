<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Tell the ad platforms which leads bought, so their algorithms look for buyers rather than form-fillers.
 *  - Meta: Conversions API "Purchase" events (hourly) for sales whose patient came from Facebook / Instagram / WhatsApp,
 *    matched by hashed phone and email, the lead-form lead id and the fbclid. Meta takes events up to 7 days old.
 *  - Google: a CSV of sales with a Google click id (gclid), for Google Ads "offline conversions" — Google can fetch it
 *    on a schedule from a private link, or it can be downloaded and uploaded by hand.
 * Each sale is sent once per platform (conversion_events).
 */
class ConversionFeedback
{
    public function metaReady(): bool
    {
        return (bool) (config('connectors.meta.pixel_id') && config('connectors.meta.capi_token'));
    }

    /** @return array{sent: int, failed: int, skipped?: string} */
    public function sendMeta(int $days = 7): array
    {
        if (!$this->metaReady()) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => 'META_PIXEL_ID / META_CAPI_TOKEN not set'];
        }
        $sales = $this->sales($days)->filter(fn ($s) => $s->meta);
        if ($sales->isEmpty()) {
            return ['sent' => 0, 'failed' => 0];
        }

        $currency = strtoupper((string) (\App\Models\Setting::find(1)?->currency_code ?? 'BDT')) ?: 'BDT';
        $events = $sales->map(function ($s) use ($currency) {
            $user = array_filter([
                'ph' => $s->phone ? [hash('sha256', $s->phone)] : null,
                'em' => $s->email ? [hash('sha256', mb_strtolower(trim($s->email)))] : null,
                'fn' => $s->first_name ? [hash('sha256', mb_strtolower($s->first_name))] : null,
                'external_id' => [hash('sha256', 'customer-' . $s->customer_id)],
                'lead_id' => $s->leadgen_id ? (int) $s->leadgen_id : null,
                'fbc' => $s->fbclid ? 'fb.1.' . Carbon::parse($s->created_at)->getTimestampMs() . '.' . $s->fbclid : null,
            ]);

            return [
                'event_name' => 'Purchase', 'event_time' => Carbon::parse($s->created_at)->timestamp,
                'event_id' => 'sale-' . $s->id, 'action_source' => $s->leadgen_id ? 'system_generated' : 'physical_store',
                'user_data' => $user, 'custom_data' => ['value' => round((float) $s->total_amount, 2), 'currency' => $currency, 'order_id' => (string) $s->sale_invoice_no],
            ];
        })->values()->all();

        $url = 'https://graph.facebook.com/' . config('connectors.meta.graph_version') . '/' . config('connectors.meta.pixel_id') . '/events';
        $response = Http::timeout(20)->post($url, ['data' => $events, 'access_token' => config('connectors.meta.capi_token')]);
        $ok = $response->successful();
        $detail = $ok ? 'events_received: ' . ($response->json('events_received') ?? '?') : ($response->json('error.message') ?? ('HTTP ' . $response->status()));

        foreach ($sales as $s) {
            DB::table('conversion_events')->updateOrInsert(['platform' => 'meta', 'sale_master_id' => $s->id],
                ['status' => $ok ? 'sent' : 'failed', 'detail' => mb_substr($detail, 0, 500), 'updated_at' => now(), 'created_at' => now()]);
        }

        return ['sent' => $ok ? $sales->count() : 0, 'failed' => $ok ? 0 : $sales->count()];
    }

    /** Rows for Google Ads offline conversion import (sales with a gclid, last 90 days). */
    public function googleRows(int $days = 90): array
    {
        $name = config('connectors.google.conversion_name') ?: 'CRM purchase';
        $currency = strtoupper((string) (\App\Models\Setting::find(1)?->currency_code ?? 'BDT')) ?: 'BDT';
        $tz = Carbon::now()->format('P');   // e.g. +06:00

        return $this->sales($days, includeSent: true)->filter(fn ($s) => $s->gclid)->map(fn ($s) => [
            $s->gclid, $name, Carbon::parse($s->created_at)->format('Y-m-d H:i:s') . $tz, round((float) $s->total_amount, 2), $currency,
        ])->values()->all();
    }

    /**
     * Recent sales with what the platforms can match on. A sale's click ids come from the enquiry it closed,
     * else from the patient's first touch.
     */
    private function sales(int $days, bool $includeSent = false): Collection
    {
        $q = DB::table('sale_masters as m')->join('customers as c', 'c.id', '=', 'm.customer_id')->join('leads as l', 'l.id', '=', 'c.lead_id')
            ->leftJoin('deals as d', function ($j) {
                $j->on('d.sale_master_id', '=', 'm.id')->whereNull('d.deleted_at');
            })
            ->whereNull('m.deleted_at')->where('m.created_at', '>=', now()->subDays($days))->where('m.total_amount', '>', 0);
        if (!$includeSent) {
            $q->whereNotExists(fn ($w) => $w->from('conversion_events')->whereColumn('conversion_events.sale_master_id', 'm.id')->where('conversion_events.platform', 'meta')->where('conversion_events.status', 'sent'));
        }

        return $q->get(['m.id', 'm.sale_invoice_no', 'm.total_amount', 'm.created_at', 'c.id as customer_id', 'l.id as lead_id', 'l.full_name', 'l.phone_number', 'l.email',
            'l.source_of_entry', 'l.fbclid as l_fbclid', 'l.gclid as l_gclid', 'd.fbclid as d_fbclid', 'd.gclid as d_gclid', 'd.source as d_source'])
            ->map(function ($s) {
                $leadgen = DB::table('lead_submissions')->where('lead_id', $s->lead_id)->whereIn('source', ['facebook', 'instagram'])
                    ->whereNotNull('external_id')->latest('id')->value('external_id');
                $s->fbclid = $s->d_fbclid ?: $s->l_fbclid;
                $s->gclid = $s->d_gclid ?: $s->l_gclid;
                $s->leadgen_id = $leadgen && ctype_digit((string) $leadgen) ? $leadgen : null;
                $s->meta = $s->fbclid || $s->leadgen_id || in_array($s->d_source ?: $s->source_of_entry, ['facebook', 'instagram', 'whatsapp'], true);
                $digits = preg_replace('/\D/', '', (string) \App\Classes\Helper::normalizePhone((string) $s->phone_number));
                $s->phone = $digits ?: null;
                $s->first_name = trim(explode(' ', trim((string) $s->full_name))[0] ?? '') ?: null;

                return $s;
            });
    }
}
