<?php

namespace Curia\Framework\Service;

class ExceptionService extends Service
{
    /**
     * 启动Whoops
     */
    public function boot()
    {
        $whoops = new \Whoops\Run;
        $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
        $whoops->register();
    }
}