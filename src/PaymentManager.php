<?php

namespace Bjit\Payment;

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
     * @return \Laravel\Socialite\Two\AbstractProvider
     */
    protected function createStripeGateway()
    {
        $config = $this->config->get('payments.stripe');

        return $this->buildGateway(
            StripeGateway::class, $config
        );
    }  
   

    /**
     * Build an OAuth 2 gateway instance.
     *
     * @param  string  $gateway
     * @param  array  $config
     * @return \Laravel\Socialite\Two\AbstractGateway
     */
    public function buildGateway($gateway, $config)
    {
        return new $gateway(
            $this->container->make('request'), $config['key'],
            $config['secret'], Arr::get($config, 'guzzle', [])
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
