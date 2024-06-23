<?php

namespace think\debugbar;

use Closure;
use DebugBar\DataCollector\ConfigCollector;
use DebugBar\DataCollector\DataCollectorInterface;
use DebugBar\DataCollector\MemoryCollector;
use DebugBar\DataCollector\MessagesCollector;
use DebugBar\DataCollector\ObjectCountCollector;
use DebugBar\DataCollector\PhpInfoCollector;
use DebugBar\DataCollector\TimeDataCollector;
use think\db\Query;
use think\debugbar\collector\FilesCollector;
use think\debugbar\collector\RequestDataCollector;
use think\debugbar\collector\SessionCollector;
use think\debugbar\collector\ThinkCollector;
use think\debugbar\collector\SqlCollector;
use think\debugbar\formatter\QueryFormatter;
use think\event\LogWrite;
use think\facade\Db;
use think\Response;
use think\response\Redirect;
use think\Session;
use think\App;

class DebugBar extends \DebugBar\DebugBar
{
    protected $app;

    protected ?string $editorTemplateLink = null;
    protected array $remoteServerReplacements = [];

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function getJavascriptRenderer($baseUrl = null, $basePath = null)
    {
        if ($this->jsRenderer === null) {
            $this->jsRenderer = new JavascriptRenderer($this, $baseUrl, $basePath);
        }
        return $this->jsRenderer;
    }

    /**
     * Starts a measure
     *
     * @param string $name  Internal name, used to stop the measure
     * @param string $label Public name
     */
    public function startMeasure($name, $label = null)
    {
        if ($this->hasCollector('time')) {
            /** @var TimeDataCollector $collector */
            $collector = $this->getCollector('time');
            $collector->startMeasure($name, $label);
        }
    }

    /**
     * Stops a measure
     *
     * @param string $name
     */
    public function stopMeasure($name)
    {
        if ($this->hasCollector('time')) {
            /** @var TimeDataCollector $collector */
            $collector = $this->getCollector('time');
            try {
                $collector->stopMeasure($name);
            } catch (\Exception $e) {
                //  $this->addThrowable($e);
            }
        }
    }

    /**
     * Adds a measure
     *
     * @param string $label
     * @param float  $start
     * @param float  $end
     */
    public function addMeasure($label, $start, $end)
    {
        if ($this->hasCollector('time')) {
            /** @var TimeDataCollector $collector */
            $collector = $this->getCollector('time');
            $collector->addMeasure($label, $start, $end);
        }
    }

    /**
     * Utility function to measure the execution of a Closure
     *
     * @param string  $label
     * @param Closure $closure
     */
    public function measure($label, Closure $closure)
    {
        if ($this->hasCollector('time')) {
            /** @var TimeDataCollector $collector */
            $collector = $this->getCollector('time');
            $collector->measure($label, $closure);
        } else {
            $closure();
        }
    }

    public function addMessage($message, $label = 'info')
    {
        if ($this->hasCollector('messages')) {
            /** @var MessagesCollector $collector */
            $collector = $this->getCollector('messages');
            $collector->addMessage($message, $label);
        }
    }

    public function getDsn($config)
    {
        $db = Db::connect();
        $driver = $config->get('database.connections.' . $config->get('database.default').'.type');
        $class = sprintf('think\db\connector\%s', ucfirst($driver));
        $method = new \ReflectionMethod(sprintf('think\db\connector\%s', ucfirst($driver)), 'parseDsn');
        $method->setAccessible(true);
        return $method->invoke($db, $config->get('database.connections.' . $config->get('database.default')));
    }

