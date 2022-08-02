<?php

namespace Bjit\Payment;

use Bjit\Payment\Gateways\PaygentGateway;
use Bjit\Payment\Gateways\PayjpGateway;
use Bjit\Payment\Gateways\PaypalGateway;
use Bjit\Payment\Gateways\PaypayGateway;
use Bjit\Payment\Gateways\StripeGateway;
use Illuminate\Support\Arr;
use Bjit\Payment\Manager;
use Illuminate\Support\Str;
use InvalidArgumentException; 

class PaymentManager extends Manager implements Contracts\Factory
{
    /**
     * Get a gateway instance.
     *
     * @param  string  $gateway
     * @return mixed
     */
    public function with($gateway)
    {
        return $this->gateway($gateway);
    }

    /**
     * Create an instance of the specified gateway.
     *
     * @return \Bjit\Payment\Gateways\AbstractGateway
     */
    protected function createStripeGateway()
    {
        $config = $this->config->get('payments.stripe');
          
        return $this->buildGateway(
            StripeGateway::class, $config
        );
    }  

    /**
     * Create an instance of the specified gateway.
     *
     * @return \Bjit\Payment\Gateways\AbstractGateway
     */
    protected function createPayjpGateway()
    {
        $config = $this->config->get('payments.payjp');
        
        return $this->buildGateway(
            PayjpGateway::class, $config
        );
    }

    /**
     * Create an instance of the specified gateway.
     *
     * @return \Bjit\Payment\Gateways\AbstractGateway
     */
    protected function createPaypalGateway()
    {
        $config = $this->config->get('payments.paypal');
        
        return $this->buildGateway(
            PaypalGateway::class, $config
        );
    }

    /**
     * Create an instance of the specified gateway.
     *
     * @return \Bjit\Payment\Gateways\AbstractGateway
     */
    protected function createPaypayGateway()
    {
        $config = $this->config->get('payments.paypay');
        
        return $this->buildGateway(
            PaypayGateway::class, $config
        );
    }

    /**
     * Create an instance of the specified gateway.
     *
     * @return \Bjit\Payment\Gateways\AbstractGateway
     */
    protected function createPaygentGateway()
    {
        $config = $this->config->get('payments.paygent');
        
        return $this->buildGateway(
            PaygentGateway::class, $config
        );
    }
   

    /**
     * Build an OAuth 2 gateway instance.
     *
     * @param  string  $gateway
     * @param  array  $config
     * @return \Bjit\Payment\Gateways\AbstractGateway
     */
    public function buildGateway($gateway, $config)
    {  
        return new $gateway(
            $this->container->make('request'), $config['key'],
            $config['secret'], Arr::except($config, ['key', 'secret']), 
            Arr::get($config, 'guzzle', [])
        );
    }  

    /**
     * Forget all of the resolved gateway instances.
     *
     * @return $this
     */
    public function forgetGateways()
    {
        $this->gateways = [];

        return $this;
    }

    /**
     * Set the container instance used by the manager.
     *
     * @param  \Illuminate\Contracts\Container\Container  $container
     * @return $this
     */
    public function setContainer($container)
    {
        $this->app = $container;
        $this->container = $container;

        return $this;
    }

    /**
     * Get the default driver name.
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public function getDefaultGateway()
    {
        throw new InvalidArgumentException('No Payment Gateway was specified.');
    }
}
