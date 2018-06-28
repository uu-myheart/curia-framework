<?php

namespace Curia\Framework\Routing;

use Closure;
use Exception;
use Curia\Baton\Baton;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Curia\Framework\Application;
use Psr\Http\Message\ResponseInterface;
use function FastRoute\simpleDispatcher;

class Router
{
    /**
     * The application instance.
     *
     * @var \Curia\Framework\Application
     */
    protected $app;

    /**
     * All of the routes waiting to be registered.
     *
     * @var array
     */
    protected $routes = [];

    /**
     * The route group attribute stack.
     *
     * @var array
     */
    protected $groupStack = [];

    /**
     * The current route being dispatched.
     *
     * @var array
     */
    protected $currentRoute;

    /**
     * Router constructor.
     *
     * @param \Curia\Framework\Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Register a set of routes with a set of shared attributes.
     *
     * @param  array  $attributes
     * @param  \Closure  $callback
     * @return void
     */
    public function group(array $attributes, Closure $callback)
    {
        if (isset($attributes['middleware']) && is_string($attributes['middleware'])) {
            $attributes['middleware'] = explode('|', $attributes['middleware']);
        }

        $this->groupStack[] = $attributes;

        call_user_func($callback, $this);

        array_pop($this->groupStack);
    }

    /**
     * 声明路由
     *
     * @param $method
     * @param string $uri
     * @param $handler
     *
     * @return void
     */
    public function addRoute($method,string $uri, $handler)
    {
        [$uri, $namespace, $middleware] = $this->parseAttribute($uri);

        if (is_string($handler)) {
            $handler = $namespace . $handler;
        }

        foreach ((array) $method as $verb) {
            $this->routes[$verb.$uri] = [
                'method' => $method,
                'uri' => $uri,
                'action' => [
                    'handler' => $handler,
                    'middleware' => $middleware,
                ],
            ];
        }
    }

    /**
     * 格式化路由信息
     *
     * 获取路由定义中完整的uri,namespace,middlewares
     * @param string $uri
     * @return array
     */
    protected function parseAttribute(string $uri)
    {
        $prefix = $namespace = '';
        $middleware = [];

        if (!empty($this->groupStack)) {
            foreach ($this->groupStack as $key => $stack) {
                if (isset($stack['namespace'])) {
                    $namespace .= trim($stack['namespace']) . '\\';
                }

                if (isset($stack['prefix'])) {
                    $prefix .=  '/' . trim($stack['prefix']);
                }

                if (isset($stack['middleware'])) {
                    $middleware = array_merge($middleware, $stack['middleware']);
                }
            }
        }

        $uri = $prefix . '/' . trim($uri, '/');

        return [$uri, $namespace, $middleware];
    }

    /**
     * Register a route with the application.
     *
     * @param  string  $uri
     * @param  mixed  $action
     * @return $this
     */
    public function get($uri, $action)
    {
        $this->addRoute('GET', $uri, $action);

        return $this;
    }

    /**
     * Register a route with the application.
     *
     * @param  string  $uri
     * @param  mixed  $action
     * @return $this
     */
    public function post($uri, $action)
    {
        $this->addRoute('POST', $uri, $action);

        return $this;
    }

    /**
     * Register a route with the application.
     *
     * @param  string  $uri
     * @param  mixed  $action
     * @return $this
     */
    public function put($uri, $action)
    {
        $this->addRoute('PUT', $uri, $action);

        return $this;
    }

    /**
     * Register a route with the application.
     *
     * @param  string  $uri
     * @param  mixed  $action
     * @return $this
     */
    public function patch($uri, $action)
    {
        $this->addRoute('PATCH', $uri, $action);

        return $this;
    }

    /**
     * Register a route with the application.
     *
     * @param  string  $uri
     * @param  mixed  $action
     * @return $this
     */
    public function delete($uri, $action)
    {
        $this->addRoute('DELETE', $uri, $action);

        return $this;
    }

    /**
     * Register a route with the application.
     *
     * @param  string  $uri
     * @param  mixed  $action
     * @return $this
     */
    public function options($uri, $action)
    {
        $this->addRoute('OPTIONS', $uri, $action);

        return $this;
    }

    /**
     * Register a route with the application.
     *
     * @param  string  $uri
     * @param  mixed  $action
     * @return $this
     */
    public function head($uri, $action)
    {
        $this->addRoute('HEAD', $uri, $action);

        return $this;
    }

    /**
     * Get the raw routes for the application.
     *
     * @return array
     */
    public function getRoutes()
    {
        return $this->routes;
    }

    /**
     * 分发请求
     *
     * @param $request
     * @return mixed
     */
    public function dispatch($request)
    {
        [$method, $uri] = $this->parseIncomingRequest($request);

        // 如果直接匹配静态uri成功的话就不用启动[FastRoute]
        if (isset($this->routes[$method.$uri])) {
            $currentRoute = $this->routes[$method.$uri];
            return $this->handleFoundRoute($currentRoute['action']);
        }

        // 上面匹配不到则通过[FastRoute]匹配
         return $this->handleFastRoute(
            $routeInfo = $this->fastRoute()->dispatch($method, $uri)
         );
    }

    /**
     * 注册FastRoute并返回实例
     *
     * @return \FastRoute\Dispatcher
     */
    protected function fastRoute()
    {
        return simpleDispatcher(function (RouteCollector $collector) {
            foreach ($this->routes as $route) {
                $collector->addRoute($route['method'], $route['uri'], $route['action']);
            }
        });
    }

    /**
     * Handle the response from the FastRoute dispatcher.
     *
     * @param  array  $routeInfo
     * @return mixed
     */
    protected function handleFastRoute($routeInfo)
    {
        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                throw new Exception('404 Not Found.');
            case Dispatcher::METHOD_NOT_ALLOWED:
                throw new Exception('405 Method Not Allowed.');
            case Dispatcher::FOUND:
                return $this->handleFoundRoute(
                    $currentRoute = $routeInfo[1],
                    $actionVars = $routeInfo[2]
                );
        }
    }

    /**
     * 路由匹配成功以后，执行中间件，分发action
     *
     * @param $currentRoute
     * @param array $actionVars
     * @return mixed
     * @throws Exception
     */
    protected function handleFoundRoute($currentRoute, $actionVars = [])
    {
        $middlewares = $currentRoute['middleware'];
        $handler = $currentRoute['handler'];

        if (count($middlewares) > 0) {
            $response = (new Baton($this->app))
                ->send($this->app['request'])
                ->through($this->gatherMiddlewareClassNames($middlewares))
                ->then(function ($request) use ($handler, $actionVars) {
                    $this->app->instance('request', $request);
                    return $this->app->call($handler, $actionVars);
                });
        }

        return $this->app->call($handler, $actionVars);
    }

    /**
     * 获取中间件的类名以便于容器实例化
     *
     * @param $middlewares
     * @return array
     */
    protected function gatherMiddlewareClassNames($middlewares)
    {
        $registerd = $this->app->getRouteMiddlewares();

        return array_map(function ($middleware) use ($registerd) {
            return $this->app->get($registerd[$middleware]);
        }, $middlewares);
    }

    /**
     * 获取请求方法和路径，用于路由匹配
     *
     * @param $request
     * @return array
     */
    protected function parseIncomingRequest($request)
    {
        $method = $request->getMethod();
        $uri = $request->getUri()->getPath();
        $uri = ($uri === '/') ? $uri : rtrim($uri, '/');

        return [$method, $uri];
    }
}