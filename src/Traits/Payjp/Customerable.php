<?php 

namespace Bjit\Payment\Traits\Payjp;

use Bjit\Payment\Enums\CmnEnum;
use Bjit\Payment\Helpers\CmnHelper;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Payjp\Customer;

trait Customerable 
{
    public function formatCustomerListInput($options)
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

        if(isset($options['email'])) {
            $input['email'] = $options['email'];
        } 
        if(isset($options['description'])) {
            $input['description'] = $options['description'];
        }
        if(isset($options['nonce'])) {
            $input['card'] = $options['nonce'];
        }
        if(isset($options['default_nonce'])) {
            $input['default_card'] = $options['default_nonce'];
        }
        if(isset($options['metadata'])) {
            $input['metadata'] = $options['metadata'];
        }

        $extraInput = Arr::except($options, [
            'email', 'description', 'nonce', 'default_nonce', 'metadata'
        ]);

        return array_merge($input, $extraInput);
    }

    public function formatCustomerResponse($response)
    { 
        if(isset($response['error'])) {
            return $this->formatErrorResponse($response);
        }
        
        return [
            'provider' => CmnEnum::PROVIDER_PAYJP,
            'id' => $response['id'],
            'name' => $response['name'] ?? null, 
            'email' => $response['email'] ?? null, 
            'description' => $response['description'] ?? null, 
            'cards' => $response['cards'] ?? null, 
            'metadata' => $response['metadata'] ?? null, 
            'provider_response' => $response
        ];
    } 

    public function createCustomer($options)
    {   
        $customer = Customer::create( $this->formatCustomerInput($options) );
        $this->storeCustomerInDatabase($customer);
        return $this->formatCustomerResponse($customer);
    }

    public function retrieveCustomer($customerId, $options = [])
    {
        return $this->formatCustomerResponse(Customer::retrieve( $customerId, $options ));
    }

    public function updateCustomer($customerId, $options = [])
    {
        $customer = Customer::retrieve( $customerId ); 

        $updateInput = $this->formatCustomerInput($options);
        foreach ($updateInput as $key => $val) {
            $customer->$key = $val;
        }
        $customer->save(); 
        $this->updateCustomerInDatabase($customerId, $customer, $options);
        return $this->formatCustomerResponse($customer);
    }

    public function deleteCustomer($customerId, $options = [])
    {
        $customer = Customer::retrieve( $customerId, $options ); 
        $response = $customer->delete();
        if(isset($response['deleted']) && $response['deleted']) {
            $this->deleteCustomerFromDatabase($customerId, $options);
        }
        return $response;
    } 

    public function allCustomers($options = [])
    { 
        return Customer::all($this->formatCustomerListInput($options)); 
    }

    private function storeCustomerInDatabase($response, $options = [], $customerType = null)
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_CUSTOMER_NAME)->insert([
            'provider' => CmnEnum::PROVIDER_PAYJP,
            'provider_customer_id' => $response['id'],
            'provider_default_card_id' => $response['default_card'] ?? CmnEnum::EMPTY_NULL,
            'email' => $response['email'],
            'description' => $response['description'],  
            'success_json' => CmnHelper::jsonEncodePrivate($response),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    private function updateCustomerInDatabase($customerId, $response, $options = [], $customerType = null)
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_CUSTOMER_NAME)
            ->where('provider', CmnEnum::PROVIDER_PAYJP)
            ->where('provider_customer_id', $customerId) 
            ->update([ 
                'email' => $response['email'],
                'description' => $response['description'], 
                'provider_default_card_id' => $response['default_card'] ?? CmnEnum::EMPTY_NULL,
                'success_json' => CmnHelper::jsonEncodePrivate($response), 
                'updated_at' => now()
            ]);
    }

    private function deleteCustomerFromDatabase($customerId, $options)
    {
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }
        
        return DB::table(CmnEnum::TABLE_CUSTOMER_NAME)
            ->where('provider', CmnEnum::PROVIDER_PAYJP)
            ->where('provider_customer_id', $customerId)
            ->update(['deleted_at' => now()]);
    }

}