<?php 

namespace Bjit\Payment\Traits\Paypal;

use Bjit\Payment\Enums\CmnEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

trait Checkoutable
{
    public function formatCheckoutInput($options)
    {

        $items = [];
        foreach($options['order_items'] as $item) 
        {
            $itemData = [ 
                'name' => $item['name'],  
                'unit_amount' => [
                    "currency_code"=> $item['price']['currency'],
                    "value"=> $item['price']['unit_amount']
                ],
                'quantity' => $item['quantity'],
                'description' => $item['description']
            ]; 

            $extraData = Arr::except($item, [
                'name', 'price', 'currency', 'quantity', 'description'
            ]);
    
            $items[] = array_merge($itemData, $extraData); 
        }

        $data = [];
        
        if (isset($options['intent'])) {
            $data["intent"] = $options['intent'];
        }
        if (isset($options['success_url'])) {
            $data["application_context"] = [
                "return_url" => $options['success_url'],
                "cancel_url" => $options['cancel_url'],
            ];
        }
        if (isset($options['amount_total'])) {
            $data["purchase_units"] = [
                [
                    "amount"=> [
                        "currency_code"=> $options['currency'],
                        "value"=> $options['amount_total'], // item_total + tax_total + shipping + handling + insurance - shipping_discount - discount
                        "breakdown" => [
                            "item_total" => [
                                "currency_code"=> $options['currency'],
                                "value"=> $options['item_total'],
                            ],
                            "tax_total" => (isset($options['tax_total']) && !empty($options['tax_total'])) ? [
                                "currency_code"=> $options['currency'],
                                "value"=> $options['tax_total'],
                            ] : null,
                        ]
                    ],
                    'description' => $options['description'] ?? null,
                    'items' => $items ?? null
                ] 
            ];
        }  

        $extraData = Arr::except($options, [
            'intent', 'application_context', 'purchase_units', 'amount_total', 'item_total', 'tax_total', 'currency', 'description', 'order_items', 'state'
        ]); 
        
        return array_merge($data, $extraData);
    }

    public function formatCheckoutResponse($response)
    { 
        return [
            'provider' => CmnEnum::PROVIDER_PAYPAL,
            'id' => $response['id'],
            'amount' => $response['purchase_units'][CmnEnum::ZERO]['payments']['captures'][CmnEnum::ZERO]['amount']['value'] ?? null, 
            'currency' => $response['purchase_units'][CmnEnum::ZERO]['payments']['captures'][CmnEnum::ZERO]['amount']['currency_code'] ?? null, 
            'status' => $response['status'] ?? null,
            'generic_payment_status' => $response['status'] == CmnEnum::STATUS_PAYPAL_COMPLETED ? CmnEnum::PS_PAID : CmnEnum::PS_UNPAID,
            'generic_status' => $response['status'] == CmnEnum::STATUS_PAYPAL_COMPLETED ? CmnEnum::STATUS_COMPLETE : CmnEnum::STATUS_OPEN,
            'description' => $response['description'] ?? null,
            'links' => $response['links'],
            'provider_response' => $response
        ];
    } 

    public function createCheckout($options)
    {  
        $response = $this->paypal->createOrder($this->formatCheckoutInput($options)); 
        $this->storePaymentInDatabase($response, $options, CmnEnum::PT_CHECKOUT_PAYMENT);
        return $this->formatCheckoutResponse($response);
    }

    public function retrieveCheckout($paymentId, $options = [])
    {
        return $this->formatCheckoutResponse($this->paypal->showOrderDetails( $paymentId ));
    }

    public function updateCheckout($paymentId, $options = [])
    {
        $response = $this->paypal->updateOrder( $paymentId, $options );
        $this->updatePaymentInDatabase($paymentId, $response, $options);
        return $this->formatCheckoutResponse($response);
    }

    public function captureCheckout($paymentId, $options = [])
    {
        $response = $this->paypal->capturePaymentOrder( $paymentId );
        $this->updatePaymentInDatabase($paymentId, $response, $options);
        return $this->formatCheckoutResponse($response);
    } 

    // private function storePaymentInDatabase($response, $options, $paymentType)
    // {  
    //     if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
    //         return true;
    //     }

    //     return DB::table(CmnEnum::TABLE_PAYMENT_NAME)->insert([
    //         'state' => $options['state'] ?? null,
    //         'type' => $paymentType,
    //         'provider' => CmnEnum::PROVIDER_PAYPAL,
    //         'provider_payment_id' => $response['id'], 
    //         'user_id' => Auth::user()->id ?? CmnEnum::ONE,
    //         'amount' => $options['amount_total'] ?? $options['amount'] ?? 0,
    //         'currency' => $options['currency'], 
    //         'status' => $response['status'],
    //         'generic_payment_status' => $response['status'] == CmnEnum::STATUS_PAYPAL_COMPLETED ? CmnEnum::PS_PAID : CmnEnum::PS_UNPAID,
    //         'generic_status' => $response['status'] == CmnEnum::STATUS_PAYPAL_COMPLETED ? CmnEnum::STATUS_COMPLETE : CmnEnum::STATUS_OPEN,
    //         'description' => $response['description'] ?? null,
    //         'success_json' => json_encode($response),
    //         'created_at' => now(),
    //         'updated_at' => now()
    //     ]);
    // }

    // private function updatePaymentInDatabase($paymentId, $response, $options) 
    // {  
    //     if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
    //         return true;
    //     }

    //     return DB::table(CmnEnum::TABLE_PAYMENT_NAME)
    //         ->where('provider', CmnEnum::PROVIDER_PAYPAL)
    //         ->where('provider_payment_id', $paymentId)
    //         ->update([  
    //             'user_id' => Auth::user()->id ?? CmnEnum::ONE,
    //             'status' => $response['status'],
    //             'generic_payment_status' => $response['status'] == CmnEnum::STATUS_PAYPAL_COMPLETED ? CmnEnum::PS_PAID : CmnEnum::PS_UNPAID,
    //             'generic_status' => $response['status'] == CmnEnum::STATUS_PAYPAL_COMPLETED ? CmnEnum::STATUS_COMPLETE : CmnEnum::STATUS_OPEN,
    //             'success_json' => json_encode($response),
    //             'created_at' => now(),
    //             'updated_at' => now()
    //         ]);
    // }
}