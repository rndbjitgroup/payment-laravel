<?php 

namespace Bjit\Payment\Traits\Stripe;

use Bjit\Payment\Enums\CmnEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

trait Cardable 
{
    private function formatCardListInput($options)
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

    private function formatCardInput($options)
    {
        $input = [];

        if(isset($options['nonce'])) {
            $input['source'] = $options['nonce'];
        }

        if(isset($options['country'])) {
            $input['address_country'] = $options['country'];
        }

        $extraInput = Arr::except($options, [
            'nonce', 'country'
        ]); 

        return array_merge($input, $extraInput);
    }

    private function formatCardResponse($response)
    { 
        return [
            'provider' => CmnEnum::PROVIDER_STRIPE,
            'id' => $response['id'],
            'brand' => $response['brand'] ?? null, 
            'country' => $response['country'] ?? null, 
            'customer' => $response['customer'] ?? null, 
            'exp_month' => $response['exp_month'] ?? null, 
            'exp_year' => $response['exp_year'] ?? null,
            'last4' => $response['last4'] ?? null, 
            'provider_response' => $response
        ];
    } 

    public function createCard($customerId, $options)
    {   
        $response = $this->stripe->customers->createSource($customerId, $this->formatCardInput( $options ) );
        $this->storeCardInDatabase($response);
        return $this->formatCardResponse($response);
    }

    public function retrieveCard($cardId, $options = [])
    {
        return $this->formatCardResponse($this->stripe->customers->retrieveSource( $cardId, $options ));
    }

    public function updateCard($customerId, $cardId, $options = [])
    {
        $response = $this->stripe->customers->updateSource( $customerId, $cardId, $this->formatCardInput( $options ) );
        $this->updateCardInDatabase($customerId, $cardId, $response, $options);
        return $this->formatCardResponse($response);
    }

    public function deleteCard($customerId, $cardId, $options = [])
    {
        $response = $this->stripe->customers->deleteSource( $customerId, $cardId, $options );
        $this->deleteCardFromDatabase($customerId, $cardId, $response, $options);
        return $this->formatCardResponse($response);
    } 

    public function allCards($customerId, $options = [])
    { 
        return $this->stripe->customers->allSources($customerId, $this->formatCardListInput($options)); 
    }

    private function storeCardInDatabase($response, $options = [], $cardType = null)
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_CARD_NAME)->insert([
            'provider' => CmnEnum::PROVIDER_STRIPE,
            'provider_customer_id' => $response['customer'],
            'provider_card_id' => $response['id'],
            'customer_id' => $this->getCustomerIdByProviderCustomerId($response['customer']),
            'brand' => $response['brand'],
            'country' => $response['country'],  
            'exp_month' => $response['exp_month'],
            'exp_year' => $response['exp_year'],
            'last4' => $response['last4'],
            'success_json' => json_encode($response),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    private function updateCardInDatabase($customerId, $cardId, $response, $options = [], $cardType = null)
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_CARD_NAME)
            ->where('provider', CmnEnum::PROVIDER_STRIPE)
            ->where('provider_customer_id', $customerId)
            ->where('provider_card_id', $cardId)
            ->update([
                'customer_id' => $this->getCustomerIdByProviderCustomerId($response['customer']),
                'brand' => $response['brand'],
                'country' => $response['country'],  
                'exp_month' => $response['exp_month'],
                'exp_year' => $response['exp_year'],
                'last4' => $response['last4'],
                'success_json' => json_encode($response),
                'created_at' => now(),
                'updated_at' => now()
            ]);
    }

    private function deleteCardFromDatabase($customerId, $cardId, $options)
    {
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_CARD_NAME)
            ->where('provider', CmnEnum::PROVIDER_STRIPE)
            ->where('provider_customer_id', $customerId)
            ->where('provider_card_id', $cardId)
            ->update(['deleted_at' => now()]);
    }

    private function getCustomerIdByProviderCustomerId($id)
    {
        return optional(
            DB::table(CmnEnum::TABLE_CUSTOMER_NAME)->where('provider_customer_id', $id)->first(['id'])
        )->id;
    }

}