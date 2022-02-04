<?php 

namespace Bjit\Payment\Traits\Stripe;

use Bjit\Payment\Enums\CmnEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

trait Planable // IT IS PLACE RECOMMENDED BY STRIPE
{
    private function formatPlanListInput($options)
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

    private function formatPlanInput($options)
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

    private function formatPlanResponse($response)
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
        $response = $this->stripe->prices->create( $this->formatPlanInput( $options ) );
        $this->storePlanInDatabase($response);
        return $this->formatPlanResponse($response);
    }

    public function retrievePlan($planId, $options = [])
    {
        return $this->formatPlanResponse($this->stripe->prices->retrieve( $planId, $options ));
    }

    public function updatePlan($planId, $options = [])
    {
        $response = $this->stripe->prices->update( $planId, $this->formatPlanInput( $options ) );
        $this->updatePlanInDatabase($planId, $response, $options);
        return $this->formatPlanResponse($response);
    }

    public function allPlans($options = [])
    { 
        return $this->stripe->prices->all($this->formatPlanListInput($options));
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