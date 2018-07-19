<?php

namespace Curia\Framework\Service;

class RouteService extends Service
{
    public function register()
    {
        $this->app->singleton('router', 'Curia\Framework\Routing\Router');
    }
}