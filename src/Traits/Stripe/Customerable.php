<?php 

namespace Bjit\Payment\Traits\Stripe;

use Bjit\Payment\Enums\CmnEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

trait Customerable 
{
    public function formatCustomerInput($options)
    {
        $input = [];

        if(isset($options['name'])) {
            $input['name'] = $options['name'];
        }
        if(isset($options['email'])) {
            $input['email'] = $options['email'];
        }
        if(isset($options['phone'])) {
            $input['phone'] = $options['phone'];
        }
        if(isset($options['description'])) {
            $input['description'] = $options['description'];
        }
        if(isset($options['address']['city'])) {
            $input['address']['city'] = $options['address']['city'];
        }
        if(isset($options['address']['country'])) {
            $input['address']['country'] = $options['address']['country'];
        }
        if(isset($options['address']['line1'])) {
            $input['address']['line1'] = $options['address']['line1'];
        }
        if(isset($options['address']['line2'])) {
            $input['address']['line2'] = $options['address']['line2'];
        }
        if(isset($options['address']['postal_code'])) {
            $input['address']['postal_code'] = $options['address']['postal_code'];
        }
        if(isset($options['address']['state'])) {
            $input['address']['state'] = $options['address']['state'];
        } 

        $extraInput = Arr::except($options, [
            'name', 'email', 'phone', 'description', 'address' 
        ]);

        return array_merge($input, $extraInput);
    }

    public function formatCustomerResponse($response)
    { 
        return [
            'provider' => CmnEnum::PROVIDER_STRIPE,
            'id' => $response['id'],
            'name' => $response['name'] ?? null, 
            'email' => $response['email'] ?? null, 
            'description' => $response['description'] ?? null, 
            'provider_response' => $response
        ];
    } 

    public function createCustomer($options)
    {   
        $customer = $this->stripe->customers->create( $this->formatCustomerInput( $options ) );
        $this->storeCustomerInDatabase($customer);
        return $this->formatCustomerResponse($customer);
    }

    public function retrieveCustomer($customerId, $options = [])
    {
        return $this->formatCustomerResponse($this->stripe->customers->retrieve( $customerId, $options ));
    }

    public function updateCustomer($customerId, $options = [])
    {
        return $this->stripe->customers->update( $customerId, $this->formatCustomerInput( $options ) );
    }

    public function deleteCustomer($customerId, $options = [])
    {
        $response = $this->stripe->customers->delete( $customerId, $options );
        $this->deleteCustomerFromDatabase($customerId, $options);
        return $response;
    } 

    public function allCustomers($options = [])
    { 
        return $this->stripe->customers->all($options); 
    }

    private function storeCustomerInDatabase($response, $options = [], $customerType = null)
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_CUSTOMER_NAME)->insert([
            'provider' => CmnEnum::PROVIDER_STRIPE,
            'provider_customer_id' => $response['id'],
            'email' => $response['email'],
            'description' => $response['description'], 
            'success_json' => json_encode($response),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    private function updateCustomerInDatabase($customerId, $response, $options = [])
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_CUSTOMER_NAME)
            ->where('provider', CmnEnum::PROVIDER_STRIPE)
            ->where('provider_customer_id', $customerId)
            ->update([
                'email' => $response['email'],
                'description' => $response['description'], 
                'success_json' => json_encode($response),
                'updated_at' => now()
            ]);
    }

    private function deleteCustomerFromDatabase($customerId, $options)
    {
        if ( (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            DB::table(CmnEnum::TABLE_CUSTOMER_NAME)
            ->where('provider', CmnEnum::PROVIDER_STRIPE)
            ->where('provider_customer_id', $customerId)
            ->update(['deleted_at' => now()]);
        }
    }

}