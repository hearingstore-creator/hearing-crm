<?php

namespace App\Http\Controllers\Api;

use App\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\BlockedContact;
use App\Models\SmsInbound;
use App\Models\SmsLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Replies to our SMS, pushed by the gateway (server-to-server).
 *
 * Switched off until SMS_INBOUND_SECRET is set. The gateway is given the address
 *   https://<crm>/api/v1/sms/inbound?key=<secret>      (or the header X-Sms-Secret: <secret>)
 * and sends the sender's number and the text. Field names vary between gateways, so the usual ones are accepted.
 * Note: a reply can only reach us if the messages go out from a number that can receive them;
 * a masked / alphanumeric sender ID (letters, not digits) cannot receive replies.
 *
 * "STOP" (and a few equivalents, also in Bangla) puts the number on the SMS opt-out list.
 */
class SmsInboundController extends Controller
{
    private const STOP_WORDS = ['stop', 'stopall', 'unsubscribe', 'cancel', 'end', 'quit', 'optout', 'opt out', 'বন্ধ', 'বন্ধ করুন', 'থামুন'];

    public function receive(Request $request): JsonResponse
    {
        $secret = (string) config('sms.inbound_secret');
        abort_if($secret === '', 404);

        $given = (string) ($request->header('X-Sms-Secret') ?: $request->input('key', ''));
        abort_unless($given !== '' && hash_equals($secret, $given), 403);

        $from = $this->first($request, ['msisdn', 'from', 'sender', 'mobile', 'phone', 'number']);
        $text = $this->first($request, ['sms', 'text', 'message', 'body', 'content', 'msg']);
        if ($from === '' || $text === '') {
            return response()->json(['status' => 'ignored', 'reason' => 'missing sender or text'], 422);
        }

        $phone = BlockedContact::normalize(BlockedContact::SMS_OPT_OUT, $from) ?: $from;
        $word = mb_strtolower(trim(preg_replace('/[\p{P}\p{S}]+/u', ' ', $text)));
        $word = trim(preg_replace('/\s+/u', ' ', $word));
        $action = in_array($word, self::STOP_WORDS, true) ? 'stop' : null;

        $digits = substr(preg_replace('/\D/', '', $phone), -10);
        $lastSent = $digits !== '' ? SmsLog::where('phone_number', 'like', '%' . $digits)->latest('id')->value('id') : null;

        SmsInbound::create([
            'phone_number' => mb_substr($phone, 0, 30),
            'message'      => mb_substr($text, 0, 1000),
            'action'       => $action,
            'sms_log_id'   => $lastSent,
            'received_at'  => now(),
        ]);

        if ($action === 'stop') {
            BlockedContact::firstOrCreate(
                ['type' => BlockedContact::SMS_OPT_OUT, 'value' => $phone],
                ['reason' => 'Replied STOP', 'created_by' => null]
            );
        }

        return response()->json(['status' => 'ok', 'action' => $action]);
    }

    private function first(Request $request, array $keys): string
    {
        foreach ($keys as $key) {
            $v = $request->input($key);
            if (is_scalar($v) && trim((string) $v) !== '') {
                return trim((string) $v);
            }
        }

        return '';
    }
}
