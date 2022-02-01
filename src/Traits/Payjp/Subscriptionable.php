<?php 

namespace Bjit\Payment\Traits\Payjp;

use Bjit\Payment\Enums\CmnEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Payjp\Subscription;

trait Subscriptionable 
{
    public function formatSubscriptionListInput($options)
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

    public function formatSubscriptionInput($options)
    {
        $input = [];

        if(isset($options['provider_customer_id'])) {
            $input['customer'] = $options['provider_customer_id'];
        } 
        if(isset($options['provider_plan_id'])) {
            $input['plan'] = $options['provider_plan_id'];
        }    

        $extraInput = Arr::except($options, [
            'provider_customer_id', 'provider_plan_id'
        ]);

        return array_merge($input, $extraInput);
    }

    public function formatSubscriptionResponse($response)
    { 
        if(isset($response['error'])) {
            return $this->formatErrorResponse($response);
        }
        
        return [
            'provider' => CmnEnum::PROVIDER_PAYJP,
            'id' => $response['id'],
            'provider_customer_id' => $response['customer'] ?? null,  
            'provider_plan_id' => $response['plan'] ?? null, 
            'provider_response' => $response
        ];
    } 

    public function createSubscription($options)
    {   
        $subscription = Subscription::create( $this->formatSubscriptionInput($options) );
        //dump($subscription); exit;
        $this->storeSubscriptionInDatabase($subscription);
        return $this->formatSubscriptionResponse($subscription);
    }

    public function retrieveSubscription($subscriptionId, $options = [])
    {
        $subscription = Subscription::retrieve( $subscriptionId ); 
        return $this->formatSubscriptionResponse($subscription);
    }

    public function updateSubscription($subscriptionId, $options = [])
    {
        $subscription = Subscription::retrieve( $subscriptionId ); 

        $updateInput = $this->formatSubscriptionInput($options);
        
        foreach($updateInput as $key => $val) {
            $subscription->$key = $val;
        } 
        //dd($updateInput, $subscription);
        $subscription->save(); 

        $this->updateSubscriptionInDatabase($subscriptionId, $subscription, $options);

        return $this->formatSubscriptionResponse($subscription);
    }

    public function pauseSubscription($subscriptionId, $options = [])
    {
        $subscription = Subscription::retrieve( $subscriptionId );  
        return $subscription->pause();
    }

    public function resumeSubscription($subscriptionId, $options = [])
    {
        $subscription = Subscription::retrieve( $subscriptionId );  
        return $subscription->resume();
    } 

    public function cancelSubscription($subscriptionId, $options = [])
    {
        $subscription = Subscription::retrieve( $subscriptionId );  
        return $subscription->cancel();
    }

    public function deleteSubscription($subscriptionId, $options = [])
    {
        $subscription = Subscription::retrieve( $subscriptionId );  
        $response = $subscription->delete();
        $this->deleteSubscriptionFromDatabase($subscriptionId, $options);
        return $response;
    } 

    public function allSubscriptions($options = [])
    { 
        return Subscription::all($this->formatSubscriptionListInput($options)); 
    }

    private function storeSubscriptionInDatabase($response, $options = [], $customerType = null)
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_SUBSCRIPTION_NAME)->insert([
            'provider' => CmnEnum::PROVIDER_PAYJP, 
            'provider_subscription_id' => $response['id'],
            'provider_customer_id' => $response['customer'],
            'provider_plan_id' => $response['plan']['id'] ?? null, 
            'success_json' => json_encode($response),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    private function updateSubscriptionInDatabase($subscriptionId, $response, $options = [])
    {
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_SUBSCRIPTION_NAME)
            ->where('provider', CmnEnum::PROVIDER_PAYJP)
            ->where('provider_subscription_id', $subscriptionId)
            ->update([ 
                'provider_customer_id' => $response['customer'],
                'provider_plan_id' => $response['plan']['id'] ?? null, 
                'success_json' => json_encode($response), 
                'updated_at' => now()
            ]);
    }

    private function deleteSubscriptionFromDatabase($subscriptionId, $options = [])
    {
        if ( (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            DB::table(CmnEnum::TABLE_CUSTOMER_NAME)
            ->where('provider', CmnEnum::PROVIDER_PAYJP)
            ->where('provider_subscription_id', $subscriptionId)
            ->update(['deleted_at' => now()]);
        }
    }
    
}