<?php

namespace Curia\Framework\Service;

class ExceptionService extends Service
{
    /**
     * 调试模式中启动Whoops
     */
    public function register()
    {
    	if (env('APP_DEBUG')) {
	        $whoops = new \Whoops\Run;
	        $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
	        $whoops->register();
    	}
    }
}