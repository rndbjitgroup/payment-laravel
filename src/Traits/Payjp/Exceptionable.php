<?php 

namespace Bjit\Payment\Traits\Payjp;

use Bjit\Payment\Enums\CmnEnum;

trait Exceptionable 
{
    public function formatErrorResponse($response)
    {
        return [
            'error' => [
                'message' => $response['error']['message'] ?? CmnEnum::EMPTY_NULL,
                'status' => $response['error']['status'] ?? CmnEnum::EMPTY_NULL,
                'type' => $response['error']['type'] ?? CmnEnum::EMPTY_NULL
            ]
        ];
    }
}