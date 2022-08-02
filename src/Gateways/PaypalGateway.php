<?php

namespace Bjit\Payment\Gateways;

use Bjit\Payment\Gateways\Interfaces\GatewayInterface;
use Bjit\Payment\Traits\Paypal\Checkoutable;
use Bjit\Payment\Traits\Paypal\Paymentable;
use Bjit\Payment\Traits\Paypal\Refundable;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Srmklive\PayPal\Services\PayPal as PayPalClient;


class PaypalGateway extends AbstractGateway implements GatewayInterface
{
    use Paymentable;
    use Refundable;
    use Checkoutable;

    /**
     * The scopes being requested.
     *
     * @var array
     */
   
    private $paypal;

    public function __construct(Request $request, $key, $secret, $additionalConfig = [], $guzzle = [])
    { 
        parent::__construct($request, $key, $secret, $additionalConfig, $guzzle);

        $this->setConfig($this->key, $this->secret);
    }

    private function setConfig($key, $secret)
    {  
        $this->paypal = new PayPalClient;
        $this->paypal->setApiCredentials(config('payments.paypal')); 
        $this->paypal->getAccessToken();
        //$this->paypal->setAccessToken($this->paypal->getAccessToken());
    }

     
}
