<?php

namespace Curia\Framework;

use Relay\Relay;
use Curia\Container\Container;
use Zend\Diactoros\ServerRequestFactory;

class Application extends Container
{
    protected $bathPath;
    
    protected $services = [];

    public function __construct($bathPath = null)
    {
        $this->bathPath = $bathPath;

        $this->registerBaseBindings();
//        $this->registerBaseService();
    }

    protected function registerBaseBindings()
    {
        static::setInstance($this);
        $this->instance(Container::class, $this);
        $this->instance(get_class($this), $this);

        // Binding request instance as singleton.
        $this->instance(\Psr\Http\Message\ServerRequestInterface::class, ServerRequestFactory::fromGlobals());
        $this->alias('request', \Psr\Http\Message\ServerRequestInterface::class);
        
        // Bind psr-7 response.
        $this->bind(
            \Psr\Http\Message\ResponseInterface::class,
            \Zend\Diactoros\Response::class
        );
        $this->alias('response', \Psr\Http\Message\ResponseInterface::class);
    }

//    protected function registerBaseService()
//    {
//        $this->register(new Service\RoutingService($this));
//        // $this->register(new Service\RquestHandler($this));
//    }
//
//    public function register($service)
//    {
//        if (method_exists($service, 'register')) {
//            $service->register();
//        }
//
//        $this->services[] = $service;
//    }
//
//    public function boot()
//    {
//        foreach ($this->services as $service) {
//            if (method_exists($service, 'boot')) {
//                $service->boot();
//            }
//        }
//    }

    public function bathPath()
    {
        return $this->bathPath;
    }

    public function run()
    {
        $middlewares = $this->getMiddlewares();

        $relay = new Relay($middlewares);
        
        $response = $relay->handle($this->get('request'));

        if ($response->getBody()->getSize()) {
            $this->get(\Zend\Diactoros\Response\SapiEmitter::class)->emit($response);
        }
    }

    protected function getMiddlewares()
    {
        $middlewares[] = $this->get(\Curia\Framework\Middleware\Router::class);

        return $middlewares;
    }
}