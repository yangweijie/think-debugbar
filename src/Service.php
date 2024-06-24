<?php

namespace think\debugbar;

use think\debugbar\controller\AssetController;
use think\debugbar\Controller\OpenHandlerController;
use think\debugbar\middleware\InjectDebugbar;
use think\Route;

class Service extends \think\Service
{
    public function boot(Route $route)
    {

        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $this->app->middleware->add(InjectDebugbar::class);
        $route->get('_debugbar/assets/stylesheets', AssetController::class . "@css");
        $route->get('_debugbar/assets/javascript', AssetController::class . "@js");
        $route->get('_debugbar/handle', OpenHandlerController::class . "@handle");

//        $router->delete('cache/{key}/{tags?}', [
//            'uses' => 'CacheController@delete',
//            'as' => 'debugbar.cache.delete',
//        ]);
//        $route->get("debugbar/:path", AssetController::class . "@index")->pattern(['path' => '[\w\.\/\-_]+']);
    }
}
