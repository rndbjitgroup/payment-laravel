<?php 

namespace Bjit\Payment\Traits\Stripe;

use Bjit\Payment\Enums\CmnEnum;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

trait Paymentable 
{
    public function formatPaymentInput($options)
    {
        return [
            'amount' => $options['amount'],
            'currency' => $options['currency'],
            'source' => $options['nonce'] ?? null,
            'description' => $options['description']
        ];
    }

    public function formatPaymentResponse($response)
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

    private function getPaymentAmount($paymentId)
    {
        $payment = $this->retrievePayment($paymentId);
        return $payment['amount'];
    }

    public function createPayment($options)
    {  
        $response = $this->stripe->charges->create($this->formatPaymentInput($options)); 
        $this->storePayment($response, $options, CmnEnum::PT_DIRECT_PAYMENT);
        return $this->formatPaymentResponse($response);
    }

    public function retrievePayment($paymentId, $options = [])
    {
        return $this->formatPaymentResponse($this->stripe->charges->retrieve( $paymentId, $options ));
    }

    public function updatePayment($paymentId, $options = [])
    {
        return $this->stripe->charges->update( $paymentId, $options );
    }

    public function capturePayment($paymentId, $options = [])
    {
        return $this->stripe->charges->capture( $paymentId, $options );
    }

    public function cancelPayment($paymentId, $options = [])
    {
         
    }

    private function storePayment($response, $options, $paymentType)
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
}