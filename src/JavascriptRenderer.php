<?php
namespace think\debugbar;

use DebugBar\DebugBar;
use DebugBar\JavascriptRenderer as BaseJavascriptRenderer;


class JavascriptRenderer extends BaseJavascriptRenderer
{
    // Use XHR handler by default, instead of jQuery
    protected $ajaxHandlerBindToJquery = false;
    protected $ajaxHandlerBindToXHR = true;

    public function __construct(DebugBar $debugBar, $baseUrl = null, $basePath = null)
    {
        parent::__construct($debugBar, $baseUrl, $basePath);

        $this->cssFiles['thinkphp'] = __DIR__ . '/Resources/thinkphp-debugbar.css';
        $this->jsFiles['thinkphp-cache'] = __DIR__ . '/Resources/cache/widget.js';
//        dump(root_path().'vendor/maximebf/debugbar/src/DebugBar/Resources/widgets/sqlqueries/widget.js');
        $this->cssFiles['sql'] = root_path() . 'vendor/maximebf/debugbar/src/DebugBar/Resources/widgets/sqlqueries/widget.css';
        $this->jsFiles['sql'] = root_path() . 'vendor/maximebf/debugbar/src/DebugBar/Resources/widgets/sqlqueries/widget.js';

        $theme = config('debugbar.theme', 'auto');
        switch ($theme) {
            case 'dark':
                $this->cssFiles['laravel-dark'] = __DIR__ . '/Resources/thinkphp-debugbar-dark-mode.css';
                break;
            case 'auto':
                $this->cssFiles['laravel-dark-0'] = __DIR__ . '/Resources/thinkphp-debugbar-dark-mode-media-start.css';
                $this->cssFiles['laravel-dark-1'] = __DIR__ . '/Resources/thinkphp-debugbar-dark-mode.css';
                $this->cssFiles['laravel-dark-2'] = __DIR__ . '/Resources/thinkphp-debugbar-dark-mode-media-end.css';
        }
    }

    /**
     * Set the URL Generator
     *
     * @param \Illuminate\Routing\UrlGenerator $url
     * @deprecated
     */
    public function setUrlGenerator($url)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function renderHead()
    {
        $cssRoute = url('/_debugbar/assets/stylesheets', ['v' => $this->getModifiedTime('css'),
            'theme' => config('debugbar.theme', 'auto')]);

        $jsRoute = url('/_debugbar/assets/javascript', ['v' => $this->getModifiedTime('js'),
            'theme' => config('debugbar.theme', 'auto')]);

        $nonce = $this->getNonceAttribute();

        $html  = "<link rel='stylesheet' type='text/css' property='stylesheet' href='{$cssRoute}' data-turbolinks-eval='false' data-turbo-eval='false'>";
        $html .= "<script{$nonce} src='{$jsRoute}' data-turbolinks-eval='false' data-turbo-eval='false'></script>";

        if ($this->isJqueryNoConflictEnabled()) {
            $html .= "<script{$nonce} data-turbo-eval='false'>jQuery.noConflict(true);</script>" . "\n";
        }

        $inlineHtml = $this->getInlineHtml();
        if ($nonce != '') {
            $inlineHtml = preg_replace("/<(script|style)>/", "<$1{$nonce}>", $inlineHtml);
        }
        $html .= $inlineHtml;

        return $html;
    }

    protected function getInlineHtml()
    {
        $html = '';

        foreach (['head', 'css', 'js'] as $asset) {
            foreach ($this->getAssets('inline_' . $asset) as $item) {
                $html .= $item . "\n";
            }
        }

        return $html;
    }
    /**
     * Get the last modified time of any assets.
     *
     * @param string $type 'js' or 'css'
     * @return int
     */
    protected function getModifiedTime($type)
    {
        $files = $this->getAssets($type);

        $latest = 0;
        foreach ($files as $file) {
            $mtime = filemtime($file);
            if ($mtime > $latest) {
                $latest = $mtime;
            }
        }
        return $latest;
    }

    /**
     * Return assets as a string
     *
     * @param string $type 'js' or 'css'
     * @return string
     */
    public function dumpAssetsToString($type)
    {
        $files = $this->getAssets($type);

        $content = '';
        foreach ($files as $file) {
            $content .= file_get_contents($file) . "\n";
        }

        return $content;
    }

    /**
     * Makes a URI relative to another
     *
     * @param string|array $uri
     * @param string $root
     * @return string
     */
    protected function makeUriRelativeTo($uri, $root)
    {
        if (!$root) {
            return $uri;
        }

        if (is_array($uri)) {
            $uris = [];
            foreach ($uri as $u) {
                $uris[] = $this->makeUriRelativeTo($u, $root);
            }
            return $uris;
        }

        if (substr($uri ?? '', 0, 1) === '/' || preg_match('/^([a-zA-Z]+:\/\/|[a-zA-Z]:\/|[a-zA-Z]:\\\)/', $uri ?? '')) {
            return $uri;
        }
        return rtrim($root, '/') . "/$uri";
    }
}