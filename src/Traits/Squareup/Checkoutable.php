<?php 

namespace Bjit\Payment\Traits\Squareup;

use Bjit\Payment\Enums\CmnEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Square\Models\OrderLineItemAppliedTax;
use Square\Models\OrderLineItemTax;
use Square\Models\Order;
use Square\Models\CreateOrderRequest;
use Square\Models\Address;
use Square\Models\CreateCheckoutRequest;
use Square\Models\Money;
use Square\Models\OrderLineItem;
use Square\Models\OrderLineItemDiscount;

trait Checkoutable
{
    private function formatPaymentLinkListInput($options)
    {
        $input = [];
 
        $input[] = $options['cursor'] ?? CmnEnum::EMPTY_NULL; 
        $input[] = $options['limit'] ?? CmnEnum::EMPTY_NULL; 

        return $input;
    }

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

    public function formatCheckoutInput($options)
    { 
        $uniqueUuid = (string) Str::uuid();
        // $orderLineItemAppliedTax = new OrderLineItemAppliedTax($uniqueUuid);
        // $appliedTaxes = [$orderLineItemAppliedTax];

        $items = [];
        foreach($options['order_items'] as $itemKey => $item) 
        {
            // -- FOR ITEM -- 
            $items['base_price_money'][$itemKey] = new Money();
            $items['base_price_money'][$itemKey]->setAmount($item['price']['amount']);
            $items['base_price_money'][$itemKey]->setCurrency($item['price']['currency']);

            $items['order_line_items'][$itemKey] = new OrderLineItem($item['quantity']);
            if(isset($item['item_type'])) {
                $items['order_line_items'][$itemKey]->setName($item['item_type']);
            }
            $items['order_line_items'][$itemKey]->setName($item['name']);
            if($itemKey == 0) {
                //$items['order_line_items'][$itemKey]->setAppliedTaxes($appliedTaxes);
            }
            $items['order_line_items'][$itemKey]->setBasePriceMoney($items['base_price_money'][$itemKey]);

            // -- FOR TAXES -- 

            $items['tax_applied_money'][$itemKey] = new Money();
            if(isset($item['tax']['applied_amount'])) {
                $items['tax_applied_money'][$itemKey]->setAmount($item['tax']['applied_amount']);
            }
            if(isset($item['tax']['applied_currency'])) {
                $items['tax_applied_money'][$itemKey]->setCurrency($item['tax']['applied_currency']);
            }

            $items['order_line_item_taxs'][$itemKey] = new OrderLineItemTax();
            //$items['order_line_item_taxs'][$itemKey]->setUid($uniqueUuid);

            if(isset($item['tax']['name'])) {
                $items['order_line_item_taxs'][$itemKey]->setName($item['tax']['name']);
            }
            if(isset($item['tax']['type'])) {
                $items['order_line_item_taxs'][$itemKey]->setType($item['tax']['type']);
            }

            $items['order_line_item_taxs'][$itemKey]->setAppliedMoney($items['tax_applied_money'][$itemKey]);

            if(isset($item['tax']['percentage'])) {
                $items['order_line_item_taxs'][$itemKey]->setPercentage($item['tax']['percentage']);
            }
            if(isset($item['tax']['metadata'])) {
                $items['order_line_item_taxs'][$itemKey]->setMetadata($item['tax']['metadata']);
            }
            if(isset($item['tax']['scope'])) {
                $items['order_line_item_taxs'][$itemKey]->setScope($item['tax']['scope']);
            }
            

            // -- FOR DISCOUNTS -- 
            
            $items['discount_money'][$itemKey] = new Money();
            if(isset($item['discount']['amount'])) {
                $items['discount_money'][$itemKey]->setAmount($item['discount']['amount']);
            }
            if(isset($item['discount']['currency'])) {
                $items['discount_money'][$itemKey]->setCurrency($item['discount']['currency']);
            }

            $items['discount_applied_money'][$itemKey] = new Money();
            if(isset($item['discount']['applied_amount'])) {
                $items['discount_applied_money'][$itemKey]->setAmount($item['discount']['applied_amount']);
            }
            if(isset($item['discount']['applied_currency'])) {
                $items['discount_applied_money'][$itemKey]->setCurrency($item['discount']['applied_currency']);
            }

            $items['order_line_item_discounts'][$itemKey] = new OrderLineItemDiscount();
            //$items['order_line_item_discounts'][$itemKey]->setUid($uniqueUuid); 
            if(isset($item['discount']['name'])) {
                $items['order_line_item_discounts'][$itemKey]->setName($item['discount']['name']);
            }
            if(isset($item['discount']['type'])) {
                $items['order_line_item_discounts'][$itemKey]->setType($item['discount']['type']);
            }
           
            $items['order_line_item_discounts'][$itemKey]->setAmountMoney($items['discount_money'][$itemKey]);
            $items['order_line_item_discounts'][$itemKey]->setAppliedMoney($items['discount_applied_money'][$itemKey]);
          
            if(isset($item['discount']['metadata'])) {
                $items['order_line_item_discounts'][$itemKey]->setMetadata($item['discount']['metadata']);
            }
            if(isset($item['discount']['scope'])) {
                $items['order_line_item_discounts'][$itemKey]->setScope($item['discount']['scope']);
            }
        } 
 
        $lineItems = $items['order_line_items'];
        $lineItemTaxes = $items['order_line_item_taxs'];
        $lineItemDiscounts = $items['order_line_item_discounts'];
         
        $order1 = new Order($options['squareup_shop_id']);
        
        if(isset($options['reference_id'])) {
            $order1->setReferenceId($options['reference_id']);
        }
        if(isset($options['customer_id'])) {
            $order1->setCustomerId($options['customer_id']);
        }
        $order1->setLineItems($lineItems);
        $order1->setTaxes($lineItemTaxes);

        if(isset($lineItemDiscounts) && !empty($lineItemDiscounts)) {
            $order1->setDiscounts($lineItemDiscounts);
        }

        $order = new CreateOrderRequest();
        $order->setOrder($order1);
        $order->setIdempotencyKey($uniqueUuid); //Str::uuid()

        $prePopulateShippingAddress = new Address();
        if(isset($options['shipping_address']['line1'])) {
            $prePopulateShippingAddress->setAddressLine1($options['shipping_address']['line1']);
        }
        if(isset($options['shipping_address']['line2'])) {
            $prePopulateShippingAddress->setAddressLine2($options['shipping_address']['line2']);
        }
        if(isset($options['shipping_address']['locality'])) {
            $prePopulateShippingAddress->setLocality($options['shipping_address']['locality']);
        }
        if(isset($options['shipping_address']['administrative_district_level1'])) {
            $prePopulateShippingAddress->setAdministrativeDistrictLevel1($options['shipping_address']['administrative_district_level1']);
        }
        if(isset($options['shipping_address']['postal_code'])) {
            $prePopulateShippingAddress->setPostalCode($options['shipping_address']['postal_code']);
        }
        if(isset($options['shipping_address']['country'])) {
            $prePopulateShippingAddress->setCountry($options['shipping_address']['country']);
        } 
         
        $body = new CreateCheckoutRequest(Str::uuid(), $order);
        if(isset($prePopulateShippingAddress)) {
            if(isset($options['shipping_address']['ask_for_shipping_address'])) {
                $body->setAskForShippingAddress($options['shipping_address']['ask_for_shipping_address']);
            }
            if(isset($options['shipping_address']['merchant_support_email'])) {
                $body->setMerchantSupportEmail($options['shipping_address']['merchant_support_email']);
            }
            if(isset($options['shipping_address']['pre_populate_buyer_email'])) {
                $body->setPrePopulateBuyerEmail($options['shipping_address']['pre_populate_buyer_email']);
            }
            $body->setPrePopulateShippingAddress($prePopulateShippingAddress);
        }

        if(isset($options['success_url'])) {
            $body->setRedirectUrl($options['success_url']);
        } 
        
        return [
            'idempotency_key' => $uniqueUuid,
            'body' => $body
        ];
    }

