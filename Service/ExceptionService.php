<?php

namespace Curia\Framework\Service;

class ExceptionService extends Service
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