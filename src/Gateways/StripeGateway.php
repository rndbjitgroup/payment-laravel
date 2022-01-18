<?php

namespace Bjit\Payment\Gateways;

use Bjit\Payment\Gateways\Interfaces\CheckoutInterface;
use Bjit\Payment\Gateways\Interfaces\CustomerInterface;
use Bjit\Payment\Gateways\Interfaces\GatewayInterface;
use Bjit\Payment\Traits\Stripe\Cardable;
use Bjit\Payment\Traits\Stripe\Checkoutable;
use Bjit\Payment\Traits\Stripe\Customerable;
use Bjit\Payment\Traits\Stripe\Paymentable;
use Bjit\Payment\Traits\Stripe\Refundable;
use Illuminate\Http\Request;
use Stripe\StripeClient;

class StripeGateway extends AbstractGateway implements GatewayInterface, CheckoutInterface, CustomerInterface
{
    use Paymentable;
    use Checkoutable;
    use Refundable;
    use Customerable;
    use Cardable;
    

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
        $this->stripe = new StripeClient($secret); 
    }


    /**
     * Get the default options for an HTTP request.
     *
     * @param  string  $token
     * @return array
     */
    // protected function getRequestOptions($token)
    // {
    //     return [
    //         'headers' => [
    //             'Accept' => 'application/vnd.github.v3+json',
    //             'Authorization' => 'token '.$token,
    //         ],
    //     ];
    // }
}
