<?php 

namespace Bjit\Payment\Traits\Payjp;

use Bjit\Payment\Enums\CmnEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Payjp\Customer;

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
        if(isset($options['description'])) {
            $input['description'] = $options['description'];
        }

        $extraInput = Arr::except($options, [
            'name', 'email', 'description'
        ]);

        return array_merge($input, $extraInput);
    }

    public function formatCustomerResponse($response)
    { 
        return [
            'provider' => CmnEnum::PROVIDER_PAYJP,
            'id' => $response['id'],
            'name' => $response['name'] ?? null, 
            'email' => $response['email'] ?? null, 
            'description' => $response['description'] ?? null, 
            'provider_response' => $response
        ];
    } 

    public function createCustomer($options)
    {   
        $customer = Customer::create( $this->formatCustomerInput($options) );
        $this->storeCustomer($customer);
        return $this->formatCustomerResponse($customer);
    }

    public function retrieveCustomer($customerId, $options = [])
    {
        return $this->formatCustomerResponse(Customer::retrieve( $customerId, $options ));
    }

    public function updateCustomer($customerId, $options = [])
    {
        $customer = Customer::retrieve( $customerId );
        if(isset($options['email'])) {
            $customer->email = $options['email'];
        }
        if(isset($options['description'])) {
            $customer->description = $options['description'];
        }
        if(isset($options['metadata'])) {
            $customer->metadata = $options['metadata'];
        }
        $customer->save(); 
        return $this->formatCustomerResponse($customer);
    }

    public function deleteCustomer($customerId, $options = [])
    {
        $customer = Customer::retrieve( $customerId, $options ); 
        return $customer->delete();;
    } 

    public function allCustomers($options = [])
    { 
        return Customer::all($options); 
    }

    private function storeCustomer($response, $options = [], $customerType = null)
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_CUSTOMER_NAME)->insert([
            'provider' => CmnEnum::PROVIDER_PAYJP,
            'provider_customer_id' => $response['id'],
            'email' => $response['email'],
            'description' => $response['description'], 
            'success_json' => json_encode($response),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}