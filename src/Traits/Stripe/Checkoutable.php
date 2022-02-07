<?php 

namespace Bjit\Payment\Traits\Stripe;

use Bjit\Payment\Enums\CmnEnum;
use Illuminate\Support\Arr;

trait Checkoutable
{
    private function formatCheckoutListInput($options)
    {
        $input = [];

        if (isset($options['limit'])) {
            $input['limit'] = $options['limit'];
        }

        if (isset($options['offset'])) {
            $input['offset'] = $options['offset'];
        }

        $extraInput = Arr::except($options, [
            'limit', 'offset'
        ]); 

        return array_merge($input, $extraInput);
    }

    private function formatCheckoutInput($options)
    { 
        $items = [];
        foreach($options['order_items'] as $item) 
        {
            $items[] = [
                'price_data' => [
                    'currency' => $item['price']['currency'],
                    'product_data' => [
                        'name' => $item['name'],
                    ],
                    'unit_amount' => $item['price']['amount'],
                ],
                'quantity' => $item['quantity'],
            ];
        }

        $chekoutData = [
            'payment_method_types' => $options['payment_method_types'],
            'line_items' => $items,
            'mode' => $options['mode'],
            'payment_intent_data' => $options['payment_intent_data'],
            'success_url' => $options['success_url'] . '?state=' . $options['state'], 
            'cancel_url' => $options['cancel_url'] . '?state=' . $options['state'],
        ]; 

        $extraData = Arr::except($options, [
            'payment_method_types', 'order_items', 'mode', 'payment_intent_data', 
            'success_url', 'cancel_url', 'state'
        ]);

        $chekoutData = array_merge($chekoutData, $extraData); 
        
        return $chekoutData;
    }

    private function formatCheckoutResponse($response)
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
        $response = $this->stripe->checkout->sessions->create($this->formatCheckoutInput($options)); 
        $this->storePaymentInDatabase($response, $options, CmnEnum::PT_CHECKOUT_PAYMENT);
        return $this->formatCheckoutResponse($response);
    }

    public function expireCheckout($csId, $options = [])
    { 
        return $this->stripe->checkout->sessions->expire($csId, $options); 
    }

    public function retrieveCheckout($csId, $options = [])
    { 
        return $this->formatCheckoutResponse($this->stripe->checkout->sessions->retrieve($csId, $options)); 
    }

    public function allCheckouts($options = [])
    { 
        return $this->stripe->checkout->sessions->all($this->formatCheckoutListInput($options)); 
    }

    public function allCheckoutLineItems($csId, $options = [])
    { 
        return $this->stripe->checkout->sessions->allLineItems($csId, $this->formatCheckoutListInput($options)); 
    }
}