<?php

namespace Bjit\Payment\Gateways;

use Bjit\Payment\Gateways\Interfaces\CheckoutInterface; 
use Bjit\Payment\Gateways\Interfaces\GatewayInterface; 
use Bjit\Payment\Traits\Squareup\Checkoutable; 
use Bjit\Payment\Traits\Squareup\Paymentable;  
use Bjit\Payment\Traits\Squareup\Refundable; 
use Illuminate\Http\Request;  
use Square\SquareClient;
use Square\Environment;

class SquareupGateway extends AbstractGateway implements GatewayInterface, CheckoutInterface
{
    use Paymentable;
    use Checkoutable;
    use Refundable;
    

    /**
     * The scopes being requested.
     *
     * @var array
     */
     
    private $stripe;

    public function __construct(Request $request, $key, $secret, $additionalConfig = [], $guzzle = [])
    { 
        parent::__construct($request, $key, $secret, $additionalConfig, $guzzle);

        $this->setConfig($this->key, $this->secret, $additionalConfig); 
    }

    private function setConfig($key, $secret, $additionalConfig)
    {  
        $this->squareup = new SquareClient([
            'accessToken' => $secret,
            'environment' => $additionalConfig['is_live'] == true ? Environment::PRODUCTION : Environment::SANDBOX,
        ]);
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
