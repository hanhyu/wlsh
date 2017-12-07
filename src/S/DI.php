<?php
/**
 * Created by PhpStorm.
 * User: hanhui
 * Date: 17-12-7
 * Time: 下午3:50
 */
namespace Wlsh\S;

class DI
{
    static protected $arr;

    public function setInstance(string $name, \Redis $obj)
    {
       DI::$arr[$name] = $obj;
    }

    public function getInstance(string $name): \Redis {
       return DI::$arr[$name];
    }
}