<?php 

namespace Bjit\Payment\Traits\Stripe;

use Bjit\Payment\Enums\CmnEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

trait Cardable 
{
    public function formatCardInput($options)
    {
        $input = [];

        if(isset($options['nonce'])) {
            $input['source'] = $options['nonce'];
        }

        $extraInput = Arr::except($options, [
            'nonce' 
        ]); 

        return array_merge($input, $extraInput);
    }

    public function formatCardResponse($response)
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
        $card = $this->stripe->customers->createSource($customerId, $this->formatCardInput( $options ) );
        $this->storeCustomer($card);
        return $this->formatCardResponse($card);
    }

    public function retrieveCard($cardId, $options = [])
    {
        return $this->formatCardResponse($this->stripe->customers->retrieveSource( $cardId, $options ));
    }

    public function updateCard($customerId, $cardId, $options = [])
    {
        return $this->stripe->customers->updateSource( $customerId, $cardId, $this->formatCardInput( $options ) );
    }

    public function deleteCard($customerId, $cardId, $options = [])
    {
        if ( (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            DB::table(CmnEnum::TABLE_CARD_NAME)
            ->where('provider', CmnEnum::PROVIDER_STRIPE)
            ->where('provider_customer_id', $customerId)
            ->where('provider_card_id', $cardId)
            ->delete();
        }
         
        return $this->stripe->customers->deleteSource( $customerId, $cardId, $options );
    } 

    public function allCards($options = [])
    { 
        return $this->stripe->customers->allSources($options); 
    }

    private function storeCard($response, $options = [], $cardType = null)
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_CARD_NAME)->insert([
            'provider' => CmnEnum::PROVIDER_STRIPE,
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