<?php

namespace Bjit\Payment\Contracts;

interface Factory
{
    /**
     * Get an OAuth Gateway implementation.
     *
     * @param  string  $driver
     * @return \Bjit\Payment\Contracts\Gateway
     */
    public function gateway($gateway = null);
}
