<?php

namespace Curia\Framework;

use Curia\Baton\Baton;
use Curia\Container\Container;
use Curia\Framework\Routing\Router;
use Psr\Http\Message\ResponseInterface;

class Application extends Container
{
    /**
     * Application base path.
     *
     * @var string
     */
    protected $basePath;

    /**
     * Application services.
     *
     * @var array
     */
    protected $services = [];

    /**
     * Application services booted or not.
     *
     * @var boolean
     */
    protected $booted;

    /**
     * The Router instance.
     *
     * @var \Curia\Framework\Routing\Router
     */
    public $router;

    /**
     * All of the global middleware for the application.
     *
     * @var array
     */
    protected $middlewares = [];

    /**
     * All of the route specific middleware short-hands.
     *
     * @var array
     */
    protected $routeMiddlewares = [];

    /**
     * Application constructor.
     * @param null $basePath
     */
    public function __construct($basePath = null)
    {
        $this->basePath = $basePath;

        $this->registerBaseBindings();
        $this->registerBaseService();
        $this->bootRouter();
    }

    /**
     * Register Application Base Bindings.
     */
    protected function registerBaseBindings()
    {
        static::setInstance($this);
        $this->instance(Container::class, $this);
        $this->instance(get_class($this), $this);
    }

    /**
     * Register application base services.
     */
    protected function registerBaseService()
    {
        $this->register(new Service\ExceptionService($this));
        $this->register(new Service\HttpService($this));
    }

    /**
     * Register a given sevice.
     *
     * @param $service
     * @return void
     */
    public function register($service)
    {
        if (method_exists($service, 'register')) {
            $service->register();
        }

        $this->services[] = $service;
    }

    /**
     * Bootstrap the router instance.
     *
     * @return void
     */
    protected function bootRouter()
    {
        $this->router = new Router($this);
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
     * Add new middleware to the application.
     *
     * @param  Closure|array  $middleware
     * @return $this
     */
    public function middleware($middlewares)
    {
        $this->middlewares = array_unique(array_merge($this->middlewares, (array) $middlewares));

        return $this;
    }

    /**
     * Define the route middleware for the application.
     *
     * @param  array  $middleware
     * @return $this
     */
    public function routeMiddleware(array $middlewares)
    {
        $this->routeMiddlewares = array_merge($this->routeMiddlewares, $middlewares);

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
            $this->booted = true;
        }

        $response = (new Baton($this))
                        ->send($this['request'])
                        ->through($this->middlewares)
                        ->then(function ($request) {
                            //TODO 是否可以直接写成$this->router->handle, 而不用闭包包裹起来
                            $this->instance('request', $request);
                            $this->router->handle($request);
                        });
        dd(222, $response);

        $this->send($response);
    }

    public function getRouteMiddlewares()
    {
        return $this->routeMiddlewares;
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