<?php

namespace App\Services;

use App\Classes\SMS;
use App\Models\Setting;
use App\Models\SmsLog;
use Illuminate\Support\Carbon;

/**
 * Questions to the SMS gateway (SSL Wireless): how much credit is left, and was a message delivered.
 *
 * The gateway only answers from the server IPs it has whitelisted, and its reply layout is read
 * defensively here (it is not documented in this project), so every answer carries the raw reply
 * for the screen to show when something cannot be understood.
 */
class SmsGateway
{
    public function __construct(private SMS $sms)
    {
    }

    /** @return array{ok: bool, balance: ?float, raw: string, error: ?string} */
    public function balance(): array
    {
        $raw = (string) $this->sms->balance();
        $json = json_decode($raw, true);

        if (!is_array($json)) {
            return ['ok' => false, 'balance' => null, 'raw' => $raw, 'error' => $raw === '' ? 'No answer from the gateway.' : 'The gateway reply could not be read.'];
        }
        if (strtoupper((string) ($json['status'] ?? '')) === 'FAILED') {
            return ['ok' => false, 'balance' => null, 'raw' => $raw, 'error' => (string) ($json['error_message'] ?? 'The gateway refused the request.')];
        }

        $balance = self::extractBalance($json);

        return ['ok' => $balance !== null, 'balance' => $balance, 'raw' => $raw, 'error' => $balance === null ? 'The reply has no balance figure.' : null];
    }

    /** Ask for the balance and remember it, so the screens can show the last known figure. */
    public function checkAndRecord(): array
    {
        $result = $this->balance();
        if ($result['ok']) {
            Setting::find(1)?->update(['sms_balance_last' => $result['balance'], 'sms_balance_checked_at' => now()]);
        }

        return $result;
    }

    /** First number found under a key that talks about balance or credit, however deep. */
    public static function extractBalance(array $json): ?float
    {
        foreach ($json as $key => $value) {
            if (is_array($value)) {
                if (($found = self::extractBalance($value)) !== null) {
                    return $found;
                }
            } elseif (is_string($key) && preg_match('/balance|credit/i', $key) && is_scalar($value)) {
                $clean = preg_replace('/[^0-9.\-]/', '', (string) $value);
                if ($clean !== '' && is_numeric($clean)) {
                    return (float) $clean;
                }
            }
        }

        return null;
    }

    /** @return array{state: string, detail: string, api_error: bool} state: delivered | failed | pending | unknown */
    public function deliveryStatus(string $csmsId): array
    {
        $raw = (string) $this->sms->status($csmsId);
        $json = json_decode($raw, true);

        if (!is_array($json)) {
            return ['state' => 'unknown', 'detail' => 'No readable answer from the gateway.', 'api_error' => true];
        }
        if (strtoupper((string) ($json['status'] ?? '')) === 'FAILED' && !isset($json['smsinfo'])) {
            return ['state' => 'unknown', 'detail' => (string) ($json['error_message'] ?? 'The gateway refused the request.'), 'api_error' => true];
        }

        return self::mapState($json) + ['api_error' => false];
    }

    /**
     * Read the message-level status from the reply. The top-level "status": "SUCCESS" only says the
     * question was answered, so it is ignored; only entries describing the message itself count.
     *
     * @return array{state: string, detail: string}
     */
    public static function mapState(array $json): array
    {
        $texts = [];
        $collect = function ($node) use (&$collect, &$texts) {
            if (!is_array($node)) {
                return;
            }
            foreach ($node as $key => $value) {
                if (is_array($value)) {
                    $collect($value);
                } elseif (is_string($key) && preg_match('/^(sms_status|dlr_status|delivery_status|message_status|dlr|sms_state|state)$/i', $key) && is_scalar($value)) {
                    $texts[] = trim((string) $value);
                } elseif (is_string($key) && preg_match('/^status_message$/i', $key) && is_scalar($value)) {
                    $texts[] = trim((string) $value);
                }
            }
        };
        foreach (['smsinfo', 'sms_details', 'data', 'message', 'sms'] as $branch) {
            if (isset($json[$branch])) {
                $collect($json[$branch]);
            }
        }
        $texts = array_values(array_filter($texts, fn ($t) => $t !== ''));
        $detail = mb_substr(implode(' · ', array_unique($texts)), 0, 250);

        foreach ($texts as $t) {
            if (preg_match('/undeliver|fail|reject|expire|error|block|invalid|not delivered|blacklist/i', $t)) {
                return ['state' => 'failed', 'detail' => $detail];
            }
        }
        foreach ($texts as $t) {
            if (preg_match('/deliver|dlvrd|completed/i', $t)) {
                return ['state' => 'delivered', 'detail' => $detail];
            }
        }
        foreach ($texts as $t) {
            if (preg_match('/pending|queue|submit|sent|accept|process|enroute|success/i', $t)) {
                return ['state' => 'pending', 'detail' => $detail];
            }
        }

        return ['state' => 'unknown', 'detail' => $detail ?: 'The reply did not describe the message.'];
    }

    /**
     * Ask about recent messages whose delivery is not known yet and save the answers.
     * Stops at the first gateway-level refusal (not whitelisted, wrong token) instead of repeating it for every message.
     *
     * @return array{checked: int, delivered: int, failed: int, pending: int, unknown: int, error: ?string}
     */
    public function refreshDeliveries(int $limit = 200): array
    {
        $out = ['checked' => 0, 'delivered' => 0, 'failed' => 0, 'pending' => 0, 'unknown' => 0, 'error' => null];

        $logs = SmsLog::where('status', 'sent')->whereNotNull('csms_id')
            ->where(fn ($q) => $q->whereNull('delivery_status')->orWhereIn('delivery_status', ['pending', 'unknown']))
            ->where('created_at', '>=', Carbon::now()->subDays(3))
            ->where(fn ($q) => $q->whereNull('delivery_checked_at')->orWhere('delivery_checked_at', '<', Carbon::now()->subMinutes(30)))
            ->orderBy('id')->limit($limit)->get();

        foreach ($logs as $log) {
            $answer = $this->deliveryStatus($log->csms_id);
            if ($answer['api_error']) {
                $out['error'] = $answer['detail'];
                break;
            }
            $log->update(['delivery_status' => $answer['state'], 'delivery_detail' => $answer['detail'] ?: null, 'delivery_checked_at' => now()]);
            $out['checked']++;
            $out[$answer['state']]++;
        }

        return $out;
    }
}
