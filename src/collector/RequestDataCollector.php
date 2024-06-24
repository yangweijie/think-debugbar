<?php

namespace think\debugbar\collector;

use think\Request;

class RequestDataCollector extends \DebugBar\DataCollector\RequestDataCollector
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function getName()
    {
        return '请求';
    }

    /**
     * Called by the DebugBar when data needs to be collected
     *
     * @return array Collected data
     */
    function collect()
    {
        $request = $this->request;

        $data = [
            'path_info' => $request->pathinfo(),
            'query'     => $request->get(),
            'request'   => $request->request(),
            'headers'   => $request->header(),
            'server'    => $request->server(),
            'cookies'   => $request->cookie(),
        ];

        foreach ($data as $key => $var) {
            if ($this->isHtmlVarDumperUsed()) {
                $data[$key] = $this->getVarDumper()->renderVar($data[$key]);
            } else {
                $data[$key] = $this->getDataFormatter()->formatVar($data[$key]);
            }
        }

        return $data;
    }

    /**
     * @return array
     */
    public function getWidgets()
    {
        $name = $this->getName();
        $widget = $this->isHtmlVarDumperUsed()
            ? "PhpDebugBar.Widgets.HtmlVariableListWidget"
            : "PhpDebugBar.Widgets.VariableListWidget";
        return array(
            $name => array(
                "icon" => "tags",
                "widget" => $widget,
                "map" => $name,
                "default" => "{}"
            )
        );
    }

}
