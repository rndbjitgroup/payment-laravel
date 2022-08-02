<?php

namespace Bjit\Payment\Gateways\Interfaces;

interface GatewayInterface
{
    /**
     * Format Payment data for the gateway.
     * 
     */
    public function formatPaymentInput($options);

    public function formatPaymentResponse($response);

    /**
     * Format Checkout data for the gateway.
     * 
     */
    //public function formatCheckoutData($options);
}
