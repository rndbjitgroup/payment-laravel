<?php 

namespace Bjit\Payment\Traits\Payjp;

use Bjit\Payment\Enums\CmnEnum;
use Bjit\Payment\Helpers\CmnHelper;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Payjp\Customer;

trait Cardable 
{
    public function formatCardListInput($options)
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

    public function formatCardInput($options)
    {
        $input = [];

        if(isset($options['nonce'])) {
            $input['card'] = $options['nonce'];
        } 

        $extraInput = Arr::except($options, [
            'nonce'
        ]);

        return array_merge($input, $extraInput);
    }

    public function formatCardResponse($response)
    { 
        if(isset($response['error'])) {
            return $this->formatErrorResponse($response);
        }
        
        return [
            'provider' => CmnEnum::PROVIDER_PAYJP,
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

    public function createCard($options, $customerId = null)
    {   
        $customer = Customer::retrieve( $customerId );
        $card = $customer->cards->create( $this->formatCardInput($options) );
        $this->storeCardInDatabase($card);
        return $this->formatCardResponse($card);
    }

    public function retrieveCard($customerId, $cardId, $options = [])
    {
        $customer = Customer::retrieve( $customerId ); 
        return $this->formatCardResponse($customer->cards->retrieve($cardId));
    }

    public function updateCard($customerId, $cardId, $options = [])
    {
        $customer = Customer::retrieve( $customerId );
        $card = $customer->cards->retrieve($cardId);

        $updateInput = $this->formatCardInput($options);
        
        foreach($updateInput as $key => $val) {
            $card->$key = $val;
        } 
        $card->save(); 
        $this->updateCardInDatabase($customerId, $cardId, $card, $options);
        return $this->formatCardResponse($card);
    }

    public function deleteCard($customerId, $cardId, $options = [])
    {
        $customer = Customer::retrieve( $customerId ); 
        $card = $customer->cards->retrieve($cardId);
        $response = $card->delete();
        $this->deleteCardFromDatabase($customerId, $cardId, $options);
        return $response;
    } 

    public function allCards($customerId, $options = [])
    { 
        return Customer::retrieve($customerId)->cards->all($this->formatCardListInput($options)); 
    }

    private function storeCardInDatabase($response, $options = [], $customerType = null)
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_CARD_NAME)->insert([
            'provider' => CmnEnum::PROVIDER_PAYJP,
            'provider_customer_id' => $response['customer'],
            'provider_card_id' => $response['id'],
            'customer_id' => $this->getCustomerIdByProviderCustomerId($response['customer']),
            'brand' => $response['brand'],
            'country' => $response['country'],  
            'exp_month' => $response['exp_month'],
            'exp_year' => $response['exp_year'],
            'last4' => $response['last4'],
            'success_json' => CmnHelper::jsonEncodePrivate($response),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    private function updateCardInDatabase($customerId, $cardId, $response, $options = [])
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_CARD_NAME)
            ->where('provider', CmnEnum::PROVIDER_PAYJP)
            ->where('provider_customer_id', $customerId)
            ->where('provider_card_id', $cardId)
            ->update([  
                'customer_id' => $this->getCustomerIdByProviderCustomerId($response['customer']),
                'brand' => $response['brand'],
                'country' => $response['country'],  
                'exp_month' => $response['exp_month'],
                'exp_year' => $response['exp_year'],
                'last4' => $response['last4'],
                'success_json' => CmnHelper::jsonEncodePrivate($response), 
                'updated_at' => now()
            ]);
    }

    private function deleteCardFromDatabase($customerId, $cardId, $options)
    {
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_CARD_NAME)
            ->where('provider', CmnEnum::PROVIDER_PAYJP)
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