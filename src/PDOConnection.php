<?php

namespace think\debugbar;

class PDOConnection {

    public function __construct($instance)
    {
        $this->instance = $instance;
    }

    public function __call($method, $args){
        if(!method_exists($this, $method) && property_exists($this->instance, $method)){
            $reflection = new \ReflectionMethod($this->instance, $method);
            return $this->$method;
        }
    }
}