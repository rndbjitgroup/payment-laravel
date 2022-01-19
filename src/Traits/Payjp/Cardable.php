<?php 

namespace Bjit\Payment\Traits\Payjp;

use Bjit\Payment\Enums\CmnEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Payjp\Customer;

trait Cardable 
{
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
        $this->storeCard($card);
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

        return $this->formatCardResponse($card);
    }

    public function deleteCard($customerId, $cardId, $options = [])
    {
        $customer = Customer::retrieve( $customerId ); 
        $card = $customer->cards->retrieve($cardId);
        return $card->delete();
    } 

    public function allCards($customerId, $options = [])
    { 
        $options = [
            'limit' => $options['limit'],
            'offset' => $options['offset']
        ];
        return Customer::retrieve($customerId)->cards->all($options); 
    }

    private function storeCard($response, $options = [], $customerType = null)
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_CARD_NAME)->insert([
            'provider' => CmnEnum::PROVIDER_PAYJP,
            'provider_customer_id' => $response['customer'],
            'provider_card_id' => $response['id'],
            'customer_id' => null,
            'brand' => $response['brand'],
            'country' => $response['country'], 
            'customer' => $response['customer'],
            'exp_month' => $response['exp_month'],
            'exp_year' => $response['exp_year'],
            'last4' => $response['last4'],
            'success_json' => json_encode($response),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}