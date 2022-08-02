<?php 

namespace Bjit\Payment\Traits\Stripe;

use Bjit\Payment\Enums\CmnEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; 

trait Subscriptionable 
{
    private function formatSubscriptionListInput($options)
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

    private function formatSubscriptionInput($options)
    {
        $input = [];

        if(isset($options['provider_customer_id'])) {
            $input['customer'] = $options['provider_customer_id'];
        } 

        if(isset($options['provider_plan_id'])) { 
            $input['items'] = [
                ['price' => $options['provider_plan_id']]
            ];
        } 

        $extraInput = Arr::except($options, [
            'provider_customer_id', 'provider_plan_id'
        ]);

        return array_merge($input, $extraInput);
    }

    private function formatSubscriptionResponse($response)
    { 
        return [
            'provider' => CmnEnum::PROVIDER_STRIPE,
            'id' => $response['id'],
            'provider_customer_id' => $response['customer'] ?? null,  
            'provider_plan_id' => $response['plan'] ?? null,  
            'provider_response' => $response
        ];
    } 

    public function createSubscription($options)
    {  
        $response = $this->stripe->subscriptions->create( $this->formatSubscriptionInput( $options ) );
        $this->storeSubscriptionInDatabase($response, $options);
        return $this->formatSubscriptionResponse($response);
    }

    public function retrieveSubscription($subscriptionId, $options = [])
    {
        return $this->formatSubscriptionResponse($this->stripe->subscriptions->retrieve( $subscriptionId, $options ));
    }

    public function updateSubscription($subscriptionId, $options = [])
    {
        $response = $this->stripe->subscriptions->update( $subscriptionId, $this->formatSubscriptionInput( $options ) );
        $this->updateSubscriptionInDatabase($subscriptionId, $response, $options);
        return $this->formatSubscriptionResponse($response);
    }

    public function deleteSubscription($subscriptionId, $options = [])
    {
        $response = $this->stripe->subscriptions->delete( $subscriptionId, $options );
        $this->deleteSubscriptionFromDatabase($subscriptionId, $options);
        return $response;
    } 

    public function allSubscriptions($options = [])
    { 
        return $this->stripe->subscriptions->all($this->formatSubscriptionListInput($options));
    }

    private function storeSubscriptionInDatabase($response, $options = [], $cardType = null)
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_SUBSCRIPTION_NAME)->insert([
            'provider' => CmnEnum::PROVIDER_STRIPE, 
            'provider_subscription_id' => $response['id'], 
            'provider_customer_id' => $response['customer'],
            'provider_plan_id' => $response['plan']['id'] ?? null,  
            'success_json' => json_encode($response),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    private function updateSubscriptionInDatabase($subscriptionId, $response, $options = [], $cardType = null)
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_SUBSCRIPTION_NAME)
            ->where('provider', CmnEnum::PROVIDER_STRIPE)
            ->where('provider_subscription_id', $subscriptionId)
            ->update([ 
                'provider_customer_id' => $response['customer'],
                'provider_plan_id' => $response['plan']['id'] ?? null,  
                'success_json' => json_encode($response), 
                'updated_at' => now()
            ]);
    }

    private function deleteSubscriptionFromDatabase($subscriptionId, $options)
    {
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_SUBSCRIPTION_NAME)
            ->where('provider', CmnEnum::PROVIDER_STRIPE)
            ->where('provider_subscription_id', $subscriptionId)
            ->update(['deleted_at' => now()]);
    }

}