    public function init()
    {
        $events = $this->app->event;
        $this->editorTemplateLink = $this->app->config->get('debugbar.editor') ?: null;
        $this->remoteServerReplacements = $this->getRemoteServerReplacements();

        $this->addCollector(new ThinkCollector($this->app));
        $this->addCollector(new PhpInfoCollector());
        $this->addCollector(new MessagesCollector());
        $this->addCollector(new RequestDataCollector($this->app->request));
        $this->addCollector(new TimeDataCollector($this->app->request->time()));
        $this->addCollector(new MemoryCollector());

        //配置
        $configCollector = new ConfigCollector([], '配置');
        $configCollector->setData($this->app->config->get());
        $this->addCollector($configCollector);
        if ($this->shouldCollect('models', true) && $events) {
            try {
                $this->addCollector(new ObjectCountCollector('模型'));
                $models = [];
                $events->listen('model.*', function ($model) use(&$models) {
                    if($this['模型']->getXdebugLinkTemplate() == ''){
                        $this['模型']->setEditorLinkTemplate('phpstorm');
                    }
                    $this['模型']->countClass($model);
                });
            } catch (Exception $e) {
                $this->addCollectorException('Cannot add Models Collector', $e);
            }
        }

        $logger = new MessagesCollector('log');
        $this['messages']->aggregate($logger);

        $this->app->log->listen(function (LogWrite $event) use ($logger, $events) {
            $database_tab = ($this->shouldCollect('db', true) && isset($this->app->db) && $events);
            foreach ($event->log as $channel => $logs) {
                foreach ($logs as $log) {
                    if($database_tab && $channel != 'sql')
                        $logger->addMessage(
                            '[' . date('H:i:s') . '] ' . $log,
                            $channel,
                            false
                        );
                }
            }
        });

        $config = $this->app->config;
        if ($this->shouldCollect('db', true) && isset($this->app->db) && $events) {
//            $events->listen('db.*', function($query){
//                $pdo = new \DebugBar\DataCollector\PDO\TraceablePDO($query->getConnection()->getPdo());
//                if(!isset($this['pdo'])){
//                    $this->addCollector(new \DebugBar\DataCollector\PDO\PDOCollector($pdo));
//                }
//                $this['pdo']->collect();
////                $this['pdo']->collect();
//            });
            
            if ($this->hasCollector('time') && $config->get('debugbar.options.db.timeline', false)) {
                $timeCollector = $this['time'];
            } else {
                $timeCollector = null;
            }
            $queryCollector = new SqlCollector($timeCollector);

            $queryCollector->setDataFormatter(new QueryFormatter());
            $queryCollector->setLimits($config->get('debugbar.options.db.soft_limit'), $config->get('debugbar.options.db.hard_limit'));
            $queryCollector->setDurationBackground($config->get('debugbar.options.db.duration_background'));

            if ($config->get('debugbar.options.db.with_params')) {
                $queryCollector->setRenderSqlWithParams(true);
            }

            if ($dbBacktrace = $config->get('debugbar.options.db.backtrace')) {
                $middleware = [];
                $queryCollector->setFindSource($dbBacktrace, $middleware);
            }

            if ($excludePaths = $config->get('debugbar.options.db.backtrace_exclude_paths')) {
                $queryCollector->mergeBacktraceExcludePaths($excludePaths);
            }

            if ($config->get('debugbar.options.db.explain.enabled')) {
                $types = $config->get('debugbar.options.db.explain.types');
                $queryCollector->setExplainSource(true, $types);
            }

            if ($config->get('debugbar.options.db.hints', true)) {
                $queryCollector->setShowHints(true);
            }

            if ($config->get('debugbar.options.db.show_copy', false)) {
                $queryCollector->setShowCopyButton(true);
            }

            $this->addCollector($queryCollector);

            try {
                $events->listen(
                    'db.*',
                    function ($query){
//                        dump($query->getConnection());
                        if (!app(static::class)->shouldCollect('db', true)) {
                            return; // Issue 776 : We've turned off collecting after the listener was attached
                        }

                        $this['queries']->addQuery($query);
                        //allow collecting only queries slower than a specified amount of milliseconds
//                        $threshold = app('config')->get('debugbar.options.db.slow_threshold', false);
//                        if (!$threshold || $query->time > $threshold) {
//                            $this['queries']->addQuery($query);
//                        }
                    }
                );
            } catch (Exception $e) {
                $this->addCollectorException('Cannot listen to Queries', $e);
            }

//            try {
//
//            } catch (Exception $e) {
//                $this->addCollectorException('Cannot listen transactions to Queries', $e);
//            }
        }

        //文件
        $this->addCollector(new FilesCollector($this->app));
    }

    public function shouldCollect($name, $default = false)
    {
        return $this->app['config']->get('debugbar.collectors.' . $name, $default);
    }
    
    public function addCollector(DataCollectorInterface $collector)
    {
        parent::addCollector($collector);

        if (method_exists($collector, 'useHtmlVarDumper')) {
            $collector->useHtmlVarDumper();
        }

        return $this;
    }

    public function inject(Response $response)
    {
        if ($response instanceof Redirect) {
            return;
        }

        if ($this->app->exists(Session::class)) {
            $this->addCollector(new  SessionCollector($this->app->make(Session::class)));
        }

        $content = $response->getContent();

        //把缓冲区的日志写入
        $this->app->log->save();

        $renderer = $this->getJavascriptRenderer();

        $renderedContent = $renderer->renderHead() . $renderer->render();

        // trace调试信息注入
        $pos = strripos($content, '</body>');
        if (false !== $pos) {
            $content = substr($content, 0, $pos) . $renderedContent . substr($content, $pos);
        } else {
            $content = $content . $renderedContent;
        }
        $response->content($content);
    }

    private function getRemoteServerReplacements()
    {
        $localPath = $this->app['config']->get('debugbar.local_sites_path') ?: base_path();
        $remotePaths = array_filter(explode(',', $this->app['config']->get('debugbar.remote_sites_path') ?: '')) ?: [base_path()];

        return array_fill_keys($remotePaths, $localPath);
    }

    /**
     * Magic calls for adding messages
     *
     * @param string $method
     * @param array $args
     * @return mixed|void
     */
    public function __call($method, $args)
    {
        $messageLevels = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug', 'log'];
        if (in_array($method, $messageLevels)) {
            foreach ($args as $arg) {
                $this->addMessage($arg, $method);
            }
        }
    }
}
