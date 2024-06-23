<?php

namespace think\debugbar\controller;

use think\debugbar\DebugBar;
use think\Request;
use think\facade\Session;
use think\helper\Arr;
use think\Response;

class AssetController
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
     * Return the javascript for the Debugbar
     *
     */
    public function js()
    {
        $renderer = $this->debugbar->getJavascriptRenderer();
//        $renderer->addControl();
        $content = $renderer->dumpAssetsToString('js');

        return response($content)->contentType('application/javascript')->allowCache(true)
//            ->cacheControl('')
            ;
    }

    /**
     * Return the stylesheets for the Debugbar
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function css()
    {
        $renderer = $this->debugbar->getJavascriptRenderer();

        $content = $renderer->dumpAssetsToString('css');

        return response($content)->contentType('text/css')->allowCache(true)
//            ->cacheControl('')
            ;
    }


}
