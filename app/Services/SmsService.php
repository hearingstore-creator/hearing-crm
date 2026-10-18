<?php

namespace App\Services;

use App\Classes\SMS;
use App\Enums\SmsCampaignStatus;
use App\Enums\SmsStatus;
use App\Enums\SmsType;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Setting;
use App\Models\SmsCampaign;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected SMS $sms;

    public function __construct(SMS $sms)
    {
        $this->sms = $sms;
    }

    /**
     * Send a bulk campaign to all recipients using dynamic bulk SMS API (one call per 500 recipients).
     * Audience Builder campaigns (recipient_type leads|customers) use their saved filter_criteria;
     * older template-category audiences (lead, customer, member, warranty, appointment) still work.
     */
    public function sendBulkCampaign(SmsCampaign $campaign): void
    {
        $recipients = $this->campaignRecipients($campaign);
        $optedOut = \App\Models\BlockedContact::where('type', \App\Models\BlockedContact::SMS_OPT_OUT)->pluck('value')->flip();

        $sentCount = 0;
        $failedCount = 0;
        $skipped = 0;
        $smsData = [];
        $recipientData = [];
        $seen = [];

        foreach ($recipients as $recipient) {
            $phone = $recipient->phone_number ?? $recipient->lead?->phone_number;
            if (empty($phone)) {
                $failedCount++;
                continue;
            }
            $normalized = \App\Models\BlockedContact::normalize('phone', $phone);
            if (isset($optedOut[$normalized]) || isset($seen[$normalized])) {
                $skipped++;   // opted out, or the same number twice
                continue;
            }
            $seen[$normalized] = true;

            $data = $this->buildRecipientData($recipient);
            $message = $this->replaceVariables($campaign->message_body, $data);
            $csmsId = 'BULK-' . $campaign->id . '-' . $recipient->id . '-' . time();

            $smsData[] = [
                'msisdn'  => $phone,
                'text'    => $message,
                'csms_id' => $csmsId,
            ];

            $recipientData[] = [
                'recipient_type' => $recipient instanceof Customer ? 'customer' : 'lead',
                'recipient_id' => $recipient->id,
                'phone_number' => $phone,
                'message'      => $message,
                'csms_id'      => $csmsId,
            ];
        }

        $campaign->update([
            'total_recipients' => count($smsData),
            'status' => SmsCampaignStatus::SENDING->value,
        ]);

        foreach (array_chunk($smsData, 500, true) as $chunk) {
            $result = $this->sendDynamicBulkSms(array_values($chunk));
            $smsInfo = collect($result['smsinfo'] ?? [])->keyBy('csms_id');

            foreach (array_intersect_key($recipientData, $chunk) as $item) {
                $info = $smsInfo->get($item['csms_id']);

                if ($info && ($info['sms_status'] ?? null) === 'SUCCESS') {
                    $logStatus = SmsStatus::SENT->value;
                    $logError = null;
                    $sentCount++;
                } else {
                    $logStatus = SmsStatus::FAILED->value;
                    $logError = $info['status_message'] ?? ($result['error'] ?? null);
                    $failedCount++;
                }

                SmsLog::create([
                    'sms_campaign_id' => $campaign->id,
                    'recipient_type' => $item['recipient_type'],
                    'recipient_id' => $item['recipient_id'],
                    'phone_number' => $item['phone_number'],
                    'message' => $item['message'],
                    'type' => SmsType::BULK->value,
                    'status' => $logStatus,
                    'gateway_response' => $result['response'] ?? null,
                    'error_message' => $logError,
                    'sent_at' => now(),
                    'campaign_name' => $campaign->name,
                ]);
            }
        }

        $campaign->update([
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'status' => SmsCampaignStatus::COMPLETED->value,
            'sent_at' => now(),
        ]);

        if ($skipped) {
            Log::info("SMS campaign {$campaign->id}: {$skipped} opted-out/duplicate numbers skipped");
        }
    }

    /** Recipients of a campaign: Audience Builder filters, or a legacy audience type. */
    public function campaignRecipients(SmsCampaign $campaign): Collection
    {
        $type = $campaign->recipient_type ?? '';
        if ($type === 'lead_ids') {   // Deals list → Send SMS: exactly the people that were ticked
            return Lead::withoutGlobalScopes()->whereNull('deleted_at')
                ->whereIn('id', $campaign->filter_criteria['lead_ids'] ?? [])->get();
        }
        if (in_array($type, ['leads', 'customers'], true)) {
            $criteria = $campaign->filter_criteria ?? [];
            $filters = array_is_list($criteria) ? $criteria : [];   // {audience_type: ..} = no filters
            $service = app(SmsAudienceFilterService::class);

            return $type === 'customers' ? $service->filterCustomers($filters) : $service->filterLeads($filters);
        }

        return $this->getRecipientsByAudience($campaign->filter_criteria['audience_type'] ?? $type);
    }

    /** Follow-up call for every successfully sent campaign SMS (lead-based follow-ups). */
    public function createCampaignFollowUps(SmsCampaign $campaign): void
    {
        $sentLogs = SmsLog::where('sms_campaign_id', $campaign->id)->where('status', SmsStatus::SENT->value)->get();
        $followUpDate = now()->addDays($campaign->follow_up_days ?: 3);
        $mode = $campaign->follow_up_mode ?: 'phone_call';

        foreach ($sentLogs as $log) {
            $leadId = match ($log->recipient_type) {
                'lead' => $log->recipient_id,
                'customer' => Customer::withoutGlobalScopes()->whereKey($log->recipient_id)->value('lead_id'),
                default => null,
            };
            if (!$leadId) {
                continue;
            }
            \App\Models\LeadFollowUp::create([
                'lead_id' => $leadId,
                'assigned_user' => $campaign->follow_up_assigned_user,
                'follow_up_date' => $followUpDate,
                'follow_up_mode' => [$mode],
                'follow_up_notes' => "Follow up on SMS campaign: \"{$campaign->name}\"",
            ]);
            $log->update(['follow_up_created' => true]);
        }
    }

    /**
     * Replace {{variable}} placeholders in template
     */
    public function replaceVariables(string $template, array $data): string
    {
        return preg_replace_callback('/\{\{(\w+)\}\}/', function ($matches) use ($data) {
            $key = $matches[1];
            return $data[$key] ?? $matches[0];
        }, $template);
    }

    /**
     * Get recipients by audience type (derived from template category)
     */
    public function getRecipientsByAudience(string $audienceType): Collection
    {
        return match ($audienceType) {
            'warranty' => Customer::with('lead', 'membershipTier', 'saleMasters.saleDetails.product')
                ->whereHas('lead', fn($q) => $q->whereNotNull('phone_number'))
                ->get()
                ->filter(fn($c) => $this->hasExpiringWarranty($c)),

            'lead' => Lead::where('lead_status', '!=', 'won')
                ->whereNotNull('phone_number')
                ->get(),

            'customer' => Customer::with('lead', 'membershipTier')
                ->whereHas('lead', fn($q) => $q->whereNotNull('phone_number'))
                ->get(),

            'appointment' => Customer::with('lead', 'membershipTier')
                ->whereHas('appointments', fn($q) => $q->whereBetween('appointment_date', [
                    Carbon::now()->startOfWeek(),
                    Carbon::now()->endOfWeek(),
                ]))
                ->get(),

            'member' => Customer::with('lead', 'membershipTier')
                ->whereNotNull('membership_tier_id')
                ->whereHas('lead', fn($q) => $q->whereNotNull('phone_number'))
                ->get(),

            default => collect(),
        };
    }

    /**
     * Check if customer has products approaching warranty expiry (within 30 days)
     */
    protected function hasExpiringWarranty(Customer $customer): bool
    {
        $threshold = Carbon::now()->addDays(30)->toDateString();

        foreach ($customer->saleMasters as $saleMaster) {
            foreach ($saleMaster->saleDetails as $saleDetail) {
                $warrantyMonths = $saleDetail->product->warranty_sales_months ?? null;
                if ($warrantyMonths && $saleMaster->date) {
                    $expiryDate = $saleMaster->date->copy()->addMonths($warrantyMonths)->toDateString();
                    if ($expiryDate >= Carbon::now()->toDateString() && $expiryDate <= $threshold) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Get the nearest upcoming warranty expiry date for a customer
     */
    protected function getNearestWarrantyExpiry(Customer $customer): string
    {
        $now = Carbon::now()->toDateString();
        $nearest = null;

        foreach ($customer->saleMasters as $saleMaster) {
            foreach ($saleMaster->saleDetails as $saleDetail) {
                $warrantyMonths = $saleDetail->product->warranty_sales_months ?? null;
                if ($warrantyMonths && $saleMaster->date) {
                    $expiryDate = $saleMaster->date->copy()->addMonths($warrantyMonths);
                    if ($expiryDate->toDateString() >= $now && ($nearest === null || $expiryDate->lt($nearest))) {
                        $nearest = $expiryDate;
                    }
                }
            }
        }

        return $nearest?->format('d M Y') ?? '';
    }

    /**
     * Get analytics data
     */
    public function getAnalyticsData(string $period = '30_days'): array
    {
        $startDate = match ($period) {
            '7_days' => Carbon::now()->subDays(7),
            '30_days' => Carbon::now()->subDays(30),
            '90_days' => Carbon::now()->subDays(90),
            '12_months' => Carbon::now()->subMonths(12),
            default => Carbon::now()->subDays(30),
        };

        $totalSent = SmsLog::where('created_at', '>=', $startDate)->count();
        $successfulSent = SmsLog::where('created_at', '>=', $startDate)
            ->where('status', SmsStatus::SENT->value)->count();
        $failedSent = SmsLog::where('created_at', '>=', $startDate)
            ->where('status', SmsStatus::FAILED->value)->count();
        $deliveryRate = $totalSent > 0 ? round(($successfulSent / $totalSent) * 100, 1) : 0;

        $bulkCampaigns = SmsCampaign::where('created_at', '>=', $startDate)->count();

        $messagesByType = SmsLog::where('created_at', '>=', $startDate)
            ->selectRaw("type, COUNT(*) as count")
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        return [
            'total_sent' => $totalSent,
            'successful_sent' => $successfulSent,
            'failed_sent' => $failedSent,
            'delivery_rate' => $deliveryRate,
            'bulk_campaigns' => $bulkCampaigns,
            'messages_by_type' => $messagesByType,
            'period' => $period,
        ];
    }

    /**
     * Build data array for variable replacement from a model
     */
    protected function buildRecipientData($recipient): array
    {
        $companyPhone = Setting::find(1)?->phone ?? '';

        if ($recipient instanceof Customer) {
            $lead = $recipient->lead;
            $nextAppointment = $recipient->appointments()
                ->where('appointment_date', '>=', now()->toDateString())
                ->orderBy('appointment_date')
                ->first();

            // Calculate nearest upcoming warranty expiry date
            $warrantyExpiryDate = $this->getNearestWarrantyExpiry($recipient);

            return [
                'name'                => $lead->full_name ?? '',
                'company_phone'       => $companyPhone,
                'customer_id'         => $recipient->customer_uid ?? '',
                'warranty_expiry_date' => $warrantyExpiryDate,
                'appointment_date'    => $nextAppointment?->appointment_date?->format('d M Y') ?? '',
                'tier'                => $recipient->membershipTier->name ?? '',
                'points'              => (string) floor($recipient->getAvailableRedeemPoints()),
            ];
        }

        if ($recipient instanceof Lead) {
            return [
                'name'          => $recipient->full_name ?? '',
                'company_phone' => $companyPhone,
            ];
        }

        return [];
    }

    /**
     * Send single SMS via gateway and log the result
     */
    public function sendAndLogSms(string $phone, string $message, string $recipientType, int $recipientId, string $csmsId = null): void
    {
        $csmsId = $csmsId ?? 'SMS-' . time() . '-' . rand(1000, 9999);

        try {
            $response = $this->sms->singleSms($phone, $message, $csmsId);

            $decoded = $response ? json_decode($response, true) : null;
            $success = $decoded && isset($decoded['status']) && $decoded['status'] === 'SUCCESS';

            SmsLog::create([
                'recipient_type'  => $recipientType,
                'recipient_id'    => $recipientId,
                'phone_number'    => $phone,
                'message'         => $message,
                'type'            => SmsType::AUTOMATED->value,
                'status'          => $success ? SmsStatus::SENT->value : SmsStatus::FAILED->value,
                'gateway_response' => $response,
                'error_message'   => !$success ? ($decoded['error_message'] ?? 'Unknown error') : null,
                'sent_at'         => now(),
            ]);
        } catch (\Exception $e) {
            SmsLog::create([
                'recipient_type'  => $recipientType,
                'recipient_id'    => $recipientId,
                'phone_number'    => $phone,
                'message'         => $message,
                'type'            => SmsType::AUTOMATED->value,
                'status'          => SmsStatus::FAILED->value,
                'error_message'   => $e->getMessage(),
                'sent_at'         => now(),
            ]);
        }
    }

    /**
     * Send dynamic bulk SMS via gateway (one call, personalized messages per recipient)
     */
    protected function sendDynamicBulkSms(array $smsData): array
    {
        try {
            $response = $this->sms->dynamicBulkSms($smsData);

            if ($response) {
                $decoded = json_decode($response, true);
                if (isset($decoded['status']) && $decoded['status'] === 'SUCCESS') {
                    return [
                        'success'  => true,
                        'response'  => $response,
                        'smsinfo'   => $decoded['smsinfo'] ?? [],
                    ];
                }
                return [
                    'success' => false,
                    'response' => $response,
                    'smsinfo'  => $decoded['smsinfo'] ?? [],
                    'error'    => $decoded['error_message'] ?? 'Unknown error',
                ];
            }

            return ['success' => false, 'error' => 'No response from gateway', 'smsinfo' => []];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'smsinfo' => []];
        }
    }
}
