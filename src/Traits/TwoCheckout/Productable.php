<?php 

namespace Bjit\Payment\Traits\TwoCheckout;

use Bjit\Payment\Enums\CmnEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

trait Productable // IT IS PLACE RECOMMENDED BY TwoCheckout
{
    private function formatProductListInput($options)
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

    private function formatProductInput($options)
    {
        $input = [];

        if(isset($options['name'])) {
            $input['name'] = $options['name'];
        } 
        if(isset($options['description'])) {
            $input['description'] = $options['description'];
        } 
        if(isset($options['active'])) {
            $input['active'] = $options['active'];
        } 

        $extraInput = Arr::except($options, [
            'name', 'description', 'active'
        ]);

        return array_merge($input, $extraInput);
    }

    private function formatProductResponse($response)
    { 
        return [
            'provider' => CmnEnum::PROVIDER_STRIPE,
            'id' => $response['id'],
            'name' => $response['nickname'] ?? null, 
            'description' => $response['description'] ?? null, 
            'active' => $response['active'] ?? false,   
            'provider_response' => $response
        ];
    } 

    public function createProduct($options)
    {   
        $response = $this->stripe->products->create( $this->formatProductInput( $options ) );
        $this->storeProductInDatabase($response);
        return $this->formatProductResponse($response);
    }

    public function retrieveProduct($productId, $options = [])
    {
        return $this->formatProductResponse($this->stripe->products->retrieve( $productId, $options ));
    }

    public function updateProduct($productId, $options = [])
    {
        $response = $this->stripe->products->update( $productId, $this->formatProductInput( $options ) );
        $this->updateProductInDatabase($productId, $response, $options);
        return $this->formatProductResponse($response);
    }

    public function deleteProduct($productId, $options = [])
    {
        $response = $this->stripe->products->delete( $productId, $options );
        $this->deleteProductFromDatabase($productId, $options);
        return $this->formatProductResponse($response);
    } 

    public function allProducts($options = [])
    { 
        return $this->stripe->products->all($this->formatProductListInput($options));
    }

    private function storeProductInDatabase($response, $options = [], $cardType = null)
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_PRODUCT_NAME)->insert([
            'provider' => CmnEnum::PROVIDER_STRIPE, 
            'provider_Product_id' => $response['id'], 
            'name' => $response['name'] ?? null, 
            'description' => $response['description'] ?? null, 
            'metadata' => isset($response['metadata']) ? json_encode($response['metadata']) : null,  
            'active' => $response['active'] ?? null,   
            'success_json' => json_encode($response),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    private function updateProductInDatabase($productId, $response, $options = [])
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_PRODUCT_NAME)
            ->where('provider', CmnEnum::PROVIDER_STRIPE)
            ->where('provider_Product_id', $productId)
            ->update([ 
                'name' => $response['name'] ?? null, 
                'description' => $response['description'] ?? null, 
                'metadata' => isset($response['metadata']) ? json_encode($response['metadata']) : null,  
                'active' => $response['active'] ?? null,   
                'success_json' => json_encode($response), 
                'updated_at' => now()
            ]);
    }

    private function deleteProductFromDatabase($productId, $options)
    {
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_PRODUCT_NAME)
            ->where('provider', CmnEnum::PROVIDER_STRIPE)
            ->where('provider_product_id', $productId)
            ->update(['deleted_at' => now()]);
    }

}