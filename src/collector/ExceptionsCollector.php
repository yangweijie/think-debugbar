<?php
/*
 * This file is part of the DebugBar package.
 *
 * (c) 2013 Maxime Bouroumeau-Fuseau
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace think\debugbar\collector;

use Exception;
use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Symfony\Component\Debug\Exception\FatalThrowableError;
use think\exception\ErrorException;

/**
 * Collects info about exceptions
 */
class ExceptionsCollector extends DataCollector implements Renderable
{
    protected $exceptions = array();
    protected $chainExceptions = false;

    /**
     * Adds an exception to be profiled in the debug bar
     *
     * @param Exception $e
     * @deprecated in favor on addThrowable
     */
    public function addException(Exception $e)
    {
        $this->addThrowable($e);
    }

    /**
     * Adds a Throwable to be profiled in the debug bar
     *
     * @param \Throwable $e
     */
    public function addThrowable($e)
    {
        $this->exceptions[] = $e;
        if ($this->chainExceptions && $previous = $e->getPrevious()) {
            $this->addThrowable($previous);
        }
    }

    /**
     * Configure whether or not all chained exceptions should be shown.
     *
     * @param bool $chainExceptions
     */
    public function setChainExceptions($chainExceptions = true)
    {
        $this->chainExceptions = $chainExceptions;
    }

    /**
     * Returns the list of exceptions being profiled
     *
     * @return array[\Throwable]
     */
    public function getExceptions()
    {
        return $this->exceptions;
    }

    public function collect()
    {
        return array(
            'count' => count($this->exceptions),
            'exceptions' => array_map(array($this, 'formatThrowableData'), $this->exceptions)
        );
    }

    /**
     * Returns exception data as an array
     *
     * @param Exception $e
     * @return array
     * @deprecated in favor on formatThrowableData
     */
    public function formatExceptionData(Exception $e)
    {
        return $this->formatThrowableData($e);
    }

    /**
     * Returns Throwable trace as an formated array
     *
     * @return array
     */
    public function formatTrace(array $trace)
    {
        if (! empty($this->xdebugReplacements)) {
            $trace = array_map(function ($track) {
                if (isset($track['file'])) {
                    $track['file'] = $this->normalizeFilePath($track['file']);
                }

                return $track;
            }, $trace);
        }

        return $trace;
    }

    /**
     * Returns Throwable data as an string
     *
     * @param \Throwable $e
     * @return string
     */
    public function formatTraceAsString($e)
    {
        if (! empty($this->xdebugReplacements)) {
            return implode("\n", array_map(function ($track) {
                $track = explode(' ', $track);
                if (isset($track[1])) {
                    $track[1] = $this->normalizeFilePath($track[1]);
                }

                return implode(' ', $track);
            }, explode("\n", $e->getTraceAsString())));
        }

        return $e->getTraceAsString();
    }

    /**
     * Returns Throwable data as an array
     *
     * @param \Throwable $e
     * @return array
     */
    public function formatThrowableData($e)
    {
        $filePath = $e->getFile();
        $source = '';
        if ($filePath && file_exists($filePath)) {
            // 模板缓存异常
            if (str_contains($filePath, 'runtime')) {
                $nextException = $e;

                $traces = [];
                do {
                    $file = $nextException->getFile();
                    // 模板缓存异常
                    if (str_contains($file, 'runtime')) {
                        $source = file($filePath);
                        $template = substr($source[0], 8, strlen($source[0]) - 14);
                        $template = array_keys(unserialize($template))[0];
                        $filePath = $template;
                        $lines = array_slice(file($filePath), 0, 7);
                        $next_traces = $nextException->getTrace();
                        $next_traces[0]['file'] = $template;
                        $next_traces[0]['line'] = $next_traces[0]['line'] - 1;
                        $next_traces[0]['args'][2] = $template;
                        $next_traces[0]['args'][3] = $next_traces[0]['line'];
                        $traces[] = [
                            'name' => get_class($nextException),
                            'file' => $template,
                            'line' => $nextException->getLine() - 1,
                            'code' => $nextException instanceof ErrorException? $nextException->getSeverity(): $nextException->getCode(),
                            'message' =>$nextException->getMessage(),
                            'trace' => $next_traces,
                            'source' => ['first' => 1, 'source' => explode(PHP_EOL, file_get_contents($template))],
                        ];
                    } else {
                        $traces[] = [
                            'name' => get_class($nextException),
                            'file' => $nextException->getFile(),
                            'line' => $nextException->getLine(),
                            'code' => $nextException instanceof ErrorException? $nextException->getSeverity(): $nextException->getCode(),
                            'message' =>$nextException->getMessage(),
                            'trace' => $nextException->getTrace(),
                            'source' => $this->getSourceCode($nextException),
                        ];
                    }
                } while ($nextException = $nextException->getPrevious());
            }else{
                $traces = [];
                $lines = file($filePath);
                $start = $e->getLine() - 4;
                $lines = array_slice($lines, $start < 0 ? 0 : $start, 7);
            }
        } else {
            $lines = array('Cannot open the file ('.$this->normalizeFilePath($filePath).') in which the exception occurred');
        }

        $traceHtml = null;
        if ($this->isHtmlVarDumperUsed()) {
            $traceHtml = $this->getVarDumper()->renderVar($this->formatTrace($traces?:$e->getTrace()));
        }

        if($source){
            return [
                'type' => get_class($e),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $this->normalizeFilePath($filePath),
                'line' => $e->getLine() - 1,
                'stack_trace' => $traceHtml ? null : $this->formatTraceAsString($e),
                'stack_trace_html' => $traceHtml,
                'surrounding_lines' => $lines,
                'xdebug_link' => $this->getXdebugLink($filePath, $e->getLine() -1)
            ];
        }
        return array(
            'type' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $this->normalizeFilePath($filePath),
            'line' => $e->getLine(),
            'stack_trace' => $traceHtml ? null : $this->formatTraceAsString($e),
            'stack_trace_html' => $traceHtml,
            'surrounding_lines' => $lines,
            'xdebug_link' => $this->getXdebugLink($filePath, $e->getLine())
        );
    }



    /**
     * @return string
     */
    public function getName()
    {
        return 'exceptions';
    }

    /**
     * @return array
     */
    public function getWidgets()
    {
        return array(
            'exceptions' => array(
                'icon' => 'bug',
                'widget' => 'PhpDebugBar.Widgets.ExceptionsWidget',
                'map' => 'exceptions.exceptions',
                'default' => '[]'
            ),
            'exceptions:badge' => array(
                'map' => 'exceptions.count',
                'default' => 'null'
            )
        );
    }
}
