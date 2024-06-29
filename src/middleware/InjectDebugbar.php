<?php

namespace think\debugbar\middleware;

use think\debugbar\DebugBar;
use think\helper\Str;
use think\Log;
use think\Request;

class InjectDebugbar
{
    protected $debugbar;
    protected $log;
    protected $app;

    public function __construct(DebugBar $debugbar, Log $log)
    {
        $this->debugbar = $debugbar;
        $this->log      = $log;
    }

    public function handle(Request $request, $next)
    {
        if (Str::startsWith($request->pathinfo(), "_debugbar/")) {
            return $next($request);
        }

        $this->debugbar->init();

        $request->debugbar = $this->debugbar;

        $response = $next($request);
        $this->debugbar['请求']->setResponseHeader($response->getHeader());
        if(!$request->isAjax() && !$request->isOptions() && !$request->isCli() && stripos($response->getHeader('Content-Type'), 'json') === false){
            $this->debugbar->inject($response);
        }
        $this->debugbar->stackData();
        $this->debugbar->sendDataInHeaders(true);

        return $response;
    }

}
