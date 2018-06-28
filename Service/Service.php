<?php

namespace Curia\Framework\Service;

use Curia\Framework\Application;

class Service
{
    protected $app;

    /**
     * Service constructor.
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }
}