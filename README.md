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
            )
        );
    }
}
~~~
