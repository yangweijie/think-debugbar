<?php

namespace think\debugbar;

use Exception;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\ErrorException;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\Response;
use Throwable;


class ExceptionHandle extends Handle
{
    /**
     * 不需要记录信息（日志）的异常类列表
     * @var array
     */
    protected $ignoreReport = [
        HttpException::class,
        HttpResponseException::class,
        ModelNotFoundException::class,
        DataNotFoundException::class,
        ValidateException::class,
    ];

    /**
     * 记录异常信息（包括日志或者其它方式记录）
     *
     * @access public
     * @param  Throwable $exception
     * @return void
     */
    public function report(Throwable $exception): void
    {
        if (!$this->isIgnoreReport($exception)) {
            // 收集异常数据
            if ($this->app->isDebug()) {
                $data = [
                    'file'    => $exception->getFile(),
                    'line'    => $exception->getLine(),
                    'message' => $this->getMessage($exception),
                    'code'    => $this->getCode($exception),
                ];
                $log = "[{$data['code']}]{$data['message']}[{$data['file']}:{$data['line']}]";
            } else {
                $data = [
                    'code'    => $this->getCode($exception),
                    'message' => $this->getMessage($exception),
                ];
                $log = "[{$data['code']}]{$data['message']}";
            }

            if ($this->app->config->get('log.record_trace')) {
                $log .= PHP_EOL . $exception->getTraceAsString();
            }

            try {
                $this->app->log->record($log, 'error');
                if($this->app->request->debugbar->shouldCollect('exceptions', true)){
                    if($exception instanceof Exception){
                        $this->app->request->debugbar['exceptions']->addException($exception);
                    }
                }
            } catch (Exception $e) {

            }
        }

    }

    /**
     * Render an exception into an HTTP response.
     *
     * @access public
     * @param \think\Request   $request
     * @param Throwable $e
     * @return Response
     */
    public function render($request, Throwable $e): Response
    {
        // 添加自定义异常处理机制

        // 其他错误交给系统处理
        return parent::render($request, $e);
    }

    /**
     * 收集异常数据
     * @param Throwable $exception
     * @return array
     */
    protected function convertExceptionToArray(Throwable $exception): array {
        if ($this->app->isDebug()) {
            // 调试模式，获取详细的错误信息
            $traces = [];
            $nextException = $exception;
            do {
                $file = $nextException->getFile();
                // 模板缓存异常
                if (str_contains($file, 'runtime')) {
                    $source = $this->getSourceCode($nextException);
                    $template = substr($source['source'][0], 8, strlen($source['source'][0]) - 14);
                    $template = array_keys(unserialize($template))[0];
                    $next_traces = $nextException->getTrace();
                    $next_traces[0]['file'] = $template;
                    $next_traces[0]['line'] = $next_traces[0]['line'] - 1;
                    $next_traces[0]['args'][2] = $template;
                    $next_traces[0]['args'][3] = $next_traces[0]['line'];
                    $traces[] = [
                        'name' => get_class($nextException),
                        'file' => $template,
                        'line' => $nextException->getLine() - 1,
                        'code' => $this->getCode($nextException),
                        'message' => $this->getMessage($nextException),
                        'trace' => $next_traces,
                        'source' => ['first' => 1, 'source' => explode(PHP_EOL, file_get_contents($template))],
                    ];
                } else {
                    $traces[] = [
                        'name' => get_class($nextException),
                        'file' => $nextException->getFile(),
                        'line' => $nextException->getLine(),
                        'code' => $this->getCode($nextException),
                        'message' => $this->getMessage($nextException),
                        'trace' => $nextException->getTrace(),
                        'source' => $this->getSourceCode($nextException),
                    ];
                }
            } while ($nextException = $nextException->getPrevious());
            $data = [
                'code' => $this->getCode($exception),
                'message' => $this->getMessage($exception),
                'traces' => $traces,
                'datas' => $this->getExtendData($exception),
                'tables' => [
                    'GET Data' => $this->app->request->get(),
                    'POST Data' => $this->app->request->post(),
                    'Files' => $this->app->request->file(),
                    'Cookies' => $this->app->request->cookie(),
                    'Session' => $this->app->exists('session') ? $this->app->session->all() : [],
                    'Server/Request Data' => $this->app->request->server(),
                ],
            ];
        } else {
            // 部署模式仅显示 Code 和 Message
            $data = [
                'code' => $this->getCode($exception),
                'message' => $this->getMessage($exception),
            ];

            if (!$this->app->config->get('app.show_error_msg')) {
                // 不显示详细错误信息
                $data['message'] = $this->app->config->get('app.error_message');
            }
        }

        return $data;
    }
}