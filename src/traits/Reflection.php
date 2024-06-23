<?php

namespace think\debugbar\traits;

class Reflection
{
    public static function classProperties($class, $property){
        $reflectProperty = new \ReflectionProperty($class, $property);
        $reflectProperty->setAccessible(true);
        return $reflectProperty->$property?? null;
    }
}