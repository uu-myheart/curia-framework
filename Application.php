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
     *
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
     * 基础绑定
     *
     * @return void
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
     * 注册服务
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
     * 初始化路由
     *
     * @return void
     */
    protected function bootRouter()
    {
        $this->router = new Router($this);
    }

    /**
     * 启动应用服务
     *
     * @return $this
     */
    public function bootServices()
    {
        foreach ($this->services as $service) {
            if (method_exists($service, 'boot')) {
                $service->boot();
            }
        }

        return $this;
    }

    /**
     * 增加全局中间件
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
     * 定义路由中间件
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
     * 获取项目目录
     *
     * @return string
     */
    public function basePath()
    {
        return $this->basePath;
    }

    /**
     * Run the applicaton.
     */
    public function run()
    {
        $this->bootServices();

        $response = (new Baton($this))
                        ->send($this['request'])
                        ->through($this->middlewares)
                        ->then($this->dispatchToRouter());

        $this->send($response);
    }

    /**
     * 请求通过全局中间件后进入路由
     *
     * @return Closure
     */
    protected function dispatchToRouter()
    {
        return function ($request) {
            $this->instance('request', $request);

            return $this->router->dispatch($request);
        };
    }

    /**
     * 获取路由中间件
     *
     * @return array
     */
    public function getRouteMiddlewares()
    {
        return $this->routeMiddlewares;
    }

    /**
     * 向客户端发送响应
     *
     * @param $response
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function send($data)
    {
        if ($data instanceof \CUria\Framework\Http\Response) {
            return $data->send();
        }

        $response = $this['response'];

        if (is_array($data)) {
            $response->getBody()->write(json_encode($data));
            return $response->withHeader('accept', 'application/json')
                        ->send();
        }

        $response->getBody()->write($data);
        $response->send();
    }
}