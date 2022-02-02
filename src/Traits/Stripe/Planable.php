<?php 

namespace Bjit\Payment\Traits\Stripe;

use Bjit\Payment\Enums\CmnEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

trait Planable // IT IS PLACE RECOMMENDED BY STRIPE
{
    public function formatPlanInput($options)
    {
        $input = [];

        if(isset($options['name'])) {
            $input['nickname'] = $options['name'];
        } 
        if(isset($options['amount'])) {
            $input['amount'] = $options['amount'];
            //$input['unit_amount'] = $options['amount'];
        } 
        if(isset($options['currency'])) {
            $input['currency'] = $options['currency'];
        }
        // if(isset($options['interval'])) {
        //     $input['interval'] = $options['interval'];
        // } 
        if(isset($options['interval'])) {
            $input['recurring'] = ['interval' => $options['interval']];
        }

        if(isset($options['provider_product_id'])) {
            $input['product'] = $options['provider_product_id'];
        } 

        $extraInput = Arr::except($options, [
            'name', 'amount', 'currency', 'interval', 'provider_product_id'
        ]);

        return array_merge($input, $extraInput);
    }

    public function formatPlanResponse($response)
    { 
        return [
            'provider' => CmnEnum::PROVIDER_STRIPE,
            'id' => $response['id'],
            'name' => $response['nickname'] ?? null, 
            'amount' => $response['unit_amount'] ?? null, 
            'currency' => $response['currency'] ?? null, 
            //'interval' => $response['interval'] ?? null,  
            'provider_response' => $response
        ];
    } 

    public function createPlan($options)
    {   
        $plan = $this->stripe->prices->create( $this->formatPlanInput( $options ) );
        $this->storePlanInDatabase($plan);
        return $this->formatPlanResponse($plan);
    }

    public function retrievePlan($planId, $options = [])
    {
        return $this->formatPlanResponse($this->stripe->prices->retrieve( $planId, $options ));
    }

    public function updatePlan($planId, $options = [])
    {
        return $this->stripe->prices->update( $planId, $this->formatPlanInput( $options ) );
    }

    public function deletePlan($planId, $options = [])
    {

    } 

    public function allPlans($options = [])
    { 
        return $this->stripe->prices->all($options);
    }

    private function storePlanInDatabase($response, $options = [], $cardType = null)
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_PLAN_NAME)->insert([
            'provider' => CmnEnum::PROVIDER_STRIPE, 
            'provider_plan_id' => $response['id'],
            'provider_product_id' => $response['product'] ?? null,
            'name' => $response['nickname'] ?? null, 
            'amount' => $response['unit_amount'] ?? null, 
            'currency' => $response['currency'] ?? null, 
            'interval' => $response['recurring']['interval'] ?? null,  
            'success_json' => json_encode($response),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    private function updatePlanInDatabase($planId, $response, $options = [])
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_PLAN_NAME)
            ->where('provider', CmnEnum::PROVIDER_STRIPE)
            ->where('provider_plan_id', $planId)
            ->update([ 
                'provider_product_id' => $response['product'] ?? null,
                'name' => $response['nickname'] ?? null, 
                'amount' => $response['unit_amount'] ?? null, 
                'currency' => $response['currency'] ?? null, 
                'interval' => $response['recurring']['interval'] ?? null,  
                'success_json' => json_encode($response), 
                'updated_at' => now()
            ]);
    }

}