<?php

namespace Curia\Framework\Middleware;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use function FastRoute\simpleDispatcher;
use Psr\Http\Server\RequestHandlerInterface;

class Router implements MiddlewareInterface
{
    /**
     * Psr-7 middleware processer.
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * @throws \Exception
     */
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		$routes = simpleDispatcher(function (RouteCollector $router) {
			require app()->basePath() . '/app/routes.php';
		});

		// Fetch method and URI.
        $method = $request->getMethod();
        $uri = $request->getUri()->getPath();

        $routeInfo = $routes->dispatch($method, $uri);
		switch ($routeInfo[0]) {
		    case Dispatcher::NOT_FOUND:
		    	throw new \Exception('404 Not Found.');
		    case Dispatcher::METHOD_NOT_ALLOWED:
                throw new \Exception('405 Method Not Allowed');
		    case Dispatcher::FOUND:
                $callable = $routeInfo[1];
                $attributes = $routeInfo[2];
                $data = app()->call($callable, $attributes);

                return $this->response($data);
		}
	}

    /**
     * Transform give type to a response.
     * @param $data
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function response($data)
    {
        if ($data instanceof ResponseInterface) {
            return $data;
        }

        $response = app('response');
        $body = is_array($data) ? json_encode($data) : $data;
        $response->getBody()->write($body);

        return $response;
	}
}