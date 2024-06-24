<?php

namespace think\debugbar\event;

use think\App;
use think\debugbar\DebugBar;

class AppInit
{
    public function handle(DebugBar $debugBar)
    {
        $debugBar->enable();
        // 事件监听处理
        $debugBar->addMeasure('AppInit', $debugBar->app->request->time(), microtime(true), [], 'time');
    }
}