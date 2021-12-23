<?php

namespace Bjit\Payment;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use InvalidArgumentException;

abstract class Manager
{
    /**
     * The container instance.
     *
     * @var \Illuminate\Contracts\Container\Container
     */
    protected $container;

    /**
     * The configuration repository instance.
     *
     * @var \Illuminate\Contracts\Config\Repository
     */
    protected $config;

    /**
     * The registered custom gateway creators.
     *
     * @var array
     */
    protected $customCreators = [];

    /**
     * The array of created "gateways".
     *
     * @var array
     */
    protected $gateways = [];

    /**
     * Create a new manager instance.
     *
     * @param  \Illuminate\Contracts\Container\Container  $container
     * @return void
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->config = $container->make('config');
    }

    /**
     * Get the default gateway name.
     *
     * @return string
     */
    abstract public function getDefaultGateway();

    /**
     * Get a gateway instance.
     *
     * @param  string|null  $gateway
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function gateway($gateway = null)
    {
        $gateway = $gateway ?: $this->getDefaultGateway();

        if (is_null($gateway)) {
            throw new InvalidArgumentException(sprintf(
                'Unable to resolve NULL gateway for [%s].', static::class
            ));
        }

        // If the given gateway has not been created before, we will create the instances
        // here and cache it so we can return it next time very quickly. If there is
        // already a gateway created by this name, we'll just return that instance.
        if (! isset($this->gateways[$gateway])) {
            $this->gateways[$gateway] = $this->creategateway($gateway);
        }

        return $this->gateways[$gateway];
    }

    /**
     * Create a new gateway instance.
     *
     * @param  string  $gateway
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    protected function createGateway($gateway)
    {
        // First, we will determine if a custom gateway creator exists for the given gateway and
        // if it does not we will check for a creator method for the gateway. Custom creator
        // callbacks allow developers to build their own "gateways" easily using Closures.
        if (isset($this->customCreators[$gateway])) {
            return $this->callCustomCreator($gateway);
        } else {
            $method = 'create'.Str::studly($gateway).'Gateway';

            if (method_exists($this, $method)) {
                return $this->$method();
            }
        }

        throw new InvalidArgumentException("Gateway [$gateway] not supported.");
    }

    /**
     * Call a custom gateway creator.
     *
     * @param  string  $gateway
     * @return mixed
     */
    protected function callCustomCreator($gateway)
    {
        return $this->customCreators[$gateway]($this->container);
    }

    /**
     * Register a custom gateway creator Closure.
     *
     * @param  string  $gateway
     * @param  \Closure  $callback
     * @return $this
     */
    public function extend($gateway, Closure $callback)
    {
        $this->customCreators[$gateway] = $callback;

        return $this;
    }

    /**
     * Get all of the created "gateways".
     *
     * @return array
     */
    public function getGateways()
    {
        return $this->gateways;
    }

    /**
     * Get the container instance used by the manager.
     *
     * @return \Illuminate\Contracts\Container\Container
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Set the container instance used by the manager.
     *
     * @param  \Illuminate\Contracts\Container\Container  $container
     * @return $this
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;

        return $this;
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
     * Dynamically call the default gateway instance.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->gateway()->$method(...$parameters);
    }
}
