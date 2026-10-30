<?php
namespace App\Classes;

class SMS {
    
    /**
     * @param $msisdn
     * @param $messageBody
     * @param $csmsId 
     */
    public function singleSms($msisdn, $messageBody, $csmsId)
    {
        if ($this->driver() !== 'sslwireless') {
            return $this->other('singleSms', [$msisdn, $messageBody, $csmsId]);
        }
        $params = [
            "api_token" => config('sms.api_token'),
            "sid" => config('sms.sender_id'),
            "msisdn" => $msisdn,
            "sms" => $messageBody,
            "csms_id" => $csmsId
        ];
        $url = trim(config('sms.api_url'), '/')."/api/v3/send-sms";
        $params = json_encode($params);
    
        return $this->callApi($url, $params);
    }
    

    /**
     * Bulk SMS via SSLWireless
     * @param array $msisdn - array of phone numbers
     * @param string $messageBody
     * @param string $batchCsmsId - unique batch ID
     */
    public function bulkSms(array $msisdn, $messageBody, $batchCsmsId)
    {
        if ($this->driver() !== 'sslwireless') {
            return $this->other('bulkSms', [$msisdn, $messageBody, $batchCsmsId]);
        }
        $params = [
            "api_token" => config('sms.api_token'),
            "sid" => config('sms.sender_id'),
            "msisdn" => $msisdn,
            "sms" => $messageBody,
            "batch_csms_id" => $batchCsmsId
        ];
        $url = trim(config('sms.api_url'), '/')."/api/v3/send-sms/bulk";
        return $this->callApi($url, json_encode($params));
    }

    /**
     * Dynamic Bulk SMS via SSLWireless — one API call with per-recipient personalized messages
     * @param array $smsData - array of ["msisdn"=>"...", "text"=>"...", "csms_id"=>"..."]
     */
    public function dynamicBulkSms(array $smsData)
    {
        if ($this->driver() !== 'sslwireless') {
            return $this->other('dynamicBulkSms', [$smsData]);
        }
        $params = [
            "api_token" => config('sms.api_token'),
            "sid"       => config('sms.sender_id'),
            "sms"       => $smsData,
        ];
        $url = trim(config('sms.api_url'), '/')."/api/v3/send-sms/dynamic";
        return $this->callApi($url, json_encode($params));
    }

    /** Remaining credit on the SSL Wireless account (POST /api/v3/balance). */
    public function balance()
    {
        if ($this->driver() !== 'sslwireless') {
            return $this->other('balance', []);
        }
        $params = ["api_token" => config('sms.api_token'), "sid" => config('sms.sender_id')];
        $url = trim(config('sms.api_url'), '/') . "/api/v3/balance";

        return $this->callApi($url, json_encode($params));
    }

    /** Delivery status of one message by the csms_id we sent it with (POST /api/v3/sms-status). */
    public function status($csmsId)
    {
        if ($this->driver() !== 'sslwireless') {
            return $this->other('status', [$csmsId]);
        }
        $params = ["api_token" => config('sms.api_token'), "sid" => config('sms.sender_id'), "csms_id" => $csmsId];
        $url = trim(config('sms.api_url'), '/') . "/api/v3/sms-status";

        return $this->callApi($url, json_encode($params));
    }

