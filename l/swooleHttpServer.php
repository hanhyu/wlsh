<?php
$http = new swoole_http_server("0.0.0.0", 9501);
$http->set([
    'worker_num' => 2,
    'daemonize' => true,
    'max_request' => 10000,
    'dispatch_mode' => 2,
    'task_worker_num' => 4,
    'log_file' => '/home/wwwroot/log/swoole.log',
    'heartbeat_check_interval' => 660,
    'heartbeat_idle_time' => 1200
]);
$http->on("start", function ($server) use ($http) {
    echo "Swoole http server is started at http://127.0.0.1:9501\n";
    $myfile = fopen('/home/wwwroot/log/swoolePid.log', 'w');
    fwrite($myfile, 'masterPid:'.$http->master_pid. '   managerPid:'.$http->manager_pid . PHP_EOL);
    fclose($myfile);
});

$http->on('WorkerStart' , function() use ($http) {
    var_dump(spl_autoload_register(function($class){
        $baseClasspath = \str_replace('\\', DIRECTORY_SEPARATOR , $class) . '.php';

        $classpath = __DIR__ . '/' . $baseClasspath;
        if (is_file($classpath)) {
            require "{$classpath}";
            return;
        }
    }));

    $http->tick(1000, function (){
        //获取页面最后修改的时间
        echo 'lastmod:' . getlastmod() ;
        //取得文件修改时间
        echo 'filemtime:' . filemtime(__FILE__). PHP_EOL;
    });


});

$http->on("request", function ($request, $response) use ($http) {

    $path_info = explode('/',$request->server['path_info']);

    if( isset($path_info[1]) && !empty($path_info[1])) {  // ctrl
        $ctrl = 'api\\' . $path_info[1];
    } else {
        $ctrl = 'api\\Index';
    }
    if( isset($path_info[2] ) ) {  // method
        $action = $path_info[2];
    } else {
        $action = 'index';
    }

    $result = "Ctrl not found";
    if( class_exists($ctrl) )
    {
        $class = new $ctrl();

        $result = "Action not found";

        if( method_exists($class, $action) )
        {
            $result = $class->$action($request);
        }
    }
    //把不依赖某种业务逻辑的返回数据，让异步task执行。
    $http->task("Async");
    $response->header("Content-Type", "text/plain");
    $response->end($result);



});

$http->on('task', function ($http, $task_id, $reactor_id, $data) {
    echo "New AsyncTask[id=$task_id]\n";
    $http->finish("$data -> OK");
});
$http->on('finish', function ($http, $task_id, $data) {
    echo "AsyncTask[$task_id] finished: {$data}\n";
});

$http->start();