    public function formatCheckoutResponse($response)
    {
        if (!$response->isSuccess()) {
            return ['error' => $response->getErrors()];
        }

        $responseFromStr = json_decode($response->getBody(), true);
        $response = $responseFromStr['checkout'];

        $checkoutResponse = $response;
        $checkoutId = $response['id'];
        $response = $response['order'];

        return [
            'provider' => CmnEnum::PROVIDER_SQUAREUP,
            'id' => $checkoutId,
            'checkout_page_url' => $checkoutResponse['checkout_page_url'],
            'amount' => $response['total_money']['amount'] ?? CmnEnum::ZERO,
            'currency' => $response['total_money']['currency'], 
            'payment_status' => $response['payment_status'] ?? CmnEnum::EMPTY_NULL,
            'status' => $response['state'],
            'generic_payment_status' => $response['state'] == strtoupper(CmnEnum::STATUS_COMPLETE) ? CmnEnum::PS_PAID : CmnEnum::PS_UNPAID,
            'generic_status' => $response['state'] == strtoupper(CmnEnum::STATUS_COMPLETE) ? CmnEnum::STATUS_COMPLETE : CmnEnum::STATUS_OPEN,
            'description' => $response['description'] ?? CmnEnum::EMPTY_NULL,
            'payment_type' => $response['payment_method_details']['type'] ?? $response['payment_method_types'][CmnEnum::ZERO] ?? CmnEnum::EMPTY_NULL,
            'card_brand' => $response['payment_method_details']['card']['brand'] ?? CmnEnum::EMPTY_NULL,
            'last_4_digit' => $response['payment_method_details']['card']['last4'] ?? CmnEnum::EMPTY_NULL,
            'created_at' => $response['created_at'],
            'customer_name' => $response['customer_details']['name'] ?? CmnEnum::EMPTY_NULL,
            'customer_email' => $response['customer_details']['email'] ?? CmnEnum::EMPTY_NULL,
            'customer_phone' => $response['customer_details']['phone'] ?? CmnEnum::EMPTY_NULL,
            'provider_response' => $checkoutResponse
        ];
    }

