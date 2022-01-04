<?php

namespace Bjit\Payment\Gateways;

use Bjit\Payment\Enums\CmnEnum;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Stripe\Charge;
use Stripe\Checkout\Session;
use Stripe\Stripe;
use Stripe\StripeClient;

class StripeGateway extends AbstractGateway implements GatewayInterface
{

    /**
     * The scopes being requested.
     *
     * @var array
     */
     
    private $stripe;

    public function __construct(Request $request, $key, $secret, $additionalConfig = [], $guzzle = [])
    { 
        parent::__construct($request, $key, $secret, $additionalConfig, $guzzle);

        $this->setConfig($this->key, $this->secret); 
    }

    private function setConfig($key, $secret)
    { 
        //Stripe::setApiKey($secret);
        $this->stripe = new StripeClient($secret);
        //dd($this->stripe);
    }

    public function formatPaymentInput($options)
    {
        return [
            'amount' => $options['amount'],
            'currency' => $options['currency'] ?? 'usd',
            'source' => $options['nonce'] ?? null,
            'description' => $options['description']
        ];
    }

    public function formatCheckoutInput($options)
    { 
        $items = [];
        foreach($options['order_items'] as $item) 
        {
            $items[] = [
                'price_data' => [
                    'currency' => $item['price']['currency'],
                    'product_data' => [
                        'name' => $item['name'],
                    ],
                    'unit_amount' => $item['price']['amount'],
                ],
                'quantity' => $item['quantity'],
            ];
        }

        $chekoutData = [
            'payment_method_types' => $options['payment_method_types'],
            'line_items' => $items,
            'mode' => $options['mode'],
            'payment_intent_data' => $options['payment_intent_data'],
            'success_url' => $options['success_url'] . '?state=' . $options['state'], 
            'cancel_url' => $options['cancel_url'] . '?state=' . $options['state'],
        ]; 

        $extraData = Arr::except($options, [
            'payment_method_types', 'order_items', 'mode', 'payment_intent_data', 
            'success_url', 'cancel_url', 'state'
        ]);

        $chekoutData = array_merge($chekoutData, $extraData); 
        
        return $chekoutData;
    }

    public function formatPaymentResponse($response)
    {
        return $response;
    }

    public function formatCheckoutResponse($response)
    {
        return $response;
    }

    public function createPayment($options)
    {  
        $response = $this->stripe->charges->create($this->formatPaymentInput($options)); 
        return $this->formatPaymentResponse($response);
        $this->storePayment($response, $options, CmnEnum::PT_DIRECT_PAYMENT);
    }

    public function retrievePayment($paymentId, $options = [])
    {
        return $this->stripe->charges->retrieve( $paymentId, $options );
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

    public function createCheckout($options)
    { 
        $response = $this->stripe->checkout->sessions->create($this->formatCheckoutInput($options)); 
        $this->storePayment($response, $options, CmnEnum::PT_CHECKOUT_PAYMENT);
        return $this->formatCheckoutResponse($response);
    }

    public function expireCheckout($csId, $options = [])
    { 
        return $this->stripe->checkout->sessions->expire($csId, $options); 
    }

    public function retrieveCheckout($csId, $options = [])
    { 
        return $this->stripe->checkout->sessions->retrieve($csId, $options); 
    }

    public function allCheckouts($options = [])
    { 
        return $this->stripe->checkout->sessions->all($options); 
    }

    public function allCheckoutLineItems($csId, $options = [])
    { 
        return $this->stripe->checkout->sessions->allLineItems($csId, $options); 
    }

    public function refundPayment($params)
    {
        return $this->stripe->refunds->create($params);
    }

    public function retrieveRefund($refundId, $options = [])
    {
        return $this->stripe->refunds->retrieve( $refundId, $options );
    }

    public function updateRefund($refundId, $options = [])
    {
        return $this->stripe->refunds->update( $refundId, $options );
    }
    
    private function storePayment($response, $options, $paymentType)
    { 
        return DB::table(CmnEnum::TABLE_PAYMENT_NAME)->insert([
            'state' => $options['state'],
            'payment_type' => $paymentType,
            'provider' => CmnEnum::PROVIDER_STRIPE,
            'provider_payment_id' => $response['id'],
            'user_id' => Auth::user()->id ?? CmnEnum::ONE,
            'amount' => $response['amount_total'],
            'currency' => $response['currency'],
            'payment_status' => $response['payment_status'],
            'status' => $response['status'],
            'success_json' => json_encode($response),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Get the default options for an HTTP request.
     *
     * @param  string  $token
     * @return array
     */
    protected function getRequestOptions($token)
    {
        return [
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'Authorization' => 'token '.$token,
            ],
        ];
    }
}
