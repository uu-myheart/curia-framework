<?php

namespace Curia\Framework;

use Relay\Relay;
use Curia\Container\Container;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\ServerRequestFactory;

class Application extends Container
{
    /**
     * Application base path.
     * @var string
     */
    protected $basePath;

    /**
     * Application services.
     * @var array
     */
    protected $services = [];

    /**
     * Application services booted or not.
     * @var boolean
     */
    protected $booted;

    /**
     * Application constructor.
     * @param null $basePath
     */
    public function __construct($basePath = null)
    {
        $this->basePath = $basePath;

        $this->registerBaseBindings();
        $this->registerBaseService();
    }

    /**
     * Register Application Base Bindings.
     */
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

    /**
     * Register application base services.
     */
    protected function registerBaseService()
    {
        $this->register(new Service\ExceptionService($this));
    }

    /**
     * Register a given sevice.
     * @param $service
     */
    public function register($service)
    {
        if (method_exists($service, 'register')) {
            $service->register();
        }

        $this->services[] = $service;
    }

    /**
     * Boot application services.
     * @return $this
     */
    public function boot()
    {
        foreach ($this->services as $service) {
            if (method_exists($service, 'boot')) {
                $service->boot();
            }
        }

        return $this;
    }

    /**
     * Get application base path
     * @return string
     */
    public function basePath()
    {
        return $this->basePath;
    }

    /**
     * Run the applicaton.
     * @throws \ReflectionException
     */
    public function run()
    {
        if (! $this->booted) {
            $this->boot();
        }

        // Handler is also a dispatcher.
        $handler = $this->getHandler();

        // Handle request and get response.
        $response = $handler->handle($this->get('request'));

        $this->send($response);
    }

    /**
     * Get the request handler.
     * @return Relay
     * @throws \ReflectionException
     */
    protected function getHandler()
    {
        $middlewares = $this->getMiddlewares();

        return new Relay($middlewares);
    }

    /**
     * Get application middlewares.
     * @return array
     * @throws \ReflectionException
     */
    protected function getMiddlewares()
    {
        $middlewares[] = $this->get(\Curia\Framework\Middleware\Router::class);

        return $middlewares;
    }

    /**
     * Emit the response.
     * @param ResponseInterface $response
     * @throws \ReflectionException
     */
    protected function send(ResponseInterface $response)
    {
        if ($response->getBody()->getSize()) {
            $this->get(\Zend\Diactoros\Response\SapiEmitter::class)->emit($response);
        }
    }
}