    public function createCheckout($options)
    { 
        $responseFromInput = $this->formatCheckoutInput($options);
        $response = $this->squareup->getCheckoutApi()->createCheckout($options['squareup_shop_id'], $responseFromInput['body']);
         
        if ($response->isSuccess()) {
            $responseFromStr = json_decode($response->getBody(), true);
            $responseFromStr = $responseFromStr['checkout'];
            $options['idempotency_key'] = $responseFromInput['idempotency_key'];
            $this->storeCheckoutInDatabase($responseFromStr, $options, CmnEnum::PT_CHECKOUT_PAYMENT);
        }
        return $this->formatCheckoutResponse($response);
    }

    public function completeCheckout($csId, $options)
    {
        $response = DB::table(CmnEnum::TABLE_PAYMENT_NAME)->where('provider_payment_id', $csId)->first();
      
        if($response) {
            $response = json_decode($response->success_json, true); 
            return $this->updateCheckoutInDatabase($csId, $response, $options);
        }
        return CmnEnum::FLASE;
    }

    public function deleteCheckout($csId, $options = [])
    { 
        //
    }

    public function deletePaymentLink($csId, $options = [])
    { 
        return $this->squareup->getCheckoutApi()->deletePaymentLink($csId); 
    }

    public function retrieveCheckout($csId, $options = [])
    { 
        //
    }

    public function retrievePaymentLink($csId, $options = [])
    { 
        return $this->formatCheckoutResponse($this->squareup->getCheckoutApi()->retrievePaymentLink($csId)); 
    }

    public function updateCheckout($csId, $options = [])
    {
        //
    }

    public function updatePaymentLink($csId, $options = [])
    {
        $response = $this->squareup->getCheckoutApi()->updatePaymentLink($csId, $options );
        $this->updatePaymentInDatabase($csId, $response, $options);
        return $this->formatPaymentResponse($response);
    }

    public function allCheckouts($options = [])
    { 
        // 
    }

    public function allPaymentLinks($options = [])
    { 
        return $this->squareup->getCheckoutApi()->listPaymentLinks(...$this->formatPaymentLinkListInput($options)); 
    }

