<?php 

namespace Bjit\Payment\Traits\Stripe;

use Bjit\Payment\Enums\CmnEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

trait Paymentable
{
    private function formatPaymentListInput($options)
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
        $input = [];

        if (isset($options['amount'])) {
            $input['amount'] = $options['amount'];
        }
        if (isset($options['currency'])) {
            $input['currency'] = $options['currency'];
        }
        if (isset($options['nonce'])) {
            $input['source'] = $options['nonce'];
        }
        if (isset($options['description'])) {
            $input['description'] = $options['description'];
        }
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
        $genericPaymentStatus = ((isset($response['paid']) && $response['paid']) || (isset($response['payment_status']) && $response['payment_status'])) ? CmnEnum::PS_PAID : CmnEnum::PS_UNPAID;
        $genericStatus = ($response['status'] == CmnEnum::STATUS_SUCCEEDED || $response['status'] == CmnEnum::STATUS_STRIPE_COMPLETE) ? CmnEnum::STATUS_COMPLETE : CmnEnum::STATUS_OPEN;

        return [
            'provider' => CmnEnum::PROVIDER_STRIPE,
            'id' => $response['id'],
            'amount' => $response['amount'], 
            'currency' => $response['currency'],
            'captured' => $response['captured'],
            'payment_status' => $response['paid'] ?? null,
            'status' => $response['status'] ?? null,
            //'generic_payment_status' => $response['paid'] ? CmnEnum::PS_PAID : CmnEnum::PS_UNPAID,
            //'generic_status' => $response['status'] == CmnEnum::STATUS_SUCCEEDED ? CmnEnum::STATUS_COMPLETE : CmnEnum::STATUS_OPEN,
            'generic_payment_status' => $genericPaymentStatus,
            'generic_status' => $genericStatus,
            'description' => $response['description'],
            'payment_type' => $response['payment_method_details']['type'] ?? $response['payment_method_types'][CmnEnum::ZERO] ?? null,
            'card_brand' => $response['payment_method_details']['card']['brand'] ?? null,
            'last_4_digit' => $response['payment_method_details']['card']['last4'] ?? null,
            'created_at' => $response['created'],
            'customer_name' => $response['customer']['name'] ?? null,
            'customer_email' => $response['customer']['email'] ?? null,
            'customer_phone' => $response['customer']['phone'] ?? null,
            'provider_response' => $response
        ];
    }

    private function getPaymentAmount($paymentId)
    {
        $payment = $this->retrievePayment($paymentId);
        return $payment['amount'];
    }

    public function createPayment($options)
    {  
        $response = $this->stripe->charges->create($this->formatPaymentInput($options)); 
        $this->storePaymentInDatabase($response, $options, CmnEnum::PT_DIRECT_PAYMENT);
        return $this->formatPaymentResponse($response);
    }

    public function retrievePayment($paymentId, $options = [])
    {
        return $this->formatPaymentResponse($this->stripe->charges->retrieve( $paymentId, $options ));
    }

    public function updatePayment($paymentId, $options = [])
    {
        $response = $this->stripe->charges->update( $paymentId, $this->formatPaymentInput($options) );
        $this->updatePaymentInDatabase($paymentId, $response, $options);
        return $this->formatPaymentResponse($response);
    }

    public function capturePayment($paymentId, $options = [])
    {
        $response = $this->stripe->charges->capture( $paymentId, $options ); 
        $this->capturePaymentFromDatabase($paymentId, $response, $options);
        return $this->formatPaymentResponse($response);
    } 

    public function allPayments($options = [])
    { 
        return $this->stripe->charges->all($this->formatPaymentListInput($options)); 
    }

    private function storePaymentInDatabase($response, $options, $paymentType)
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_PAYMENT_NAME)->insert([
            'state' => $options['state'] ?? null,
            'type' => $paymentType,
            'provider' => CmnEnum::PROVIDER_STRIPE,
            'provider_payment_id' => $response['id'],
            'provider_payment_intent_id' => $response['payment_intent'] ?? null,
            'user_id' => Auth::user()->id ?? CmnEnum::ONE,
            'amount' => $response['amount_total'] ?? $response['amount'] ?? 0,
            'currency' => $response['currency'],
            'captured' => $response['captured'],
            'payment_status' => $response['paid'],
            'status' => $response['status'],
            'generic_payment_status' => $response['paid'] ? CmnEnum::PS_PAID : CmnEnum::PS_UNPAID,
            'generic_status' => $response['status'] == CmnEnum::STATUS_SUCCEEDED ? CmnEnum::STATUS_COMPLETE : CmnEnum::STATUS_OPEN,
            'description' => $response['description'],
            'payment_type' => $response['payment_method_details']['type'] ?? $response['payment_method_types'][CmnEnum::ZERO] ?? null,
            'card_brand' => $response['payment_method_details']['card']['brand'] ?? null,
            'last_4_digit' => $response['payment_method_details']['card']['last4'] ?? null,
            'customer_name' => $response['customer_details']['name'] ?? null,
            'customer_email' => $response['customer_details']['email'] ?? null,
            'customer_phone' => $response['customer_details']['phone'] ?? null,
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

        if (isset($options['is_captured']) && $options['is_captured']) {
            $updateData['captured_at'] = now();
        }

        $genericPaymentStatus = ((isset($response['paid']) && $response['paid']) || (isset($response['payment_status']) && $response['payment_status'])) ? CmnEnum::PS_PAID : CmnEnum::PS_UNPAID;
        $genericStatus = ($response['status'] == CmnEnum::STATUS_SUCCEEDED || $response['status'] == CmnEnum::STATUS_STRIPE_COMPLETE) ? CmnEnum::STATUS_COMPLETE : CmnEnum::STATUS_OPEN;

        return DB::table(CmnEnum::TABLE_PAYMENT_NAME)
            ->where('provider', CmnEnum::PROVIDER_STRIPE)
            ->where('provider_payment_id', $paymentId)
            ->update([  
                'provider_payment_intent_id' => $response['payment_intent'] ?? null,
                'user_id' => Auth::user()->id ?? CmnEnum::ONE,
                'amount' => $response['amount_total'] ?? $response['amount'] ?? 0,
                'currency' => $response['currency'],
                'captured' => $response['captured'],
                'payment_status' => $response['paid'],
                'status' => $response['status'],
                'generic_payment_status' => $genericPaymentStatus,
                'generic_status' => $genericStatus,
                'description' => $response['description'],
                'payment_type' => $response['payment_method_details']['type'] ?? $response['payment_method_types'][CmnEnum::ZERO] ?? null,
                'card_brand' => $response['payment_method_details']['card']['brand'] ?? null,
                'last_4_digit' => $response['payment_method_details']['card']['last4'] ?? null,
                'customer_name' => $response['customer_details']['name'] ?? null,
                'customer_email' => $response['customer_details']['email'] ?? null,
                'customer_phone' => $response['customer_details']['phone'] ?? null,
                'success_json' => json_encode($response),  
                'updated_at' => now()
            ]);
    }

    private function capturePaymentFromDatabase($paymentId, $response, $options = [])
    {
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_PAYMENT_NAME)
            ->where('provider', CmnEnum::PROVIDER_STRIPE)
            ->where('provider_payment_id', $paymentId)
            ->update([
                'success_json' => json_encode($response),  
                'captured_at' => now()
            ]);
    }

}