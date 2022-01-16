<?php

namespace Bjit\Payment\Gateways;

use Bjit\Payment\Enums\CmnEnum;
use Bjit\Payment\Gateways\Interfaces\GatewayInterface;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Payjp\Charge;
use Payjp\Payjp;
use PayPay\OpenPaymentAPI\Client;
use PayPay\OpenPaymentAPI\Models\AccountLinkPayload;
use PayPay\OpenPaymentAPI\Models\CapturePaymentAuthPayload;
use PayPay\OpenPaymentAPI\Models\CreateQrCodePayload;
use PayPay\OpenPaymentAPI\Models\OrderItem;
use PayPay\OpenPaymentAPI\Models\RefundPaymentPayload;

class PaypayGateway extends AbstractGateway implements GatewayInterface
{

    /**
     * The scopes being requested.
     *
     * @var object
     */ 

    private $paypay;
    private $totalAmount = 0;

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

    public function formatPaymentInput($options)
    {
        return [
            'amount' => $options['amount'],
            'currency' => strtoupper($options['currency']) ?? 'JPY',
            //'card' => $options['nonce'] ?? '',
            //'description' => $options['description'] ?? ''/
        ];
    }

    public function formatCheckoutData($options)
    {
        $orderItems = [];
        if (isset($options['order_items']) && is_array($options['order_items'])) {
           foreach($options['order_items'] as $item) {
                $orderItems[] = (new OrderItem())
                    ->setName($item['name'])
                    ->setQuantity($item['quantity'])
                    ->setUnitPrice([
                        'amount' => $item['price']['amount'], 
                        'currency' => $item['price']['currency']
                    ]);
                $this->totalAmount += $item['price']['amount'];
           } 
        }
        return $orderItems;
    }

    public function formatPaymentResponse($response)
    {
        
    }

    public function accountLinkPayload()
    {
        $payload = new AccountLinkPayload();
        $payload
            ->setScopes($this->additionalConfig['scopes'])
            ->setRedirectUrl($this->additionalConfig['redirect'])
            ->setReferenceId(uniqid("ppap" . date('YmdHis')));
        //dd($this->additionalConfig, $this->paypay, $payload);

        return $this->paypay->user->createAccountLinkQrCode($payload);
    }

    public function allPayments($options)
    {
        return Charge::all([
            'limit' => $options['limit'] ?? CmnEnum::LIMIT, 
            'offset' => $options['offset'] ?? CmnEnum::OFFSET
        ]);
    }

    public function createPayment($options)
    { 
         
        $cQCPayload = new CreateQrCodePayload();
        $cQCPayload->setMerchantPaymentId(uniqid('ppp') . date('YmdHis'));
        $cQCPayload->setRequestedAt();
        $cQCPayload->setCodeType("ORDER_QR"); 
        
        $cQCPayload->setAmount($this->formatPaymentData($options));
 
        $cQCPayload->setRedirectType('WEB_LINK');
        $cQCPayload->setRedirectUrl($this->additionalConfig['redirect']);
        //$cQCPayload->setIsAuthorization(true);
 
        $response = $this->paypay->code->createQRCode($cQCPayload);
        //dump($response['data']['merchantPaymentId'], $options); //die;
        $this->capturePayment($response['data']['merchantPaymentId'], $options);
        return $response; 
    }

    public function retrievePayment($paymentId, $options = [])
    {
        return $this->paypay->code->getPaymentDetails($paymentId);
    }

    public function updatePayment($paymentId, $options = [])
    {
        
    }

    public function capturePayment($paymentId, $options = [])
    {
        $cAPayload = new CapturePaymentAuthPayload();
        $cAPayload->setMerchantPaymentId($paymentId);

        $cAPayload->setAmount($this->formatPaymentData($options));

        $cAPayload->setMerchantCaptureId(uniqid('ppmc') . date('YmdHis'));
        $cAPayload->setRequestedAt();
        $cAPayload->setOrderDescription("ORDER DESCRIPTION TEST 101");
        //$cAPayload->setCodeType("ORDER_QR");

        return $this->paypay->payment->capturePaymentAuth($cAPayload);
    }

    public function cancelPayment($paymentId, $options = [])
    {
        return $this->paypay->payment->cancelPayment($paymentId);
    }

    public function deletePayment($codeId, $options = [])
    {
        return $this->paypay->code->deleteQRCode($codeId);
    }

    public function createCheckout($options)
    { 
        $cQCPayload = new CreateQrCodePayload();
        $cQCPayload->setMerchantPaymentId(uniqid('ppcp') . date('YmdHis'));
        $cQCPayload->setRequestedAt();
        $cQCPayload->setCodeType("ORDER_QR");

        $cQCPayload->setOrderItems($this->formatCheckoutData($options));
         
        $cQCPayload->setAmount([
            'amount' => $this->totalAmount,
            'currency' => $options['order_items'][CmnEnum::ZERO]['price']['currency'] 
        ]);
 
        $cQCPayload->setRedirectType('WEB_LINK');
        $cQCPayload->setRedirectUrl($this->additionalConfig['redirect']);
 
        $response = $this->paypay->code->createQRCode($cQCPayload);

        return $response; 
    } 

    public function retrieveCheckout($csId, $options = [])
    { 
        return $this->paypay->code->getPaymentDetails($csId);
    } 

    public function refundPayment($paymentId, $options = [])
    {
        $amount = [
            "amount" => 1,
            "currency" => "JPY"
        ];

        $rPPayload = new RefundPaymentPayload();
        $rPPayload
          ->setMerchantRefundId(uniqid('rf' . date('YmdHis')))
          ->setPaymentId($paymentId)
          ->setAmount($amount)
          ->setRequestedAt(); 

        return $this->paypay->refund->refundPayment($rPPayload);
    }

    public function retrieveRefund($refundId, $options = [])
    {
        return $this->stripe->refunds->retrieve( $refundId, $options );
    }

    public function updateRefund($refundId, $options = [])
    {
        return $this->stripe->refunds->update( $refundId, $options );
    } 

}
