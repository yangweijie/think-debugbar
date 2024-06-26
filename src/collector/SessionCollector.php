<?php

namespace think\debugbar\collector;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\DataCollectorInterface;
use DebugBar\DataCollector\Renderable;
use think\Session;

class SessionCollector extends DataCollector implements DataCollectorInterface, Renderable
{
    protected $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    /**
     * {@inheritdoc}
     */
    public function collect()
    {
        $data = [];
        foreach ($this->session->all() as $key => $value) {
            $data[$key] = is_string($value) ? $value : $this->formatVar($value);
        }
        return $data;
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return '会话';
    }

    /**
     * {@inheritDoc}
     */
    public function getWidgets()
    {
        return [
            "会话" => [
                "icon"    => "archive",
                "widget"  => "PhpDebugBar.Widgets.VariableListWidget",
                "map"     => "会话",
                "default" => "{}",
            ],
        ];
    }
}
