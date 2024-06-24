# think-debugbar

用于ThinkPHP6+的[DebugBar](http://phpdebugbar.com/)扩展。

## 安装

~~~
composer require topthink/think-debugbar
~~~

nginx 项目的rewrite里要配ThinkPHP的

~~~
location / {
	index index.php;
	if (!-e $request_filename){
		rewrite  ^(.*)$  /index.php?s=$1  last;
		break;
	}
}
~~~

静态资源缓存要注释掉
~~~
#    location ~ .+\.(gif|jpg|jpeg|png|bmp|swf)$
#    {
#        expires      1d;
#        error_log nul;
#        access_log off;
#    }

#    location ~ .+\.(js|css)$
#    {
#        expires      1h;
#        error_log nul;
#        access_log off;
#    }
~~~

## 扩展
### collectors
#### 普通
~~~ tab
class MyDataCollector extends DebugBar\DataCollector\DataCollector
{
    public function collect()
    {
        return array("uniqid" => uniqid());
    }

    public function getName()
    {
        return 'mycollector';
    }
}
~~~

~~~ widget
class MyDataCollector extends DebugBar\DataCollector\DataCollector implements DebugBar\DataCollector\Renderable
{
    // ...

    public function getWidgets()
    {
        return array(
            "mycollector" => array(
                "icon" => "cogs",
                "tooltip" => "uniqid()",
                "map" => "uniqid",
                "default" => "''"
            ),
            "mycollector:badge" => [
                "map" => "SQL.nb_statements",
                "default" => 0
            ]
        );
    }
}
~~~
badge 角标，可以从collect 取map的key

#### 让message里可以打开指定文件
如文件加载列表

~~~
    /**
     * {@inheritDoc}
     */
    public function collect()
    {
        $files = $this->getIncludedFiles();

        $included = [];
        if(!$this->getXdebugLinkTemplate()){
            $this->setEditorLinkTemplate(app()->config->get('debugbar.editor'));
        }
        foreach ($files as $file) {

            if (Str::contains($file, $this->ignored)) {
                continue;
            }

            $included[] = [
                'message'   => "'" . $this->stripBasePath($file) . "',",
                // Use PHP syntax so we can copy-paste to compile config file.
                'is_string' => true,
                'xdebug_link'=>$this->getXdebugLink($file),
            ];
        }

        return [
            'messages' => $included,
            'count'    => count($included),
        ];
    }
~~~
message 里  包含 xdebug_link $this->getXdebugLink($file)


### 资源
扩展 JavascriptRenderer 类，
实现 renderHead 方法替换静态资源头部
然后 注册路由控制器来实现 资源地址动态返回js文件

