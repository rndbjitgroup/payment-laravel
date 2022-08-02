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
        if(isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        $amount = $response['purchase_units'][CmnEnum::ZERO]['payments']['captures'][CmnEnum::ZERO]['amount']['value'] ?? null; 
        $currency = $response['purchase_units'][CmnEnum::ZERO]['payments']['captures'][CmnEnum::ZERO]['amount']['currency_code'] ?? null;
        
        if(!$amount) {
            $amount = $response['purchase_units'][CmnEnum::ZERO]['payments']['authorizations'][CmnEnum::ZERO]['amount']['value'] ?? null; 
            $currency = $response['purchase_units'][CmnEnum::ZERO]['payments']['authorizations'][CmnEnum::ZERO]['amount']['currency_code'] ?? null;
        } 

        if(!$amount) {
            $amount = $response['amount']['value'] ?? null; 
            $currency = $response['amount']['currency_code'] ?? null;
        }

        if(!$amount) {
            $amount = $response['purchase_units'][CmnEnum::ZERO]['amount']['value'] ?? null; 
            $currency = $response['purchase_units'][CmnEnum::ZERO]['amount']['currency_code'] ?? null;
        }


        return [
            'provider' => CmnEnum::PROVIDER_PAYPAL,
            'id' => $response['id'],
            'amount' => $amount, 
            'currency' => $currency, 
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
        //dd($response);
        if(isset($response['id'])) {
            $response = $this->paypal->showOrderDetails( $response['id'] );
            $this->storePaymentInDatabase($response, $options, CmnEnum::PT_DIRECT_PAYMENT);
        }
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

    public function authorizePayment($paymentId, $options = [])
    {
        $response = $this->paypal->authorizePaymentOrder( $paymentId );
        $this->updateAuthorizePaymentInDatabase($paymentId, $response, $options);
        return $this->formatPaymentResponse($response);
    }

    public function reAuthorizeAuthorizedPayment($paymentId, $options = [])
    {
        $response = $this->paypal->reAuthorizeAuthorizedPayment( $paymentId, $options['amount'] );
        $this->updateAuthorizePaymentInDatabase($paymentId, $response, $options);
        return $this->formatPaymentResponse($response);
    }

    public function voidAuthorizedPayment($paymentId, $options = [])
    {
        $response = $this->paypal->voidAuthorizedPayment( $paymentId );
        $this->updateAuthorizePaymentInDatabase($paymentId, $response, $options);
        return $this->formatPaymentResponse($response);
    }
    public function captureAuthorizedPayment($authorizedId, $options = [])
    {
        $response = $this->paypal->captureAuthorizedPayment( 
            $authorizedId, 
            $options['invoice_id'] ?? '',
            $options['amount'] ?? '',
            $options['reason'] ?? ''
        );
        
        $response = $this->paypal->showCapturedPaymentDetails( $response['id'] );
        
        $this->updateCaptureAuthorizedPaymentInDatabase($authorizedId, $response, $options);
        return $this->formatPaymentResponse($response);
    }
    public function capturePayment($paymentId, $options = [])
    {
        $response = $this->paypal->capturePaymentOrder( $paymentId );
        $this->updateCapturePaymentInDatabase($paymentId, $response, $options);
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

    private function updatePaymentTableInDatabase($paymentId, $options) 
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_PAYMENT_NAME)
            ->where('provider', CmnEnum::PROVIDER_PAYPAL)
            ->where('provider_payment_id', $paymentId)
            ->update($options);
    }

    private function rearrangeDataForUpdate($response, $options = [])
    {
        return [
            'user_id' => Auth::user()->id ?? CmnEnum::EMPTY_NULL,
            'status' => $response['status'],
            'generic_payment_status' => $response['status'] == CmnEnum::STATUS_PAYPAL_COMPLETED ? CmnEnum::PS_PAID : CmnEnum::PS_UNPAID,
            'generic_status' => $response['status'] == CmnEnum::STATUS_PAYPAL_COMPLETED ? CmnEnum::STATUS_COMPLETE : CmnEnum::STATUS_OPEN,
            'success_json' => json_encode($response), 
            'updated_at' => now()
        ];
    }

    private function updatePaymentInDatabase($paymentId, $response, $options) 
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return $this->updatePaymentTableInDatabase($paymentId, $this->rearrangeDataForUpdate($response, $options));
    }

    private function updateAuthorizePaymentInDatabase($paymentId, $response, $options) 
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        } 

        return $this->updatePaymentTableInDatabase($paymentId, 
            array_merge(
                $this->rearrangeDataForUpdate($response, $options),
                [
                    'provider_authorized_id' => $this->getProviderAuthorizedId($response),
                    'authorized_at' => now()
                ]
            )
        );
    }

    private function updateCapturePaymentInDatabase($paymentId, $response, $options) 
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return $this->updatePaymentTableInDatabase($paymentId, 
            array_merge(
                $this->rearrangeDataForUpdate($response, $options),
                [
                    'provider_captured_id' => $this->getProviderCaptureId($response),
                    'captured_at' => now()
                ]
            )
        );
    }

    private function updateCaptureAuthorizedPaymentInDatabase($authorizedId, $response, $options) 
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        $paymentId = $this->getProviderPaymentIdByAuthorizedId($authorizedId);

        return $this->updatePaymentTableInDatabase($paymentId, 
            array_merge(
                $this->rearrangeDataForUpdate($response, $options),
                [
                    'provider_authorized_captured_id' => $response['id'],
                    'authorized_captured_at' => now()
                ]
            )
        );
    } 

    private function getProviderAuthorizedId($response)
    {
        if(isset($response['purchase_units'][CmnEnum::ZERO]['payments']['authorizations'][CmnEnum::ZERO]['id'])) {
            return $response['purchase_units'][CmnEnum::ZERO]['payments']['authorizations'][CmnEnum::ZERO]['id'];
        }
        return CmnEnum::ZERO;
    }

    private function getProviderCaptureId($response)
    {
        if(isset($response['purchase_units'][CmnEnum::ZERO]['payments']['captures'][CmnEnum::ZERO]['id'])) {
            return $response['purchase_units'][CmnEnum::ZERO]['payments']['captures'][CmnEnum::ZERO]['id'];
        }
        return CmnEnum::ZERO;
    }

    private function getProviderPaymentIdByAuthorizedId($id)
    {
        return optional(
            DB::table(CmnEnum::TABLE_PAYMENT_NAME)
            ->where(function($q) use ($id) {
                $q->where('provider_authorized_id', $id);
            })
            ->first(['provider_payment_id'])
        )->provider_payment_id;
    }

}