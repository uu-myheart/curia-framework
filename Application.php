<?php

namespace Curia\Framework;

use Curia\Collect\Str;
use Curia\Framework\Baton;
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
     * 配置文件管理实例
     * 
     * @var \Curia\Framework\Config 
     */
    protected $config;

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
     * @param null
     */
    public function __construct($basePath = null)
    {
        // 设置应用的各个路径
        $this->setPath($basePath);

        $this->loadConfiguration();

        $this->registerBaseBindings();

        $this->registerBaseService();

        $this->bootRouter();
    }

    /**
     * 设置应用的各个路径
     *
     * @param $bathPath
     * @return void
     */
    protected function setPath($basePath)
    {
        $this->basePath = $basePath;

        $this->configPath = $basePath . '/config';
    }

    /**
     * 加载配置文件
     */
    protected function loadConfiguration()
    {
        // 加载应用目录中的.env到$_ENV
        (new \Dotenv\Dotenv($this->basePath()))->load();
        
        // 实例化创建配置文件管理类
        $this->config = new Config(
            $this->getConfiguration()
        );
    }

    protected function getConfiguration()
    {
        return collect(
            scandir($this->configPath)
        )->flatMap(function ($item) {
            $file = $this->configPath . DIRECTORY_SEPARATOR . $item;

            if(is_file($file) && Str::endsWith($file, '.php')) {
                return [Str::before($item, '.php') => require($file) ];
            }
        })->toArray();
    }

    /**
     * 获取config实例或者具体配置内容
     * 
     * @param null $key
     * @return Config
     */
    public function config($key = null)
    {
        return $key ? $this->config->get($key) : $this->config;
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
        $this->register(new Service\DatabaseService($this));
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

            return $response->withHeader('accept', 'application/json')->send();
        }

        $response->getBody()->write($data);
        $response->send();
    }
}