<?php

namespace App\Services;

use App\Enums\SmsAutomationTrigger;
use App\Models\BlockedContact;
use App\Models\Setting;
use App\Models\SmsAutomation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The one door for every automated SMS. Each send:
 *  – uses the active automation's text from the SMS Center (nothing is sent if it is inactive),
 *  – is sent at most once per $onceKey,
 *  – respects the daily cap (config crm.automation_sms_daily_cap),
 *  – skips opted-out numbers for promotional triggers (service SMS still go out),
 *  – is logged in sms_logs and counted on the automation.
 */
class SmsAutomationService
{
    public function __construct(private SmsService $sms, private CrmNotifier $notifier)
    {
    }

    /** Returns true when an SMS was sent. Never throws – an SMS problem must not break the business action. */
    public function fire(SmsAutomationTrigger $trigger, ?string $phone, string $recipientType, int $recipientId, array $vars, ?string $onceKey = null): bool
    {
        try {
            $automation = $this->active($trigger);
            if (!$automation || !$phone) {
                return false;
            }
            if ($trigger->isPromotional() && self::isOptedOut($phone)) {
                return false;
            }
            if (!$this->underDailyCap()) {
                Log::warning('Automation SMS daily cap reached', ['trigger' => $trigger->value]);
                return false;
            }
            if ($onceKey && !$this->notifier->once($onceKey, 'sms_' . $trigger->value)) {
                return false;
            }

            $vars += ['company_phone' => Setting::find(1)?->phone ?? ''];
            $this->sms->sendAndLogSms($phone, $this->sms->replaceVariables($automation->message_body, $vars), $recipientType, $recipientId, null, \App\Enums\SmsType::AUTOMATED, $trigger->value);
            $automation->increment('sent_count');

            return true;
        } catch (\Throwable $e) {
            Log::error('Automation SMS failed', ['trigger' => $trigger->value, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /** Same as fire(), but waits until the surrounding DB transaction commits (nothing is sent on rollback). */
    public function fireAfterCommit(SmsAutomationTrigger $trigger, ?string $phone, string $recipientType, int $recipientId, array $vars, ?string $onceKey = null): void
    {
        DB::afterCommit(fn () => $this->fire($trigger, $phone, $recipientType, $recipientId, $vars, $onceKey));
    }

    /** Read-only listener (SaleMaster created): SMS on the customer's very first invoice. */
    public function onSaleCreated(\App\Models\SaleMaster $sale): void
    {
        if (!$sale->customer_id || !$this->active(SmsAutomationTrigger::CUSTOMER_FIRST_SALE)) {
            return;
        }
        if (\App\Models\SaleMaster::withoutGlobalScopes()->where('customer_id', $sale->customer_id)->count() !== 1) {
            return;
        }
        $customer = \App\Models\Customer::withoutGlobalScopes()->with('lead')->find($sale->customer_id);
        if ($customer?->lead) {
            $this->fireAfterCommit(SmsAutomationTrigger::CUSTOMER_FIRST_SALE, $customer->lead->phone_number, 'customer', (int) $customer->id, [
                'name' => $customer->lead->full_name, 'customer_name' => $customer->lead->full_name, 'customer_id' => $customer->customer_uid,
            ], 'first-sale:' . $customer->id);
        }
    }

    /** Read-only listener (Customer updated): SMS when the membership tier moves up. */
    public function onCustomerUpdated(\App\Models\Customer $customer): void
    {
        if (!$customer->wasChanged('membership_tier_id') || !$customer->membership_tier_id || !$this->active(SmsAutomationTrigger::MEMBERSHIP_UPGRADE)) {
            return;
        }
        $old = $customer->getOriginal('membership_tier_id');
        $tiers = DB::table('membership_tiers')->whereIn('id', array_filter([$old, $customer->membership_tier_id]))->pluck('min_purchase', 'id');
        $new = $tiers[$customer->membership_tier_id] ?? null;
        if ($new === null || ($old && (float) $new <= (float) ($tiers[$old] ?? 0))) {
            return;   // not an upgrade
        }
        $lead = $customer->lead()->withoutGlobalScopes()->first();
        $this->fireAfterCommit(SmsAutomationTrigger::MEMBERSHIP_UPGRADE, $lead?->phone_number, 'customer', (int) $customer->id, [
            'name' => $lead?->full_name, 'customer_name' => $lead?->full_name, 'customer_id' => $customer->customer_uid,
            'tier' => DB::table('membership_tiers')->where('id', $customer->membership_tier_id)->value('name'),
        ], 'tier-up:' . $customer->id . ':' . $customer->membership_tier_id);
    }

    /** A payment was recorded for a patient (Receive and checkout payments). Fires after the transaction commits. */
    public function onPaymentReceived(\App\Models\CustomerLedger $entry): void
    {
        if (!$entry->customer_id || (float) $entry->credit <= 0 || !$this->active(SmsAutomationTrigger::PAYMENT_RECEIVED)) {
            return;
        }
        $customer = \App\Models\Customer::withoutGlobalScopes()->with('lead')->find($entry->customer_id);
        if (!$customer?->lead?->phone_number) {
            return;
        }
        $this->fireAfterCommit(SmsAutomationTrigger::PAYMENT_RECEIVED, $customer->lead->phone_number, 'customer', (int) $customer->id, [
            'name' => $customer->lead->full_name, 'amount' => number_format((float) $entry->credit, 2),
            'invoice' => $entry->ref_invoice_no, 'date' => \Illuminate\Support\Carbon::parse($entry->date ?: now())->format('d M Y'),
        ], 'payment:' . $entry->id);
    }

    /** A sale credited commission to a doctor / referrer (once per sale and person, even if the sale is edited). */
    public function onReferralEarned(?\App\Models\Doctor $doctor, float $amount, string $invoiceNo, int $saleId): void
    {
        if (!$doctor || $amount <= 0 || !$doctor->contact_number || !$this->active(SmsAutomationTrigger::REFERRAL_EARNED)) {
            return;
        }
        $this->fireAfterCommit(SmsAutomationTrigger::REFERRAL_EARNED, $doctor->contact_number, ($doctor->referrer_type ?? 'doctor') === 'doctor' ? 'doctor' : 'referrer', (int) $doctor->id, [
            'name' => $doctor->name, 'amount' => number_format($amount, 2), 'invoice' => $invoiceNo,
        ], 'ref-earned:' . $saleId . ':' . $doctor->id);
    }

    /** A commission payment was made to a doctor / referrer. */
    public function onReferralPaid(\App\Models\DoctorLedger $entry): void
    {
        $doctor = \App\Models\Doctor::withoutGlobalScopes()->find($entry->doctor_id);
        if (!$doctor || (float) $entry->debit <= 0 || !$doctor->contact_number || !$this->active(SmsAutomationTrigger::REFERRAL_PAID)) {
            return;
        }
        $this->fireAfterCommit(SmsAutomationTrigger::REFERRAL_PAID, $doctor->contact_number, ($doctor->referrer_type ?? 'doctor') === 'doctor' ? 'doctor' : 'referrer', (int) $doctor->id, [
            'name' => $doctor->name, 'amount' => number_format((float) $entry->debit, 2), 'voucher' => $entry->ref_invoice_no,
            'date' => \Illuminate\Support\Carbon::parse($entry->date ?: now())->format('d M Y'),
        ], 'ref-paid:' . $entry->id);
    }

    public function active(SmsAutomationTrigger $trigger): ?SmsAutomation
    {
        // The SMS Center mode is the master control: off stops everything, essential keeps only the must-have messages
        $mode = self::mode();
        if ($mode === 'off' || ($mode === 'essential' && !$trigger->isEssential())) {
            return null;
        }

        return SmsAutomation::where('trigger_type', $trigger->value)->where('is_active', true)->first();
    }

    /** all | essential | off */
    public static function mode(): string
    {
        $mode = (string) (Setting::find(1)?->sms_automation_mode ?? 'all');

        return in_array($mode, ['all', 'essential', 'off'], true) ? $mode : 'all';
    }

    public function underDailyCap(): bool
    {
        return DB::table('sms_logs')->where('type', 'automated')->whereDate('created_at', today())->count()
            < (int) config('crm.automation_sms_daily_cap');
    }

    /** Numbers that asked not to receive promotional SMS (managed in SMS Center → Opt-out). */
    public static function isOptedOut(?string $phone): bool
    {
        $value = BlockedContact::normalize('phone', $phone);

        return $value && BlockedContact::where('type', BlockedContact::SMS_OPT_OUT)->where('value', $value)->exists();
    }
}
