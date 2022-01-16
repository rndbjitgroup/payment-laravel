<?php

namespace Bjit\Payment\Gateways;

use Bjit\Payment\Gateways\Interfaces\CustomerInterface;
use Bjit\Payment\Gateways\Interfaces\GatewayInterface;
use Bjit\Payment\Traits\Payjp\Customerable;
use Bjit\Payment\Traits\Payjp\Paymentable;
use Bjit\Payment\Traits\Payjp\Refundable;
use Illuminate\Http\Request;
use Payjp\Payjp;

class PayjpGateway extends AbstractGateway implements GatewayInterface, CustomerInterface
{
    use Paymentable;
    use Refundable;
    use Customerable;

    /**
     * The scopes being requested.
     *
     * @var array
     */ 

    private $payjp;

    public function __construct(Request $request, $key, $secret, $additionalConfig = [], $guzzle = [])
    { 
        parent::__construct($request, $key, $secret, $additionalConfig, $guzzle);

        $this->setConfig($this->key, $this->secret);
    }

    private function setConfig($key, $secret)
    { 
        Payjp::setApiKey($secret);
    } 

}
