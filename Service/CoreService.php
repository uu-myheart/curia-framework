<?php

namespace Curia\Framework\Service;

class CoreService extends Service
{
    public function register()
    {
        // Binding request instance as singleton.
        $this->app->instance(\Psr\Http\Message\ServerRequestInterface::class, \Zend\Diactoros\ServerRequestFactory::fromGlobals());
        $this->app->alias('request', \Psr\Http\Message\ServerRequestInterface::class);

        // Bind psr-7 response.
        $this->app->bind(
            \Psr\Http\Message\ResponseInterface::class,
            \Zend\Diactoros\Response::class
        );
        $this->app->alias('response', \Psr\Http\Message\ResponseInterface::class);
    }
}