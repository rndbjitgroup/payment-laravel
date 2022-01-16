<?php

namespace Bjit\Payment\Gateways;

use Bjit\Payment\Gateways\Interfaces\GatewayInterface;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Stripe\Charge;
use Stripe\Checkout\Session;
use Stripe\Stripe;
use Stripe\StripeClient;

class PaypalGateway extends AbstractGateway implements GatewayInterface
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

    public function formatCheckoutData($options)
    {
        return $options;
    }

    public function formatPaymentResponse($response)
    {
        
    }

    public function createPayment($options)
    { 
        return $this->stripe->charges->create($this->formatPaymentData($options)); 
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
        return $this->stripe->checkout->sessions->create($this->formatCheckoutData($options)); 
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
