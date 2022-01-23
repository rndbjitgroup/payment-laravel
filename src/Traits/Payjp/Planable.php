<?php 

namespace Bjit\Payment\Traits\Payjp;

use Bjit\Payment\Enums\CmnEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Payjp\Plan;

trait Planable 
{
    public function formatPlanInput($options)
    {
        $input = [];

        if(isset($options['name'])) {
            $input['name'] = $options['name'];
        } 
        if(isset($options['amount'])) {
            $input['amount'] = $options['amount'];
        } 
        if(isset($options['currency'])) {
            $input['currency'] = $options['currency'];
        }
        if(isset($options['interval'])) {
            $input['interval'] = $options['interval'];
        }
        if(isset($options['trial_days'])) {
            $input['trial_days'] = $options['trial_days'];
        } 

        $extraInput = Arr::except($options, [
            'name', 'amount', 'currency', 'interval', 'trial_days'
        ]);

        return array_merge($input, $extraInput);
    }

    public function formatPlanResponse($response)
    { 
        return [
            'provider' => CmnEnum::PROVIDER_PAYJP,
            'id' => $response['id'],
            'name' => $response['name'] ?? null, 
            'amount' => $response['amount'] ?? null, 
            'currency' => $response['currency'] ?? null, 
            'interval' => $response['interval'] ?? null, 
            'trial_days' => $response['trial_days'] ?? null, 
            'provider_response' => $response
        ];
    } 

    public function createPlan($options)
    {   
        $plan = Plan::create( $this->formatPlanInput($options) );
        $this->storePlanInDatabase($plan);
        return $this->formatPlanResponse($plan);
    }

    public function retrievePlan($planId, $options = [])
    {
        $plan = Plan::retrieve( $planId ); 
        return $this->formatPlanResponse($plan);
    }

    public function updatePlan($planId, $options = [])
    {
        $plan = Plan::retrieve( $planId ); 

        $updateInput = $this->formatPlanInput($options);
        foreach($updateInput as $key => $val) {
            $plan->$key = $val;
        } 
        $plan->save(); 

        return $this->formatPlanResponse($plan);
    }

    public function deletePlan($planId, $options = [])
    {
        $plan = Plan::retrieve( $planId );  
        return $plan->delete();
    } 

    public function allPlans($planId, $options = [])
    { 
        $options = [
            'limit' => $options['limit'],
            'offset' => $options['offset']
        ];
        return Plan::all($options); 
    }

    private function storePlanInDatabase($response, $options = [], $customerType = null)
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_PLAN_NAME)->insert([
            'provider' => CmnEnum::PROVIDER_PAYJP, 
            'provider_plan_id' => $response['id'],
            'name' => $response['name'] ?? null, 
            'amount' => $response['amount'] ?? null, 
            'currency' => $response['currency'] ?? null, 
            'interval' => $response['interval'] ?? null, 
            'trial_days' => $response['trial_days'] ?? null, 
            'success_json' => json_encode($response),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}