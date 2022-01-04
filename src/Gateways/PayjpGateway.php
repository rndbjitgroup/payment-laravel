<?php

namespace Bjit\Payment\Gateways;

use Bjit\Payment\Enums\CmnEnum;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Payjp\Charge;
use Payjp\Payjp; 

class PayjpGateway extends AbstractGateway implements GatewayInterface
{

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

    public function createCustomer()
    {

    }

    public function formatPaymentInput($options)
    {
        return [
            'amount' => $options['amount'],
            'currency' => $options['currency'] ?? 'jpy',
            'card' => $options['nonce'] ?? '',
            'description' => $options['description'] ?? ''
            //'tenant' => $options['description'] ?? ''
        ];
    }

    public function formatPaymentResponse($response)
    {
        return $response;
    }

    // public function formatCheckoutData($options)
    // {
    //     return $options;
    // }

    public function allPayments($options)
    {
        return Charge::all([
            'limit' => $options['limit'] ?? CmnEnum::LIMIT, 
            'offset' => $options['offset'] ?? CmnEnum::OFFSET
        ]);
    }

    public function createPayment($options)
    { 
        //dd($options, $this->formatPaymentInput($options));
        $response = Charge::create($this->formatPaymentInput($options)); 
        $this->storePayment($response, $options, CmnEnum::PT_DIRECT_PAYMENT);
        return $this->formatPaymentResponse($response);
    }

    public function retrievePayment($paymentId, $options = [])
    {
        return Charge::retrieve($paymentId);
    }

    public function updatePayment($paymentId, $options = [])
    {
        $obj = Charge::retrieve($paymentId);
        if($options['amount']) {
            $obj->amount = $options['amount'];
        }
        if($options['description']) {
            $obj->description = $options['description'];
        }
        if($options['currency']) {
            $obj->currency = $options['currency'];
        }
        return $obj->save();
    }

    public function capturePayment($paymentId, $options = [])
    {
        $ch = Charge::retrieve($paymentId);
        return $ch->capture(); 
    }

    public function cancelPayment($paymentId, $options = [])
    {
         
    } 

    public function refundPayment($paymentId, $options = [])
    {
        $ch = Charge::retrieve($paymentId);
         
        if(is_null($options)) {
            return $ch->refund();
        }
        return $ch->refund($options);
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
            //'state' => $options['state'], 
            'payment_type' => $paymentType,
            'provider' => CmnEnum::PROVIDER_PAYJP,
            'provider_payment_id' => $response['id'],
            'user_id' => Auth::user()->id ?? CmnEnum::ONE,
            'amount' => $response['amount'],
            'currency' => $response['currency'],
            'payment_status' => $response['paid'] ? CmnEnum::PS_PAID : CmnEnum::PS_UNPAID,
            'status' => $response['paid'] ? CmnEnum::STATUS_COMPLETE : CmnEnum::STATUS_OPEN,
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
