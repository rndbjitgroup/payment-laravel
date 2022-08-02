<?php

namespace Bjit\Payment\Gateways;

use Bjit\Payment\Gateways\Interfaces\GatewayInterface;
use Bjit\Payment\Traits\Paygent\Checkoutable;
use Bjit\Payment\Traits\Paygent\LinkPaymentable;
use Bjit\Payment\Traits\Paygent\Paymentable;
use Bjit\Payment\Traits\Paygent\Refundable; 
use Illuminate\Http\Request;  

class PaygentGateway extends AbstractGateway implements GatewayInterface
{
    use LinkPaymentable;
    use Paymentable;
    use Refundable; 

    /**
     * The scopes being requested.
     *
     * @var object
     */ 

    private $paygent; 

    public function __construct(Request $request, $key, $secret, $additionalConfig = [], $guzzle = [])
    { 
        parent::__construct($request, $key, $secret, $additionalConfig, $guzzle);

        $this->setConfig($this->key, $this->secret, $this->additionalConfig);
    }

    private function setConfig($key, $secret, $additionalConfig)
    { 
        $this->paygent = null; 
    } 
 
}
