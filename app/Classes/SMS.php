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
        $params = [
            "api_token" => config('sms.api_token'),
            "sid"       => config('sms.sender_id'),
            "sms"       => $smsData,
        ];
        $url = trim(config('sms.api_url'), '/')."/api/v3/send-sms/dynamic";
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
}