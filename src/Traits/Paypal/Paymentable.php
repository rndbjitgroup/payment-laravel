<?php 

namespace Bjit\Payment\Traits\Paypal;

use Bjit\Payment\Enums\CmnEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

trait Paymentable 
{
    public function formatPaymentInput($options)
    {
        $data = [];
        
        if (isset($options['intent'])) {
            $data["intent"] = $options['intent'];
        }
        if (isset($options['success_url'])) {
            $data["application_context"] = [
                "return_url" => $options['success_url'],
                "cancel_url" => $options['cancel_url'],
            ];
        }
        if(isset($options['amount'])) {
            $data["purchase_units"] = [
                [
                    "amount"=> [
                        "currency_code"=> $options['currency'],
                        "value"=> $options['amount']
                    ],
                    'description' => $options['description'] ?? null
                ] 
            ];
        } 

        $extraData = Arr::except($options, [
            'intent', 'application_context', 'purchase_units', 'amount', 'currency', 'description', 'state'
        ]); 
        
        return array_merge($data, $extraData);
    }

    public function formatPaymentResponse($response)
    { 
        return [
            'provider' => CmnEnum::PROVIDER_PAYPAL,
            'id' => $response['id'],
            'amount' => $response['purchase_units'][CmnEnum::ZERO]['payments']['captures'][CmnEnum::ZERO]['amount']['value'] ?? null, 
            'currency' => $response['purchase_units'][CmnEnum::ZERO]['payments']['captures'][CmnEnum::ZERO]['amount']['currency_code'] ?? null, 
            'status' => $response['status'] ?? null,
            'generic_payment_status' => $response['status'] == CmnEnum::STATUS_PAYPAL_COMPLETED ? CmnEnum::PS_PAID : CmnEnum::PS_UNPAID,
            'generic_status' => $response['status'] == CmnEnum::STATUS_PAYPAL_COMPLETED ? CmnEnum::STATUS_COMPLETE : CmnEnum::STATUS_OPEN,
            'description' => $response['description'] ?? null,
            'links' => $response['links'],
            'provider_response' => $response
        ];
    } 

    public function createPayment($options)
    {  
        $response = $this->paypal->createOrder($this->formatPaymentInput($options)); 
        $this->storePaymentInDatabase($response, $options, CmnEnum::PT_DIRECT_PAYMENT);
        return $this->formatPaymentResponse($response);
    }

    public function retrievePayment($paymentId, $options = [])
    {
        return $this->formatPaymentResponse($this->paypal->showOrderDetails( $paymentId ));
    }

    public function updatePayment($paymentId, $options = [])
    {
        $response = $this->paypal->updateOrder( $paymentId, $options );
        $this->updatePaymentInDatabase($paymentId, $response, $options);
        return $this->formatPaymentResponse($response);
    }

    public function capturePayment($paymentId, $options = [])
    {
        $response = $this->paypal->capturePaymentOrder( $paymentId );
        $this->updatePaymentInDatabase($paymentId, $response, $options);
        return $this->formatPaymentResponse($response);
    } 

    private function storePaymentInDatabase($response, $options, $paymentType)
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_PAYMENT_NAME)->insert([
            'state' => $options['state'] ?? null,
            'type' => $paymentType,
            'provider' => CmnEnum::PROVIDER_PAYPAL,
            'provider_payment_id' => $response['id'], 
            'user_id' => Auth::user()->id ?? CmnEnum::ONE,
            'amount' => $options['amount_total'] ?? $options['amount'] ?? 0,
            'currency' => $options['currency'], 
            'status' => $response['status'],
            'generic_payment_status' => $response['status'] == CmnEnum::STATUS_PAYPAL_COMPLETED ? CmnEnum::PS_PAID : CmnEnum::PS_UNPAID,
            'generic_status' => $response['status'] == CmnEnum::STATUS_PAYPAL_COMPLETED ? CmnEnum::STATUS_COMPLETE : CmnEnum::STATUS_OPEN,
            'description' => $response['description'] ?? null,
            'success_json' => json_encode($response),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    private function updatePaymentInDatabase($paymentId, $response, $options) 
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_PAYMENT_NAME)
            ->where('provider', CmnEnum::PROVIDER_PAYPAL)
            ->where('provider_payment_id', $paymentId)
            ->update([  
                'user_id' => Auth::user()->id ?? CmnEnum::ONE,
                'status' => $response['status'],
                'generic_payment_status' => $response['status'] == CmnEnum::STATUS_PAYPAL_COMPLETED ? CmnEnum::PS_PAID : CmnEnum::PS_UNPAID,
                'generic_status' => $response['status'] == CmnEnum::STATUS_PAYPAL_COMPLETED ? CmnEnum::STATUS_COMPLETE : CmnEnum::STATUS_OPEN,
                'success_json' => json_encode($response), 
                'updated_at' => now()
            ]);
    }
}