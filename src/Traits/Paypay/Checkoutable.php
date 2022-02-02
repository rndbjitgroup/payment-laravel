<?php 

namespace Bjit\Payment\Traits\Paypay;

use Bjit\Payment\Enums\CmnEnum;
use Illuminate\Support\Arr;
use PayPay\OpenPaymentAPI\Models\CreateQrCodePayload;
use PayPay\OpenPaymentAPI\Models\OrderItem;

trait Checkoutable
{
    public function formatCheckoutInput($options)
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

    public function formatCheckoutResponse($response)
    {
        return [
            'provider' => CmnEnum::PROVIDER_STRIPE,
            'id' => $response['id'],
            'amount' => $response['amount_total'], 
            'currency' => $response['currency'], 
            'payment_status' => $response['payment_status'] ?? null,
            'status' => $response['status'] ?? null,
            'generic_payment_status' => $response['payment_status'] ?? null,
            'generic_status' => $response['status'] ?? null,
            'description' => $response['description'],
            'payment_type' => $response['payment_method_details']['type'] ?? $response['payment_method_types'][CmnEnum::ZERO] ?? null,
            'card_brand' => $response['payment_method_details']['card']['brand'] ?? null,
            'last_4_digit' => $response['payment_method_details']['card']['last4'] ?? null,
            'created_at' => $response['created'],
            'customer_name' => $response['customer_details']['name'] ?? null,
            'customer_email' => $response['customer_details']['email'] ?? null,
            'customer_phone' => $response['customer_details']['phone'] ?? null,
            'provider_response' => $response
        ];
    }

    public function createCheckout($options)
    { 
        $cQCPayload = new CreateQrCodePayload();
        $cQCPayload->setMerchantPaymentId(uniqid('ppcp') . date('YmdHis'));
        $cQCPayload->setRequestedAt();
        $cQCPayload->setCodeType(CmnEnum::PAYPAY_ORDER_TYPE); 

        $cQCPayload->setOrderItems($this->formatCheckoutInput($options));
         
        $cQCPayload->setAmount([
            'amount' => $this->totalAmount,
            'currency' => $options['order_items'][CmnEnum::ZERO]['price']['currency'] 
        ]);
 
        $cQCPayload->setRedirectType(CmnEnum::PAYPAY_REDIRECT_TYPE_WEB_LINK);
        $cQCPayload->setRedirectUrl($this->additionalConfig['redirect']);
 
        $response = $this->paypay->code->createQRCode($cQCPayload);

        //$this->storePaymentInDatabase($response, $options, CmnEnum::PT_CHECKOUT_PAYMENT);

        return $response; 
    } 

    public function retrieveCheckout($csId, $options = [])
    { 
        return $this->paypay->code->getPaymentDetails($csId);
    } 

    public function allCheckouts($options = [])
    { 
        //return $this->stripe->checkout->sessions->all($options); 
    }
 
}