    private function storeCheckoutInDatabase($response, $options, $paymentType)
    {
        if (!(config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        $checkoutResponse = $response;
        $checkoutId = $response['id'];
        $response = $response['order'];

        return DB::table(CmnEnum::TABLE_PAYMENT_NAME)->insert([
            'state' => $options['state'] ?? CmnEnum::EMPTY_NULL,
            'type' => $paymentType,
            'provider' => CmnEnum::PROVIDER_SQUAREUP,
            'provider_payment_id' => $checkoutId,
            'provider_payment_idempotency_key' => $options['idempotency_key'] ?? CmnEnum::EMPTY_NULL,
            'user_id' => Auth::user()->id ?? CmnEnum::ONE,
            'amount' => $response['total_money']['amount'] ?? CmnEnum::ZERO,
            'currency' => $response['total_money']['currency'],
            //'captured' => $response['card_details']['status'] == strtoupper(CmnEnum::STATUS_CAPTURED) ? CmnEnum::ONE : CmnEnum::ZERO,
            //'payment_status' => $response['state'] ?? CmnEnum::EMPTY_NULL,
            'status' => $response['state'],
            'generic_payment_status' => $response['state'] == strtoupper(CmnEnum::STATUS_COMPLETE) ? CmnEnum::PS_PAID : CmnEnum::PS_UNPAID,
            'generic_status' => $response['state'] == strtoupper(CmnEnum::STATUS_COMPLETE) ? CmnEnum::STATUS_COMPLETE : CmnEnum::STATUS_OPEN,
            'description' => $response['description'] ?? CmnEnum::EMPTY_NULL,
            'payment_type' => $response['card_details']['card']['card_type'] ?? CmnEnum::EMPTY_NULL,
            'card_brand' => $response['card_details']['card']['card_brand'] ?? CmnEnum::EMPTY_NULL,
            'last_4_digit' => $response['card_details']['card']['last_4'] ?? CmnEnum::EMPTY_NULL,
            'customer_name' => $response['customer_details']['name'] ?? CmnEnum::EMPTY_NULL,
            'customer_email' => $response['customer_details']['email'] ?? CmnEnum::EMPTY_NULL,
            'customer_phone' => $response['customer_details']['phone'] ?? CmnEnum::EMPTY_NULL,
            'success_json' => json_encode($checkoutResponse),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    private function updateCheckoutTableInDatabase($paymentId, $options) 
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_PAYMENT_NAME)
            ->where('provider', CmnEnum::PROVIDER_SQUAREUP)
            ->where('provider_payment_id', $paymentId)
            ->update($options);
    }
    private function rearrangeCheckoutDataForUpdate($response, $options = [])
    {
        $checkoutResponse = $response;
        $checkoutResponse['order']['state'] = strtoupper(CmnEnum::STATUS_COMPLETE);
        $checkoutId = $checkoutResponse['id'];
        $response = $checkoutResponse['order']; 

        return [
            'user_id' => Auth::user()->id ?? CmnEnum::ONE,
            'amount' => $response['total_money']['amount'] ?? CmnEnum::ZERO,
            'currency' => $response['total_money']['currency'],
            //'captured' => $response['card_details']['status'] == strtoupper(CmnEnum::STATUS_CAPTURED) ? CmnEnum::ONE : CmnEnum::ZERO,
            //'payment_status' => $response['state'] ?? CmnEnum::EMPTY_NULL,
            'status' => $response['state'],
            'generic_payment_status' => $response['state'] == strtoupper(CmnEnum::STATUS_COMPLETE) ? CmnEnum::PS_PAID : CmnEnum::PS_UNPAID,
            'generic_status' => $response['state'] == strtoupper(CmnEnum::STATUS_COMPLETE) ? CmnEnum::STATUS_COMPLETE : CmnEnum::STATUS_OPEN,
            'description' => $response['description'] ?? CmnEnum::EMPTY_NULL,
            'payment_type' => $response['card_details']['card']['card_type'] ?? CmnEnum::EMPTY_NULL,
            'card_brand' => $response['card_details']['card']['card_brand'] ?? CmnEnum::EMPTY_NULL,
            'last_4_digit' => $response['card_details']['card']['last_4'] ?? CmnEnum::EMPTY_NULL,
            'customer_name' => $response['customer_details']['name'] ?? CmnEnum::EMPTY_NULL,
            'customer_email' => $response['customer_details']['email'] ?? CmnEnum::EMPTY_NULL,
            'customer_phone' => $response['customer_details']['phone'] ?? CmnEnum::EMPTY_NULL,
            'success_json' => json_encode($checkoutResponse), 
            'updated_at' => now()
        ];
    }

    private function updateCheckoutInDatabase($paymentId, $response, $options) 
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return $this->updateCheckoutTableInDatabase($paymentId, $this->rearrangeCheckoutDataForUpdate($response, $options));
    }
 
}