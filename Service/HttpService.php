<?php

namespace Curia\Framework\Service;

class HttpService extends Service
{
    public function register()
    {
        // Binding request instance as singleton.
        $this->app->instance(
            \Psr\Http\Message\ServerRequestInterface::class,
            \Curia\Framework\Http\RequestFactory::fromGlobals()
        );
        $this->app->alias('request', \Psr\Http\Message\ServerRequestInterface::class);

        // Bind psr-7 response.
        $this->app->bind(
            \Psr\Http\Message\ResponseInterface::class,
            \Zend\Diactoros\Response::class
        );
        $this->app->alias('response', \Psr\Http\Message\ResponseInterface::class);
    }
}