<?php

namespace Curia\Framework\Service;

use Curia\Framework\Application;

class Service
{
    protected $app;
    
    public function __construct(Application $app)
    {
        $this->app = $app;
    }
}