<?php 

namespace Bjit\Payment\Traits\Payjp;

use Bjit\Payment\Enums\CmnEnum;
use Bjit\Payment\Helpers\CmnHelper;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Payjp\Charge; 

trait Paymentable 
{
    public function formatPaymentListInput($options)
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

    public function formatPaymentInput($options)
    {
        $input = [
            'amount' => $options['amount'],
            'currency' => $options['currency'],
            'card' => $options['nonce'] ?? CmnEnum::EMPTY_NULL,
            'description' => $options['description'] ?? CmnEnum::EMPTY_NULL   
        ];  

        if (isset($options['capture'])) {
            $input['capture'] = $options['capture'];
        }

        $extraInput = Arr::except($options, [
            'amount', 'currency', 'nonce', 'description', 'capture'
        ]); 

        return array_merge($input, $extraInput);
    }

    public function formatPaymentResponse($response)
    {
        if(isset($response['error'])) {
            return $this->formatErrorResponse($response);
        }  

        return [
            'provider' => CmnEnum::PROVIDER_PAYJP,
            'id' => $response['id'],
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
            'provider_response' => $response
        ];
    }
 
    public function createPayment($options)
    { 
        $response = Charge::create($this->formatPaymentInput($options)); 
        $this->storePaymentInDatabase($response, $options, CmnEnum::PT_DIRECT_PAYMENT);
        return $this->formatPaymentResponse($response);
    }

    public function retrievePayment($paymentId, $options = [])
    {
        return $this->formatPaymentResponse(Charge::retrieve($paymentId));
    }

    public function updatePayment($paymentId, $options = [])
    {
        $response = Charge::retrieve($paymentId);

        if(isset($options['description'])) {
            $response->description = $options['description'];
        }
        if(isset($options['metadata'])) {
            $response->metadata = $options['metadata'];
        }
        
        $response->save();
        $this->updatePaymentInDatabase($paymentId, $response, $options);
        return $this->formatPaymentResponse($response);
    }

    public function capturePayment($paymentId, $options = [])
    {
        $response = Charge::retrieve($paymentId);
        $this->updatePaymentInDatabase($paymentId, $response, $options);
        return $this->formatPaymentResponse($response->capture()); 
    }

    public function allPayments($options)
    {
        return Charge::all($this->formatPaymentListInput($options));
    }

    private function storePaymentInDatabase($response, $options, $paymentType)
    { 
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return CmnEnum::TRUE;
        }

        return DB::table(CmnEnum::TABLE_PAYMENT_NAME)->insert([
            //'state' => $options['state'], 
            'type' => $paymentType,
            'provider' => CmnEnum::PROVIDER_PAYJP,
            'provider_payment_id' => $response['id'],
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
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    private function updatePaymentInDatabase($providerPaymentId, $response, $options) 
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return CmnEnum::TRUE;
        }

        return DB::table(CmnEnum::TABLE_PAYMENT_NAME)
            ->where('provider', CmnEnum::PROVIDER_PAYJP)
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