    public function callApi($url, $params)
    {
        $ch = curl_init(); // Initialize cURL
        curl_setopt($ch, CURLOPT_URL, $url);
        // Verify the gateway's certificate (turn off only via SMS_VERIFY_SSL=false where PHP has no CA bundle)
        $verify = (bool) config('crm.sms_verify_ssl', true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verify ? 2 : 0);
        // Never let a slow gateway hang a page or a job
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($params),
            'accept:application/json'
        ));
    
        $response = curl_exec($ch);
    
        curl_close($ch);
    
        return $response;
    }
    // ── other providers ──────────────────────────────────────────────────
    // Answers are shaped like SSL Wireless replies, so the rest of the CRM (logs, delivery reports, balance) works the same.

    protected function driver(): string
    {
        return (string) (config('sms.driver') ?: 'sslwireless');
    }

    protected function other(string $method, array $args)
    {
        return match ($this->driver()) {
            'twilio' => $this->twilio($method, $args),
            default => $this->logOnly($method, $args),
        };
    }

    protected function logOnly(string $method, array $args)
    {
        \Illuminate\Support\Facades\Log::info('SMS (log driver) ' . $method, ['args' => $args]);

        return match ($method) {
            'balance' => json_encode(['status' => 'FAILED', 'error_message' => 'The log driver has no balance']),
            'status' => json_encode(['status' => 'SUCCESS', 'smsinfo' => [['sms_status' => 'Delivered']]]),
            'dynamicBulkSms' => json_encode(['status' => 'SUCCESS', 'smsinfo' => array_map(fn ($m) => ['csms_id' => $m['csms_id'], 'sms_status' => 'SUCCESS'], $args[0])]),
            default => json_encode(['status' => 'SUCCESS', 'smsinfo' => [['sms_status' => 'SUCCESS', 'csms_id' => $args[2] ?? null]]]),
        };
    }

    protected function twilio(string $method, array $args)
    {
        $sid = (string) config('sms.twilio.sid');
        $token = (string) config('sms.twilio.token');
        if ($sid === '' || $token === '') {
            return json_encode(['status' => 'FAILED', 'error_message' => 'TWILIO_SID / TWILIO_TOKEN not set']);
        }
        $base = "https://api.twilio.com/2010-04-01/Accounts/{$sid}";
        $http = fn () => \Illuminate\Support\Facades\Http::withBasicAuth($sid, $token)->timeout(15);

        $send = function (string $to, string $body, ?string $csmsId) use ($http, $base) {
            $digits = preg_replace('/\D/', '', (string) \App\Classes\Helper::normalizePhone($to));
            $r = $http()->asForm()->post("{$base}/Messages.json", ['To' => '+' . $digits, 'From' => config('sms.twilio.from'), 'Body' => $body]);
            if ($r->successful()) {
                return ['csms_id' => $csmsId, 'sms_status' => 'SUCCESS', 'reference_id' => $r->json('sid')];
            }

            return ['csms_id' => $csmsId, 'sms_status' => 'FAILED', 'status_message' => $r->json('message') ?? ('HTTP ' . $r->status())];
        };

        switch ($method) {
            case 'singleSms':
                $info = $send((string) $args[0], (string) $args[1], $args[2] ?? null);
                return json_encode($info['sms_status'] === 'SUCCESS'
                    ? ['status' => 'SUCCESS', 'smsinfo' => [$info]]
                    : ['status' => 'FAILED', 'error_message' => $info['status_message'], 'smsinfo' => [$info]]);
            case 'bulkSms':
                return json_encode(['status' => 'SUCCESS', 'smsinfo' => array_map(fn ($to) => $send((string) $to, (string) $args[1], null), (array) $args[0])]);
            case 'dynamicBulkSms':
                return json_encode(['status' => 'SUCCESS', 'smsinfo' => array_map(fn ($m) => $send((string) $m['msisdn'], (string) $m['text'], $m['csms_id'] ?? null), (array) $args[0])]);
            case 'balance':
                $r = $http()->get("{$base}/Balance.json");
                return $r->successful()
                    ? json_encode(['status' => 'SUCCESS', 'balance' => $r->json('balance'), 'currency' => $r->json('currency')])
                    : json_encode(['status' => 'FAILED', 'error_message' => $r->json('message') ?? ('HTTP ' . $r->status())]);
            case 'status':
                $ref = \Illuminate\Support\Facades\DB::table('sms_logs')->where('csms_id', $args[0])->value('gateway_ref');
                if (!$ref) {
                    return json_encode(['status' => 'FAILED', 'error_message' => 'No Twilio message id for this SMS']);
                }
                $r = $http()->get("{$base}/Messages/{$ref}.json");
                return $r->successful()
                    ? json_encode(['status' => 'SUCCESS', 'smsinfo' => [['sms_status' => $r->json('status'), 'status_message' => $r->json('error_message')]]])
                    : json_encode(['status' => 'FAILED', 'error_message' => $r->json('message') ?? ('HTTP ' . $r->status())]);
        }

        return json_encode(['status' => 'FAILED', 'error_message' => 'Unknown SMS call']);
    }
}
