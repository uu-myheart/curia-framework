<?php

namespace Curia\Framework\Facades;

Class Facade
{
	protected static $app;

	protected static $resolved;

	/**
     * Set the application instance.
     *
     * @param  \Curia\Framework\Application  $app
     * @return void
     */
    public static function setFacadeApplication($app)
    {
        static::$app = $app;
    }

	public static function __callStatic($method, $parameters)
	{
		$instance = static::getFacadeInstance();

		return $instance->$method(...$parameters);
	}

	protected static function getFacadeInstance()
	{
		$name = static::getFacadeAccessor();

		if (! isset(static::$resolved[$name])) {
			$instance = static::$app[$name];

			static::$resolved[$name] = $instance;
		}

		return static::$resolved[$name];
	}
}