<?php

namespace Bjit\Payment\Contracts;

interface Gateway
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
