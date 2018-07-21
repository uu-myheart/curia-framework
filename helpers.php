<?php

if (! function_exists('app')) {
    function app($abstract = null)
    {
        $app = Curia\Framework\Application::getInstance();

        return is_null($abstract) ? $app : $app->get($abstract);
    }
}

if (! function_exists('response')) {
    function response($data = '', $statusCode = 200)
    {
        $response = app('response');

        if (is_array($data)) {
            $data = json_encode($data);
        }

        $response->getBody()->write($data);

        return $response->withStatus($statusCode);
    }
}

if (! function_exists('redirect')) {
    function redirect($uri, $status = 302, array $headers = [])
    {
        return new \Curia\Framework\Http\Redirect($uri, $status, $headers);
    }
}

if (! function_exists('request')) {
    function request(...$args)
    {
        if (! $args) {
            return app('request');
        }

        return request()->only(...$args);
    }
}