<?php

namespace Bjit\Payment\Gateways;

interface GatewayInterface
{
    /**
     * Format Payment data for the gateway.
     * 
     */
    public function formatPaymentData($options);

    /**
     * Format Checkout data for the gateway.
     * 
     */
    public function formatCheckoutData($options);
}
