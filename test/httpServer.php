<?php
/**
 * Created by PhpStorm.
 * User: hanhui
 * Date: 17-11-27
 * Time: 上午11:46
 */
use  Swoole\Redis\Server;

$count = 0;
$pool = new SplQueue();
$http = new swoole_http_server("127.0.0.1", 9501);
$http->set(['worker_num' => 8]);
//require __DIR__."/../../redis-async/src/Swoole/Async/RedisClient.php";
//$redis = new Swoole\Async\RedisClient('127.0.0.1');

$http->on('request', function ($request, $response) use (&$count, $pool){
    if (count($pool) == 0) {
        $redis = new Swoole\Coroutine\Redis();
        $res = $redis->connect('127.0.0.1', 6379);
        if ($res == false) {
            $response->end("redis connect fail!");
            return;
        }
        $pool->push($redis);
        $count++;
    }
    $redis = $pool->pop();
    $response->end("<h1>Hello Swoole. value=" . $redis->get('key') . "</h1>");
    $pool->push($redis);
});

/*
$http->on('request', function ($request, $response) use ($redis) {
    $redis->get('key', function($result) use($response) {
        $response->end("<h1>Hello Swoole. value=".$result."</h1>");
    });
});
*/


$http->start();