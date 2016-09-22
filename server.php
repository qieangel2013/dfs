<?php
/*
|---------------------------------------------------------------
|  Copyright (c) 2016
|---------------------------------------------------------------
| 作者：qieangel2013
| 联系：qieangel2013@gmail.com
| 版本：V1.0
| 日期：2016/7/25
|---------------------------------------------------------------
*/
// 检查扩展
if (!extension_loaded('inotify')) {
    exit("Please install inotify extension.\n");
}
if (!extension_loaded('redis')) {
    exit("Please install redis extension.\n");
}
if (!extension_loaded('swoole')) {
    exit("Please install swoole extension.\n");
}
//检查是否为cli模式
if (php_sapi_name() !== 'cli') {
    exit("Please use php cli mode.\n");
}
function server_call($cmd)
{
    
    foreach (glob(__DIR__ . '/server/FileDistributedServer.php') as $start_file) {
        exec($cmd . ' ' . $start_file);
    }
}
$ser_ser = $argv;
require_once __DIR__ . '/config/config.php';
if (!isset($ser_ser[1])) {
    exit("No argv.\n");
} else {
    switch ($ser_ser[1]) {
        case 'start':
            call_user_func('server_call', Bincmd);
            break;
        case 'stop':
            exec("ps -ef | grep -E '" . Bincmd . "' |grep -v 'grep'| awk '{print $2}'|xargs kill -9 > /dev/null 2>&1 &");
            echo "Kill all process success.\n";
            break;
        case 'restart':
            exec("ps -ef | grep -E '" . Bincmd . "' |grep -v 'grep'| awk '{print $2}'|xargs kill -9 > /dev/null 2>&1 &");
            echo "Kill all process success.\n";
            call_user_func('server_call', Bincmd);
            break;
        default:
            exit("Not support this argv.\n");
            break;
    }
}

?>
