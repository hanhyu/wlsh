<?php
namespace Wlsh\S;
require __DIR__ . "/DI.php";
class WsServer {
    private $http;
    private $tcp;

    public function __construct() {
        $this->http = new \swoole_websocket_server('0.0.0.0', 9501);
        $this->http->set([
            'worker_num' => 8,
            'daemonize' => true,
            'max_request' => 100000,
            'dispatch_mode' => 2,
            'task_worker_num' => 8,
            'log_file' => __DIR__ . '/../L/swoole.log',
            'heartbeat_check_interval' => 660,
            'heartbeat_idle_time' => 1200,
        ]);
        $this->http->on('start', [$this, 'onStart']);
        $this->http->on('managerStart', [$this, 'onManagerStart']);
        $this->http->on('workerStart', [$this, 'onWorkerStart']);
        $this->http->on('open', [$this, 'onOpen']);
        $this->http->on('message', [$this, 'onMessage']);
        $this->http->on('request', [$this, 'onRequest']);
        $this->http->on('task', [$this, 'onTask']);
        $this->http->on('close', [$this, 'onClose']);
        $this->http->on('finish', [$this, 'onFinish']);
        $this->http->start();
    }

    public function onStart($http) {
        echo "Swoole http server is started at http://127.0.0.1:9501\n";
        $myfile = fopen(__DIR__ . '/../L/swoolePid.log', 'w');
        fwrite($myfile, json_encode(['masterPid'=>$http->master_pid]));
        fclose($myfile);
    }

    public function onManagerStart($http) {

    }

    public function onWorkerStart($http, $worker_id) {
        $redis = new \redis();
        $redis->connect('127.0.0.1', 6379);
        DI::setInstance('redis', $redis);

        if(!$http->taskworker){
            require __DIR__ . "/../../vendor/autoload.php";
        }
        if($worker_id == 0) {
            $this->sReload();   //监听需要修改的文件，修改后自动平滑重启worker进程。
        }

    }

    public function onOpen($http, $request) {
        echo '==============='. date("Y-m-d H:i:s", time()). '欢迎' . $request->fd . '进入==============' . PHP_EOL;

    }

    public function onMessage($http, $frame) {
        $data = json_decode( $frame->data, true );
        var_dump($data);
        if( $http->exist( $frame->fd) ) $http->push( $frame->fd, json_encode($data) );
    }

    public function onRequest($request, $response) {


        $path_info = explode('/',$request->server['path_info']);

        if( isset($path_info[1]) && !empty($path_info[1])) {  // ctrl
            $ctrl = "Wlsh\W\\" . ucfirst($path_info[1]);
        } else {
            $ctrl = "Wlsh\W\Index";
        }
        if( isset($path_info[2] ) ) {  // method
            $action = lcfirst($path_info[2]);
        } else {
            $action = 'index';
        }
        $result = "Ctrl not found";
        if( class_exists( $ctrl ) )
        {
            $class = new $ctrl();

            $result = "Action not found";

            if( method_exists($class, $action) )
            {
                $result = $class->$action($request);
            }
        }

        //把不依赖某种业务逻辑的返回数据，让异步task执行。
        //$this->http->task("Async");
        $response->header("Content-Type", "text/plain;charset=UTF-8");
        $response->header("Connection", "keep-alive");

        $response->end($result);
    }

    public function onTask($http, $task_id, $reactor_id, $data) {
        echo "New AsyncTask[id=$task_id]\n";
        //$http->finish("$data -> OK");
        return;
    }

    public function onClose($http, $fd) {
        //echo "client-{$fd} is closed" . PHP_EOL;
        //echo '==============='. date("Y-m-d H:i:s", time()). '欢送' . $fd . '离开==============' . PHP_EOL;
    }

    public function onFinish($http, $task_id, $data) {
        //echo "AsyncTask[$task_id] finished: {$data}\n";
    }

    private function sReload() {
        $fd1 = inotify_init();
        $file = __DIR__ . "/../W/Login.php";
        inotify_add_watch($fd1, $file, IN_MODIFY );

        $myfile = fopen(__DIR__ . "/../L/swoolePid.log", 'r');
        $pid = fread($myfile, 20);
        fclose($myfile);
        $pid = json_decode($pid, true)['masterPid'];

        swoole_event_add($fd1, function () use ($fd1, $pid){
            $events = inotify_read($fd1);
            if ($events) {
                exec("kill -USR1 {$pid}");
            }
        });

    }

}


new WsServer();