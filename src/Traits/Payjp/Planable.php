<?php 

namespace Bjit\Payment\Traits\Payjp;

use Bjit\Payment\Enums\CmnEnum;
use Bjit\Payment\Helpers\CmnHelper;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Payjp\Plan;

trait Planable 
{
    public function formatPlanListInput($options)
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
        if(isset($response['error'])) {
            return $this->formatErrorResponse($response);
        }
        
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
        $this->updatePlanInDatabase($planId, $plan, $options);
        return $this->formatPlanResponse($plan);
    }

    public function deletePlan($planId, $options = [])
    {
        $plan = Plan::retrieve( $planId );  
        $response = $plan->delete();
        $this->deletePlanFromDatabase($planId, $options);
        return $response;
    } 

    public function allPlans($options = [])
    { 
        return Plan::all($this->formatPlanListInput($options)); 
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
            'success_json' => CmnHelper::jsonEncodePrivate($response),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    private function updatePlanInDatabase($planId, $response, $options = [], $customerType = null)
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_PLAN_NAME)
            ->where('provider', CmnEnum::PROVIDER_PAYJP)
            ->where('provider_plan_id', $planId)
            ->update([ 
                'name' => $response['name'] ?? null, 
                'amount' => $response['amount'] ?? null, 
                'currency' => $response['currency'] ?? null, 
                'interval' => $response['interval'] ?? null, 
                'trial_days' => $response['trial_days'] ?? null, 
                'success_json' => CmnHelper::jsonEncodePrivate($response), 
                'updated_at' => now()
            ]);
    }

    private function deletePlanFromDatabase($planId, $options = [])
    {
        if ( (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            DB::table(CmnEnum::TABLE_CUSTOMER_NAME)
            ->where('provider', CmnEnum::PROVIDER_PAYJP)
            ->where('provider_plan_id', $planId)
            ->update(['deleted_at' => now()]);
        }
    }

}