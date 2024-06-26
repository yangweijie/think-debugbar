<?php

namespace think\debugbar;

use think\App;

class Install
{
	public static function config($event){
		$io = $event->getIO();
		$io->write('in install');
		$io->write('version:'.App::VERSION);
		$app = new App();
		$io->write('config_path:'.$app->getConfigPath());
		$io->write(__DIR__.'/../config/debugbar.php');
		// 兼容tp 5.1
		if(version_compare(App::VERSION, '6.0.0')== -1){
			$config_path = $app->getConfigPath();
			$target_file = "{$config_path}/debugbar.php";
			$io->write('in copy');
			if(!file_exists($target_file)){
				copy(__DIR__.'/../config/debugbar.php', $target_file);
			}
		}
	}

    public static function patch(){
        $app = new App();
        $patch_file = $app->getRootPath().'vendor/topthink/think-orm/src/db/PDOConnection.php';
        if(file_exists($patch_file)){
            if(stripos(file_get_contents($patch_file), 'protected $queryStartTime;') !== false){
                file_put_contents($patch_file, str_replace('protected $queryStartTime;', 'public $queryStartTime;', file_get_contents($patch_file)));
            }
        }
    }

}