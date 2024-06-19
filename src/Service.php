<?php

namespace think\debugbar;

use think\debugbar\controller\AssetController;
use think\debugbar\middleware\InjectDebugbar;
use think\Route;

class Service extends \think\Service
{
    public function boot(Route $route)
    {
        $this->app->middleware->add(InjectDebugbar::class);
        $route->get("debugbar/:path", AssetController::class . "@index")->pattern(['path' => '[\w\.\/\-_]+']);
    }
}
