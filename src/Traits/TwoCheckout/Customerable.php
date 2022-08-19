<?php 

namespace Bjit\Payment\Traits\TwoCheckout;

use Bjit\Payment\Enums\CmnEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

trait Customerable 
{
    private function formatCustomerListInput($options)
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
        
        if(isset($options['address'])) {
            $input['address'] = $options['address'];
        }

        if(isset($options['shipping']['address'])) {
            $input['shipping']['address'] = $options['shipping']['address'];
        } 

        if(isset($options['shipping']['name'])) {
            $input['shipping']['name'] = $options['shipping']['name'];
        }

        if(isset($options['shipping']['phone'])) {
            $input['shipping']['phone'] = $options['shipping']['phone'];
        }

        if(isset($options['nonce'])) {
            $input['source'] = $options['nonce'];
        }
        if(isset($options['metadata'])) {
            $input['metadata'] = $options['metadata'];
        } 
        
        $extraInput = Arr::except($options, [
            'name', 'email', 'phone', 'description', 'address', 'nonce', 'metadata' 
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
            'phone' => $response['phone'] ?? null, 
            'description' => $response['description'] ?? null, 
            'address' => $response['address'] ?? null, 
            'shipping' => $response['shipping'] ?? null, 
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
        $response = $this->stripe->customers->update($customerId, $this->formatCustomerInput( $options ) );
        $this->updateCustomerInDatabase($customerId, $response, $options);
        return $response;
    }

    public function deleteCustomer($customerId, $options = [])
    {
        $response = $this->stripe->customers->delete( $customerId, $options );
        $this->deleteCustomerFromDatabase($customerId, $options);
        return $response;
    } 

    public function allCustomers($options = [])
    { 
        return $this->stripe->customers->all($this->formatCustomerListInput($options)); 
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
            'phone' => $response['phone'] ?? null,
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
                'phone' => $response['phone'] ?? null,
                'description' => $response['description'], 
                'success_json' => json_encode($response),
                'updated_at' => now()
            ]);
    }

    private function deleteCustomerFromDatabase($customerId, $options)
    {
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_CUSTOMER_NAME)
            ->where('provider', CmnEnum::PROVIDER_STRIPE)
            ->where('provider_customer_id', $customerId)
            ->update(['deleted_at' => now()]);
    }

}