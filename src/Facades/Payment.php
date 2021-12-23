<?php

namespace Bjit\Payment\Facades;

use Illuminate\Support\Facades\Facade;
use Bjit\Payment\Contracts\Factory;

/**
 * @method static Bjit\Payment\Contracts\Gateway gateway(string $gateway = null)
 *
 * @see Bjit\Payment\SocialiteManager
 */
class Payment extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return Factory::class;
    }
}
