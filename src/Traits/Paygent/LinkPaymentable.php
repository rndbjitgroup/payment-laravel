<?php 

namespace Bjit\Payment\Traits\Paygent;

use Bjit\Payment\Enums\CmnEnum;
use Bjit\Payment\Helpers\CmnHelper;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Payjp\Charge; 

trait LinkPaymentable 
{
    public function generateHash($options)
    {

        $str = $options['trading_id'];
        $str .= $options['payment_type'];
        $str .= $options['id'];
        $str .= $options['seq_merchant_id'];
        $str .= $options['payment_term_min'];
        $str .= $options['payment_class'];
        $str .= $options['use_card_conf_number']; 
        $str .= $options['has_generation_key'] ?? config('payments.paygent.key');
         
        return hash('sha256', $str);
    }

    public function formatLinkPaymentListInput($options)
    {
        $input = [];

        if (isset($options['limit'])) {
            $input['limit'] = $options['limit'];
        }

        if (isset($options['offset'])) {
            $input['offset'] = $options['offset'];
        }

        $extraInput = Arr::except($options, [
            'limit', 'offset'
        ]); 

        return array_merge($input, $extraInput);
    }

    public function formatLinkPaymentInput($options)
    {
        $input = [
            'trading_id' => $options['trading_id'],
            'payment_type' => $options['payment_type'],
            'id' => $options['amount'],
            //'currency' => $options['currency'], 
            //'card' => $options['nonce'] ?? CmnEnum::EMPTY_NULL,  
            'seq_merchant_id' => $options['seq_merchant_id'] ?? config('payments.paygent.merchant_id'), 
            'payment_term_min' => $options['payment_term_min'] ?? CmnEnum::PAYGENT_PAYMENT_TERM_MIN, 
            'payment_class' => $options['payment_class'] ?? CmnEnum::PAYGENT_PAYMENT_CLASS, 
            'use_card_conf_number' => $options['use_card_conf_number'] ?? CmnEnum::PAYGENT_USE_CARD_CONF_NUMBER, 
            'banner_url' => $options['banner_url'] ?? '',
            'return_url' => $options['success_url'] . '?state=' . $options['state'] ?? '',
            'stop_return_url' => $options['cancel_url'] . '?state=' . $options['state'] ?? '',
            //'hc' => $options['hc'] ?? $this->generateHash($options),
        ];  
        
        $input['hc'] = $options['hc'] ?? $this->generateHash($input);

        $extraInput = Arr::except($options, [
            'trading_id', 'payment_type', 'id', 'seq_merchant_id', 'payment_term_min', 'payment_class', 'use_card_conf_number',
            'banner_url', 'return_url', 'stop_return_url', 'hc', 'has_generation_key', 'success_url', 'cancel_url', 'state'
        ]); 

        return array_merge($input, $extraInput);
    }

    public function formatLinkPaymentResponse($response)
    {
         
    }

    public function formatLinkPaymentGetURL($response)
    {
        return config('payments.paygent.base_url') . '?' . http_build_query($response);
    }
 
    public function createLinkPayment($options)
    { 
        $response = $this->formatLinkPaymentInput($options); 
        $this->storeLinkPaymentInDatabase($response, $options, CmnEnum::PT_DIRECT_PAYMENT);
        return $this->formatLinkPaymentGetURL($response);
    }

    public function retrieveLinkPayment($paymentId, $options = [])
    {
         
    }

    public function updateLinkPayment($paymentId, $options = [])
    {
         
    }

    public function captureLinkPayment($paymentId, $options = [])
    {
         
    }

    public function allLinkPayments($options)
    {
         
    }

    private function storeLinkPaymentInDatabase($response, $options, $paymentType)
    { 
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return CmnEnum::TRUE;
        }

        return DB::table(CmnEnum::TABLE_PAYMENT_NAME)->insert([
            'state' => $options['state'] ?? null,
            'type' => $paymentType,
            'provider' => CmnEnum::PROVIDER_PAYGENT,
            'provider_payment_id' => $response['trading_id'],
            'user_id' => Auth::user()->id ?? CmnEnum::ONE,
            'amount' => $options['amount'],
            'currency' => $response['currency'],
            'captured' => $response['captured'] ?? CmnEnum::EMPTY_NULL,
            'payment_status' => $response['paid'] ?? CmnEnum::EMPTY_NULL,
            'status' => $response['status'] ?? CmnEnum::EMPTY_NULL,
            'generic_payment_status' => isset($response['paid']) ? CmnEnum::PS_PAID : CmnEnum::PS_UNPAID,
            'generic_status' => isset($response['paid']) ? CmnEnum::STATUS_COMPLETE : CmnEnum::STATUS_OPEN,
            'description' => $response['description'] ?? CmnEnum::EMPTY_NULL,
            'payment_type' => CmnEnum::PT_CARD,
            'card_brand' => $response['card']['brand'] ?? CmnEnum::EMPTY_NULL,
            'last_4_digit' => $response['card']['last4'] ?? CmnEnum::EMPTY_NULL,
            'customer_name' =>$response['card']['name'] ?? CmnEnum::EMPTY_NULL,
            'customer_email' => $response['customer_details']['email'] ?? CmnEnum::EMPTY_NULL,
            'customer_phone' => $response['customer_details']['phone'] ?? CmnEnum::EMPTY_NULL,
            'success_json' => json_encode($response),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    private function updateLinkPaymentInDatabase($providerPaymentId, $response, $options) 
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return CmnEnum::TRUE;
        }

        return DB::table(CmnEnum::TABLE_PAYMENT_NAME)
            ->where('provider', CmnEnum::PROVIDER_PAYGENT)
            ->where('provider_payment_id', $providerPaymentId)
            ->update([  
                'user_id' => Auth::user()->id ?? CmnEnum::ONE,
                'amount' => $response['amount'],
                'currency' => $response['currency'],
                'captured' => $response['captured'],
                'payment_status' => $response['paid'] ?? CmnEnum::EMPTY_NULL,
                'status' => $response['status'] ?? CmnEnum::EMPTY_NULL,
                'generic_payment_status' => $response['paid'] ? CmnEnum::PS_PAID : CmnEnum::PS_UNPAID,
                'generic_status' => $response['paid'] ? CmnEnum::STATUS_COMPLETE : CmnEnum::STATUS_OPEN,
                'description' => $response['description'],
                'payment_type' => CmnEnum::PT_CARD,
                'card_brand' => $response['card']['brand'] ?? CmnEnum::EMPTY_NULL,
                'last_4_digit' => $response['card']['last4'] ?? CmnEnum::EMPTY_NULL,
                'customer_name' =>$response['card']['name'] ?? CmnEnum::EMPTY_NULL,
                'customer_email' => $response['customer_details']['email'] ?? CmnEnum::EMPTY_NULL,
                'customer_phone' => $response['customer_details']['phone'] ?? CmnEnum::EMPTY_NULL,
                'success_json' => CmnHelper::jsonEncodePrivate($response), 
                'updated_at' => now()
            ]);
    }

}