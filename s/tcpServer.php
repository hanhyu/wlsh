<?php
$server = new \swoole_server("127.0.0.1", 9504);

$server->on('connect', function ($server, $fd){
    echo "connection open: {$fd} \n";
});

$server->on('receive', function ($server, $fd, $reactor_id, $data){
    $server->send($fd, "swoole: {$data}");
    $server->close();
});

$server->on('close', function ($server, $fd){
    echo "connection close: {$fd} \n";
});

$server->start();