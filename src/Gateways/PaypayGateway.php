<?php

namespace Bjit\Payment\Gateways;

use Bjit\Payment\Gateways\Interfaces\GatewayInterface;
use Bjit\Payment\Traits\Paypay\Checkoutable;
use Bjit\Payment\Traits\Paypay\Paymentable;
use Bjit\Payment\Traits\Paypay\Refundable; 
use Illuminate\Http\Request; 
use PayPay\OpenPaymentAPI\Client; 

class PaypayGateway extends AbstractGateway implements GatewayInterface
{
    use Paymentable;
    use Refundable;
    use Checkoutable;

    /**
     * The scopes being requested.
     *
     * @var object
     */ 

    private $paypay; 

    public function __construct(Request $request, $key, $secret, $additionalConfig = [], $guzzle = [])
    { 
        parent::__construct($request, $key, $secret, $additionalConfig, $guzzle);

        $this->setConfig($this->key, $this->secret, $this->additionalConfig);
    }

    private function setConfig($key, $secret, $additionalConfig)
    { 
        $this->paypay = new Client([
            'API_KEY' => $key,
            'API_SECRET'=> $secret,
            'MERCHANT_ID'=> $additionalConfig['merchant_id']
        ], $additionalConfig['is_live'] ); 
    } 
 
}
