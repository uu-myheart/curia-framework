<?php

namespace Curia\Framework\Service;

use Curia\Framework\Application;
use Zend\Diactoros\ServerRequestFactory;

class RoutingService
{
    protected $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function register()
    {
//        // Binding request instance as singleton.
//        $this->app->instance(\Psr\Http\Message\ServerRequestInterface::class, ServerRequestFactory::fromGlobals());
//        $this->app->alias('request', \Psr\Http\Message\ServerRequestInterface::class);
//
//        // Bind psr-7 request handler.
//        $this->app->bind(
//            \Psr\Http\Server\RequestHandlerInterface::class,
//            \Curia\Framework\Middleware\RequestHandler::class
//        );
    }

    public function boot()
    {
//        $this->app->call('Curia\Framework\Middleware\Router@process');
    }
}