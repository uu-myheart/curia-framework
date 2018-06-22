<?php

namespace Curia\Framework;

use Curia\Container\Container;

class Application extends Container
{
    protected $bathPath;

    public function __construct($bathPath = null)
    {
        $this->bathPath = $bathPath;

        static::setInstance($this);
    }

    public function register($provider)
    {
        if (method_exists($provider, 'register')) {
            $provider->register();
        }

        //TODO $provider->boot();
    }

    public function bathPath()
    {
        return $this->bathPath;
    }
}