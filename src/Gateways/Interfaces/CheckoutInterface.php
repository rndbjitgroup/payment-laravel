<?php

namespace Bjit\Payment\Gateways\Interfaces;

interface CheckoutInterface
{
    /**
     * Format Payment data for the gateway.
     * 
     */
    public function formatCheckoutInput($options);

    public function formatCheckoutResponse($response);

    public function createCheckout($options);

    public function retrieveCheckout($csId, $options = []);
    
    public function allCheckouts($options = []);

 
}
