<?php

namespace Curia\Framework\Service;

use Curia\Framework\Database\DatabaseManager;

class DatabaseService extends Service
{
    public function register()
    {
        $this->app->singleton('db', function ($app) {
            return new DatabaseManager($app);
        });
    }
}