<?php

namespace think\debugbar;

use think\debugbar\storage\FilesystemStorage;
use think\debugbar\storage\SocketStorage;
use Closure;
use DebugBar\DataCollector\ConfigCollector;
use DebugBar\DataCollector\DataCollectorInterface;
use DebugBar\DataCollector\MemoryCollector;
use DebugBar\DataCollector\MessagesCollector;
use DebugBar\DataCollector\ObjectCountCollector;
use DebugBar\DataCollector\PhpInfoCollector;
use DebugBar\DataCollector\TimeDataCollector;
use DebugBar\Storage\PdoStorage;
use DebugBar\Storage\RedisStorage;
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
    public $app;
    /**
     * True when booted.
     *
     * @var bool
     */
    protected $booted = false;

    /**
     * True when enabled, false disabled on null for still unknown
     *
     * @var bool
     */
    protected $enabled = null;


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

        if ($this->booted) {
            return;
        }

        $events = $this->app->event;
        $this->editorTemplateLink = $this->app->config->get('debugbar.editor') ?: null;
        $this->remoteServerReplacements = $this->getRemoteServerReplacements();

        $config = $this->app->config;

        // Set custom error handler
        if ($config->get('debugbar.error_handler', false)) {
            set_error_handler([$this, 'handleError']);
        }

        $this->addCollector(new ThinkCollector($this->app));
        $this->addCollector(new PhpInfoCollector());
        $this->addCollector(new MessagesCollector());
        $this->addCollector(new RequestDataCollector($this->app->request));

        if ($this->shouldCollect('time', true)) {
            $startTime = $this->app->request->time();
            $this->addCollector(new TimeDataCollector($startTime));

            if ($config->get('debugbar.options.time.memory_usage')) {
                $this['time']->showMemoryUsage();
            }

            $this->startMeasure('application', 'Application', 'time');
        }

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

        if ($this->shouldCollect('db', true) && isset($this->app->db) && $events) {
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
        $this->booted = true;
    }

    /**
     * Enable the Debugbar and boot, if not already booted.
     */
    public function enable()
    {
        $this->enabled = true;

        if (!$this->booted) {
            $this->init();
        }
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

    /**
     * @param \DebugBar\DebugBar $debugbar
     */
    protected function selectStorage(DebugBar $debugbar)
    {
        /** @var \Illuminate\Config\Repository $config */
        $config = $this->app['config'];
        if ($config->get('debugbar.storage.enabled')) {
            $driver = $config->get('debugbar.storage.driver', 'file');

            switch ($driver) {
                case 'pdo':
                    $connection = $config->get('debugbar.storage.connection');
                    $table = $this->app['db']->getTablePrefix() . 'phpdebugbar';
                    $pdo = $this->app['db']->connection($connection)->getPdo();
                    $storage = new PdoStorage($pdo, $table);
                    break;
                case 'redis':
                    $connection = $config->get('debugbar.storage.connection');
                    $client = $this->app['redis']->connection($connection);
                    if (is_a($client, 'Illuminate\Redis\Connections\Connection', false)) {
                        $client = $client->client();
                    }
                    $storage = new RedisStorage($client);
                    break;
                case 'custom':
                    $class = $config->get('debugbar.storage.provider');
                    $storage = $this->app->make($class);
                    break;
                case 'socket':
                    $hostname = $config->get('debugbar.storage.hostname', '127.0.0.1');
                    $port = $config->get('debugbar.storage.port', 2304);
                    $storage = new SocketStorage($hostname, $port);
                    break;
                case 'file':
                default:
                    $path = $config->get('debugbar.storage.path');
                    $storage = new FilesystemStorage($this->app['files'], $path);
                    break;
            }

            $debugbar->setStorage($storage);
        }
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
     * Handle silenced errors
     *
     * @param $level
     * @param $message
     * @param string $file
     * @param int $line
     * @param array $context
     * @throws \ErrorException
     */
    public function handleError($level, $message, $file = '', $line = 0, $context = [])
    {
        $exception = new \ErrorException($message, 0, $level, $file, $line);
        if (error_reporting() & $level) {
            throw $exception;
        }

        $this->addThrowable($exception);
        if ($this->hasCollector('messages')) {
            $file = $file ? ' on ' . $this['messages']->normalizeFilePath($file) . ":{$line}" : '';
            $this['messages']->addMessage($message . $file, 'deprecation');
        }
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
