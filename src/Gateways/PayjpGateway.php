<?php

namespace Bjit\Payment\Gateways;

use Bjit\Payment\Gateways\Interfaces\CustomerInterface;
use Bjit\Payment\Gateways\Interfaces\GatewayInterface;
use Bjit\Payment\Traits\Payjp\Cardable;
use Bjit\Payment\Traits\Payjp\Customerable;
use Bjit\Payment\Traits\Payjp\Exceptionable;
use Bjit\Payment\Traits\Payjp\Paymentable;
use Bjit\Payment\Traits\Payjp\Planable;
use Bjit\Payment\Traits\Payjp\Refundable;
use Bjit\Payment\Traits\Payjp\Subscriptionable;
use Illuminate\Http\Request;
use Payjp\Payjp;

class PayjpGateway extends AbstractGateway implements GatewayInterface, CustomerInterface
{
    use Exceptionable;
    use Paymentable;
    use Refundable;
    use Customerable;
    use Cardable;
    use Planable;
    use Subscriptionable;
    

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
