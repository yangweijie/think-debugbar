<?php

namespace think\debugbar\Controller;

use DebugBar\DebugBarException;
use DebugBar\OpenHandler;
use think\debugbar\DebugBar;
use think\Request;
use think\facade\Session;

class OpenHandlerController
{
    protected $debugbar;

    public function __construct(Request $request, DebugBar $debugbar)
    {
        $this->debugbar = $debugbar;

        if ($request->session) {
            Session::flush();
        }
    }

    /**
     * Check if the storage is open for inspecting.
     *
     * @param Request $request
     * @return bool
     */
    protected function isStorageOpen(Request $request)
    {
        $open = config('debugbar.storage.open');

        if (is_callable($open)) {
            return call_user_func($open, [$request]);
        }

        if (is_string($open) && class_exists($open)) {
            return method_exists($open, 'resolve') ? $open::resolve($request) : false;
        }

        if (is_bool($open)) {
            return $open;
        }

        // Allow localhost request when not explicitly allowed/disallowed
        if (in_array($request->ip(), ['127.0.0.1', '::1'], true)) {
            return true;
        }

        return false;
    }

    public function handle(Request $request)
    {
        if ($request->param('op') === 'get' || $this->isStorageOpen($request)) {
            $openHandler = new OpenHandler($this->debugbar);
            $data = $openHandler->handle($request->param(), false, false);
            $data = json_decode($data, true);
        } else {
            $data = [
                [
                    'datetime' => date("Y-m-d H:i:s"),
                    'id' => null,
                    'ip' => $request->getClientIp(),
                    'method' => 'ERROR',
                    'uri' => '!! To enable public access to previous requests, set debugbar.storage.open to true in your config, or enable DEBUGBAR_OPEN_STORAGE if you did not publish the config. !!',
                    'utime' => microtime(true),
                ]
            ];
        }
        return json($data);
    }
}
