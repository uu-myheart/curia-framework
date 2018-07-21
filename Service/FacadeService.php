<?php

namespace Curia\Framework\Service;

use Curia\Framework\Facades\Facade;

class FacadeService extends Service
{
    /**
     * 调试模式中启动Whoops
     */
    public function register()
    {
        Facade::setFacadeApplication($this->app);

        $aliases = $this->app['config']['app.aliases'];

        foreach ($aliases as $alias => $class) {
            class_alias($class, $alias);
        }
    }
}