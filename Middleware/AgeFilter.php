<?php

namespace Curia\Framework\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AgeFilter implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
    {
        if ($request->has('age') && ($request->get('age') < 18)) {
            return response('Teenager forbiden!', 401);
        }

        return $next->handle($request);
    }
}