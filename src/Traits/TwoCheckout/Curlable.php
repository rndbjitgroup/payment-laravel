<?php 

namespace Bjit\Payment\Traits\TwoCheckout;

use Bjit\Payment\Enums\CmnEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

trait Curlable
{
    public function getCurlHeader()
    {
        $date = gmdate('Y-m-d H:i:s');
        $string = strlen($merchantCode) . $merchantCode . strlen($date) . $date;
        $hash = hash_hmac('md5', $string, $key);

        return [
            "Content-Type: application/json",
            "Accept: application/json",
            "X-Avangate-Authentication: code=\"{$merchantCode}\" date=\"{$date}\" hash=\"{$hash}\""
        ];
    }

    public function formatCurlResponse($ch, $response)
    {
        $errorMsg = null;
        if (curl_errno($ch)) {
            $errorMsg = curl_error($ch);
        }
        curl_close($ch);

        if(!is_null($errorMsg)) {
            return [
                'error' => $errorMsg
            ];
        } 
        return json_decode($response);
    }
    public function postCurl($url, $payload)
    {
        
        $ch = curl_init(); 

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        //curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSLVERSION, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getCurlHeader());

        $response = curl_exec($ch);
        return $this->formatCurlResponse($ch, $response);
    }
    
    public function getCurl($url)
    {
        $ch = curl_init(); 

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, FALSE);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        //curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_SSLVERSION, FALSE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getCurlHeader());

        $response = curl_exec($ch);
        return $this->formatCurlResponse($ch, $response);
    }

}