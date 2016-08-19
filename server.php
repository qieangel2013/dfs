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
if(!extension_loaded('inotify'))
{
    exit("Please install inotify extension.\n");
}
if(!extension_loaded('redis'))
{
    exit("Please install redis extension.\n");
}
if(!extension_loaded('swoole'))
{
    exit("Please install swoole extension.\n");
}
//检查是否为cli模式
if(php_sapi_name() !== 'cli'){
    exit("Please use php cli mode.\n");
}

function server_call(swoole_process $worker)
{
	require_once __DIR__.'/server/FileDistributedServer.php';
	FileDistributedServer::getInstance();
}
$ser_ser=$argv;
$pid=-6;
if(!isset($ser_ser[1])){
     exit("No argv.\n");
 }else{
switch ($ser_ser[1]) {
    case 'start':
        $process = new swoole_process('server_call', true);
		$pid = $process->start();
		swoole_process::daemon(false);
		swoole_process::wait();
        break;
    case 'stop':
        exec("kill -9 ".$pid);
        echo "Kill all process success.\n"; 
        break;
     case 'restart':
        exec("kill -9 ".$pid);
        echo "Kill all process success.\n"; 
        $process = new swoole_process('server_call', true);
		$pid = $process->start();
		swoole_process::daemon(false);
		swoole_process::wait();
        break;
    default:
        exit("Not support this argv.\n");
        break;
    }
 }

?>
