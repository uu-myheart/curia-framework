<?php

namespace Curia\Framework\Routing;

use Closure;
use Curia\Baton\Baton;
use Curia\Framework\Application;
use Psr\Http\Message\ResponseInterface;

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
     * Router constructor.
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

    public function addRoute($method,string $uri, $action)
    {
        [
            'uri' => $uri,
            'namespace' => $namespace,
            'middlewares' => $middlewares
        ] = $this->parseAttribute($uri);

        if (is_string($action)) {
            $action = $namespace . $action;
        }

        foreach ((array) $method as $verb) {
            $this->routes[$method.$uri] = compact('method', 'uri', 'action', 'middlewares');
        }
    }

    protected function parseAttribute(string $uri)
    {
        $namespace = '';
        $prefix = '';
        $middlewares = [];

        if (!empty($this->groupStack)) {
            foreach ($this->groupStack as $key => $stack) {
                if (isset($stack['namespace'])) {
                    $namespace .= trim($stack['namespace']) . '\\';
                }

                if (isset($stack['prefix'])) {
                    $prefix .=  '/' . trim($stack['prefix']);
                }

                if (isset($stack['middleware'])) {
                    $middlewares = array_merge($middlewares, $stack['middleware']);
                }
            }
        }
        
        $uri = $prefix . '/' . trim($uri, '/');

        return compact('namespace', 'uri', 'middlewares');
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
     * Handle the request.
     *
     * @param $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function handle($request)
    {
        [$method, $uri] = $this->parseIncomingRequest($request);

//         dd($this->routes);
        // Route matches.
        if (isset($this->routes[$method.$uri])) {
            $route = $this->routes[$method.$uri];

            //TODO
             if ($route['middlewares']) {
                 $response = (new Baton($this->app))
                                ->send($request)
                                ->through($this->getMiddlewares($route['middlewares']))
                                ->then(function ($request) {
                                    return $request;
                                });

                 return $response;
             }

            $data = $this->app->call($route['action']);

            return $this->generateResponse($data);
        }
    }

    protected function getMiddlewares($middlewares)
    {
        $registerd = $this->app->getRouteMiddlewares();

        return array_map(function ($middleware) use ($registerd) {
            return $this->app->get($registerd[$middleware]);
        }, $middlewares);
    }

    /**
     * Transform given type to a response.
     * @param $data
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function generateResponse($data)
    {
        if ($data instanceof ResponseInterface) {
            return $data;
        }

        $response = $this->app['response'];
        $body = is_array($data) ? json_encode($data) : $data;
        $response->getBody()->write($body);

        return $response;
    }

    protected function parseIncomingRequest($request)
    {
        return [
            $request->getMethod(),
            rtrim($request->getUri()->getPath(), '/'),
        ];
    }
}