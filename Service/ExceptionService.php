<?php

namespace Curia\Framework\Service;

class ExceptionService
{
    /**
     * Boot current service.
     */
    public function boot()
    {
        $whoops = new \Whoops\Run;
        $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
        $whoops->register();
    }
}