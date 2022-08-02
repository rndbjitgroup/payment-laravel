<?php 

namespace Bjit\Payment\Traits\Stripe;

use Bjit\Payment\Enums\CmnEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

trait PaymenIntenttable 
{
    private function formatPaymentIntentListInput($options)
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

    private function formatPaymentIntentInput($options)
    {
        $input = [
            'amount' => $options['amount'],
            'currency' => $options['currency'],
            'payment_method_types' => $options['payment_method_types'], 
            // 'payment_method_data' => [
            //     'type' => 'card',
            //     'token' => $options['nonce']
            // ],
            //'customer' => '{{CUSTOMER_ID}}',
            //'payment_method' => '{{ CARD_ID }}',
            'description' => $options['description']
        ];

        if (isset($options['capture'])) {
            $input['capture'] = $options['capture'];
        }

        $extraInput = Arr::except($options, [
            'amount', 'currency', 'nonce', 'description', 'capture'
        ]); 

        return array_merge($input, $extraInput);
    }

    private function formatPaymentIntentResponse($response)
    { 
        return [
            'provider' => CmnEnum::PROVIDER_STRIPE,
            'id' => $response['id'],
            'amount' => $response['amount'], 
            'currency' => $response['currency'],
            'payment_status' => $response['paid'] ?? null,
            'status' => $response['status'] ?? null,
            'generic_payment_status' => $response['paid'] ? CmnEnum::PS_PAID : CmnEnum::PS_UNPAID,
            'generic_status' => $response['status'] == CmnEnum::STATUS_SUCCEEDED ? CmnEnum::STATUS_COMPLETE : CmnEnum::STATUS_OPEN,
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

    private function getPaymentIntentAmount($paymentId)
    {
        $payment = $this->retrievePaymentIntent($paymentId);
        return $payment['amount'];
    }

    public function createPaymentIntent($options)
    {  
        $response = $this->stripe->paymentIntents->create($this->formatPaymentIntentInput($options)); 
        $this->storePaymentIntentInDatabase($response, $options, CmnEnum::PT_DIRECT_PAYMENT);
        return $this->formatPaymentIntentResponse($response);
    }

    public function retrievePaymentIntent($paymentId, $options = [])
    {
        return $this->formatPaymentIntentResponse($this->stripe->paymentIntents->retrieve( $paymentId, $options ));
    }

    public function updatePaymentIntent($paymentId, $options = [])
    { 
        $response = $this->stripe->paymentIntents->update( $paymentId, $this->formatPaymentIntentInput($options) );
        $this->updatePaymentIntentInDatabase($paymentId, $response, $options);
        return $this->formatPaymentIntentResponse($response);
    }

    public function confirmPaymentIntent($paymentId, $options = [])
    {
        $response = $this->stripe->paymentIntents->confirm( $paymentId, $options );
        $this->updatePaymentIntentInDatabase($paymentId, $response, $options);
        return $this->formatPaymentIntentResponse($response);
    }

    public function capturePaymentIntent($paymentId, $options = [])
    {
        $response = $this->stripe->paymentIntents->capture( $paymentId, $options );
        $this->capturePaymentIntentFromDatabase($paymentId, $response, $options);
        return $this->formatPaymentIntentResponse($response);
    }

    public function cancelPaymentIntent($paymentId, $options = [])
    {
        $response = $this->stripe->paymentIntents->cancel( $paymentId, $options );
        $this->cancelPaymentIntentFromDatabase($paymentId, $response, $options);
        return $this->formatPaymentIntentResponse($response);
    }

    public function allPaymentIntents($options = [])
    { 
        return $this->stripe->paymentIntents->all($this->formatPaymentIntentListInput($options)); 
    }

    private function storePaymentIntentInDatabase($response, $options, $paymentType)
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC) ) {
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

    private function updatePaymentIntentInDatabase($paymentId, $response, $options) 
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC) ) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_PAYMENT_NAME)
            ->where('provider', CmnEnum::PROVIDER_STRIPE)
            ->where('provider_payment_id', $paymentId)
            ->update([  
                'provider_payment_intent_id' => $response['payment_intent'] ?? null,
                'user_id' => Auth::user()->id ?? CmnEnum::ONE,
                'amount' => $response['amount_total'] ?? $response['amount'] ?? 0,
                'currency' => $response['currency'],
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
                'updated_at' => now()
            ]);
    } 

    private function capturePaymentIntentFromDatabase($paymentId, $response, $options = [])
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

    private function cancelPaymentIntentFromDatabase($paymentId, $response, $options = [])
    {
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_PAYMENT_NAME)
            ->where('provider', CmnEnum::PROVIDER_STRIPE)
            ->where('provider_payment_id', $paymentId)
            ->update([
                'success_json' => json_encode($response),  
                'canceled_at' => now()
            ]);
    }

}