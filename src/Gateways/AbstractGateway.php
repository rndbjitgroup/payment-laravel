<?php

namespace Bjit\Payment\Gateways;

use GuzzleHttp\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Bjit\Payment\Contracts\Gateway as GatewayContract;

abstract class AbstractGateway implements GatewayContract
{
    /**
     * The HTTP request instance.
     *
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * The client ID.
     *
     * @var string
     */
    protected $key;

    /**
     * The client secret.
     *
     * @var string
     */
    protected $secret;
 

    /**
     * The custom parameters to be sent with the request.
     *
     * @var array
     */
    protected $parameters = [];
 

    /**
     * The custom Guzzle configuration options.
     *
     * @var array
     */
    protected $guzzle = [];
 

    /**
     * Create a new provider instance.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $key
     * @param  string  $secret
     * @param  string  $redirectUrl
     * @param  array  $guzzle
     * @return void
     */
    public function __construct(Request $request, $key, $secret, $guzzle = [])
    {
        $this->guzzle = $guzzle;
        $this->request = $request;
        $this->key = $key; 
        $this->secret = $secret;
    }

    /**
     * Create Payment for the stripe.
     *
     * @param  string  $state
     * @return string
     */
    abstract protected function createPayment($options);

    /**
     * Retrieve Payment for the stripe.
     *
     * @return string
     */
    abstract protected function retrievePayment($paymentId, $options);

    /**
     * Update Payment for the stripe.
     *
     * @param  string  $token
     * @return array
     */
    abstract protected function updatePayment($paymentId, $options);

    abstract protected function capturePayment($paymentId, $options); 

    abstract protected function cancelPayment($paymentId, $options);

    abstract protected function createCheckout($options);

    abstract protected function expireCheckout($csId, $options);

    abstract protected function retrieveCheckout($csId, $options);

    abstract protected function allCheckouts($options);

    abstract protected function allCheckoutLineItems($csId, $options);

    abstract protected function refundPayment($options);

    abstract protected function retrieveRefund($refundId, $options);

    abstract protected function updateRefund($refundId, $options);

     
    /**
     * Set the custom parameters of the request.
     *
     * @param  array  $parameters
     * @return $this
     */
    public function with(array $parameters)
    {
        $this->parameters = $parameters;

        return $this;
    }
}
