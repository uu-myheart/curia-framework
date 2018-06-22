<?php

if (! function_exists('app')) {
    function app($abstract = null)
    {
        $app = Curia\Framework\Application::getInstance();

        return is_null($abstract) ? $app : $app->get($abstract);
    }
}