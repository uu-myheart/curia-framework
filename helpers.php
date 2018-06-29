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