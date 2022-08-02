<?php

namespace Bjit\Payment\Gateways\Interfaces;

interface CustomerInterface
{
    /**
     * Format Payment data for the gateway.
     * 
     */
    public function formatCustomerInput($options);

    public function formatCustomerResponse($response);

    public function createCustomer($options);

    public function retrieveCustomer($cusId, $options = []);

    public function updateCustomer($cusId,  $options = []);

    public function deleteCustomer($cusId,  $options = []);

    public function allCustomers($options = []);
 
}
