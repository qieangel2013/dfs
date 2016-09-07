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
class FileDistributedServer
{
    public static $instance;
    private $application;
    public $b_server_pool = array();
    public $client_pool = array();
    public $client_a;
    private $table;
    private $localip;
    private $connectioninfo;
    private $curpath;
    private $curtmp;
    private $filefd;
    private $filesizes;
    private $tmpdata;
    private $tmpdatas;
    private $oldpath;
    private $client_pool_ser = array();
    private $client_pool_ser_c = array();
    private $tmpdata_flag;
    private $wd = array();
    public function __construct()
    {
        require_once dirname(__DIR__) . '/config/config.php';
        require_once __DIR__ . '/FileDistributedClient.php';
        $this->table = new swoole_table(1024);
        $this->table->column('fileserverfd', swoole_table::TYPE_INT, 8);
        $this->table->create();
        $server = new swoole_server(ServerIp, ServerPort, SWOOLE_PROCESS, SWOOLE_SOCK_TCP);
        if (ServerLog) {
            $server->set(array(
                'worker_num' => 1,
                //'task_worker_num'         => 4,
                'dispatch_mode' => 4, //1: 轮循, 3: 争抢
                'daemonize' => true,
                'log_file' => ServerLog
            ));
        } else {
            $server->set(array(
                'worker_num' => 1,
                //'task_worker_num'         => 4,
                'dispatch_mode' => 4, //1: 轮循, 3: 争抢
                'daemonize' => true
            ));
        }
        $server->on('Start', array(
            &$this,
            'onStart'
        ));
        $server->on('WorkerStart', array(
            &$this,
            'onWorkerStart'
        ));
        $server->on('Connect', array(
            &$this,
            'onConnect'
        ));
        $server->on('Receive', array(
            &$this,
            'onReceive'
        ));
        //$server->on('Task',array(&$this , 'onTask'));
        //$server->on('Finish',array(&$this , 'onFinish'));
        $server->on('Close', array(
            &$this,
            'onClose'
        ));
        $server->on('ManagerStop', array(
            &$this,
            'onManagerStop'
        ));
        $server->on('WorkerError', array(
            &$this,
            'onWorkerError'
        ));
        $server->start();
    }
    
    public function onStart($serv)
    {
        $localinfo     = swoole_get_local_ip();
        $this->localip = current($localinfo);
        $localclient   = FileDistributedClient::getInstance()->addServerClient($this->localip);
        $this->table->set(ip2long($this->localip), array(
            'fileserverfd' => ip2long($this->localip)
        ));
        $this->b_server_pool[ip2long($this->localip)] = array(
            'fd' => $this->localip,
            'client' => $localclient
        );
        $listenpath                                   = $listenpathx = LISTENPATH;
        $this->filefd                                 = inotify_init();
        $wd                                           = inotify_add_watch($this->filefd, $listenpath, IN_CREATE | IN_MOVED_TO | IN_CLOSE_WRITE); //IN_MODIFY、IN_ALL_EVENTS、IN_CLOSE_WRITE
        $this->wd[$wd]                                = array(
            'wd' => $wd,
            'path' => $listenpath
        );
        $lisrdir                                      = FileDistributedClient::getInstance()->getlistDir($listenpath);
        if ($lisrdir) {
            foreach ($lisrdir as $k => $v) {
                $wd            = inotify_add_watch($this->filefd, $v, IN_CREATE | IN_MOVED_TO | IN_CLOSE_WRITE); //IN_MODIFY、IN_ALL_EVENTS、IN_CLOSE_WRITE
                $this->wd[$wd] = array(
                    'wd' => $wd,
                    'path' => $v,
                    'pre' => ''
                );
            }
        }
        swoole_event_add($this->filefd, function($fd) use ($localclient, $listenpath)
        {
            $events = inotify_read($fd);
            if ($events) {
                foreach ($events as $kk => $vv) {
                    if (isset($vv['name']) && $vv['mask'] != 256) {
                        if ($vv['mask'] == 1073742080) {
                            if (in_array($vv['wd'], array_keys($this->wd))) {
                                $listenpathx = substr($this->wd[$vv['wd']]['path'], 0, strripos($this->wd[$vv['wd']]['path'], "/") + 1);
                                $listenpathx .= '/' . $vv['name'];
                                if (is_dir($listenpathx) && is_readable($listenpathx)) {
                                } else {
                                    FileDistributedClient::getInstance()->mklistDir($listenpathx);
                                }
                                $wd            = inotify_add_watch($fd, $listenpathx, IN_CREATE | IN_MOVED_TO | IN_CLOSE_WRITE);
                                $this->wd[$wd] = array(
                                    'wd' => $wd,
                                    'path' => $listenpathx,
                                    'pre' => $vv['name']
                                );
                            } else {
                                $listenpath .= '/' . $vv['name'];
                                if (is_dir($listenpath) && is_readable($listenpath)) {
                                } else {
                                    FileDistributedClient::getInstance()->mklistDir($listenpath);
                                }
                                $wd            = inotify_add_watch($fd, $listenpath, IN_CREATE | IN_MOVED_TO | IN_CLOSE_WRITE);
                                $this->wd[$wd] = array(
                                    'wd' => $wd,
                                    'path' => $listenpath,
                                    'pre' => $vv['name']
                                );
                            }
                            
                            
                        } else {
                            $path_listen = $this->wd[$vv['wd']]['path'] . '/' . $vv['name'];
                            //$infofile    = pathinfo($path_listen);
                            $extends     = explode("/", $path_listen);
                            $vas         = count($extends) - 1;
                            if (empty($this->wd[$vv['wd']]['pre'])) {
                                $data = array(
                                    'type' => 'fileclient',
                                    'data' => array(
                                        //'path' => iconv('GB2312', 'UTF-8', $path_listen),
                                        'path' => rawurlencode($path_listen),
                                        'fileex' => rawurlencode($extends[$vas]),
                                        'pre' => ''
                                    )
                                );
                            } else {
                                $data = array(
                                    'type' => 'fileclient',
                                    'data' => array(
                                        //'path' => iconv('GB2312', 'UTF-8', $path_listen),
                                        'path' => rawurlencode($path_listen),
                                        'fileex' => rawurlencode($extends[$vas]),
                                        'pre' => rawurlencode($this->wd[$vv['wd']]['pre'])
                                    )
                                );
                            }
                            $localclient->send(FileDistributedClient::getInstance()->packmes($data));
                        }
                        
                    }
                }
                
            }
        });
        
    }
    public function onWorkerStart($serv, $worker_id)
    {
        //swoole_timer_tick(1000,array(&$this , 'onTimer'));
        $localinfo     = swoole_get_local_ip();
        $this->localip = current($localinfo);
        $serverlist    = FileDistributedClient::getInstance()->getserlist();
        $result_fd     = json_decode($serverlist, true);
        if (!empty($result_fd)) {
            foreach ($result_fd as $id => $fd) {
                if ($fd != $localinfo['eth0']) {
                    $client = FileDistributedClient::getInstance()->addServerClient($fd);
                    $this->table->set(ip2long($fd), array(
                        'fileserverfd' => ip2long($fd)
                    ));
                    $this->b_server_pool[ip2long($fd)] = array(
                        'fd' => $fd,
                        'client' => $client
                    );
                }
            }
        }
        FileDistributedClient::getInstance()->appendserlist($this->localip, ip2long($this->localip));
    }
    
    public function onConnect($serv, $fd)
    {
        $this->connectioninfo = $serv->connection_info($fd);
        $localinfo            = swoole_get_local_ip();
        $this->localip        = current($localinfo);
        if ($this->localip != $this->connectioninfo['remote_ip']) {
            $this->client_pool[ip2long($this->connectioninfo['remote_ip'])] = array(
                'fd' => $fd,
                'remote_ip' => $this->connectioninfo['remote_ip']
            );
        }
        
    }
    public function onReceive($serv, $fd, $from_id, $data)
    {
        $remote_info = FileDistributedClient::getInstance()->unpackmes($data);
        //判断是否为二进制图片流
        if (!is_array($remote_info)) {
            if (!$this->tmpdata_flag) {
                $tdf                   = array_shift($this->client_pool_ser_c);
                $this->curpath['path'] = LISTENPATH . '/' . rawurldecode($tdf['data']['path']);
                $this->filesizes       = $tdf['data']['filesize'];
                $this->tmpdata_flag    = 1;
            }
            if (isset($this->curpath['path'])) {
                if (is_dir(dirname($this->curpath['path'])) && is_readable(dirname($this->curpath['path']))) {
                } else {
                    FileDistributedClient::getInstance()->mklistDir(dirname($this->curpath['path']));
                }
                if ($this->oldpath != $this->curpath['path']) {
                    $this->tmpdata .= $remote_info;
                    if (strlen($this->tmpdata) > $this->filesizes) {
                        $this->tmpdatas = substr($this->tmpdata, $this->filesizes, strlen($this->tmpdata));
                        $this->tmpdata  = substr($this->tmpdata, 0, $this->filesizes);
                    }
                }
                if (strlen($this->tmpdata) == $this->filesizes) {
                    //$infofile = pathinfo($this->curpath['path']);
                    //if (in_array($infofile['extension'], array(
                    //    'txt',
                    //    'log'
                    //))) {
                    if (file_put_contents($this->curpath['path'], $this->tmpdata)) {
                        $this->tmpdata = '';
                        $this->oldpath = $this->curpath['path'];
                        
                        if (strlen($this->tmpdatas) > 0) {
                            $this->tmpdata  = $this->tmpdatas;
                            $this->tmpdatas = '';
                        }
                        $this->tmpdata_flag = 0;
                    }
                    /*} else {
                    if (in_array($infofile['extension'], array(
                    'jpg',
                    'png',
                    'jpeg',
                    'JPG',
                    'JPEG',
                    'PNG',
                    'bmp'
                    ))) {
                    if (file_put_contents($this->curpath['path'], $this->tmpdata)) {
                    $this->tmpdata = '';
                    $this->oldpath = $this->curpath['path'];
                    if (strlen($this->tmpdatas) > 0) {
                    $this->tmpdata  = $this->tmpdatas;
                    $this->tmpdatas = '';
                    
                    }
                    $this->tmpdata_flag = 0;
                    } //写入图片流
                    }
                    }*/
                }
            }
        } else {
            foreach ($remote_info as &$val) {
                if ($val['type'] == 'system' && $val['data']['code'] == 10001) {
                    if ($this->client_a != $val['data']['fd']) {
                        if (!$this->table->get(ip2long($val['data']['fd']))) {
                            $client                                           = FileDistributedClient::getInstance()->addServerClient($val['data']['fd']);
                            $this->b_server_pool[ip2long($val['data']['fd'])] = array(
                                'fd' => $val['data']['fd'],
                                'client' => $client
                            );
                            $this->client_a                                   = $val['data']['fd'];
                        } else {
                            if (FileDistributedClient::getInstance()->getkey()) {
                                $client                                           = FileDistributedClient::getInstance()->addServerClient($val['data']['fd']);
                                $this->b_server_pool[ip2long($val['data']['fd'])] = array(
                                    'fd' => $val['data']['fd'],
                                    'client' => $client
                                );
                                $this->client_a                                   = $val['data']['fd'];
                                if ($this->localip == FileDistributedClient::getInstance()->getkey()) {
                                    FileDistributedClient::getInstance()->delkey();
                                }
                            }
                        }
                        
                    }
                    if ($this->localip != $this->connectioninfo['remote_ip']) {
                        if (!in_array($this->connectioninfo['remote_ip'], $this->client_pool_ser)) {
                            $serv->send($fd, FileDistributedClient::getInstance()->packmes(array(
                                'type' => 'system',
                                'data' => array(
                                    'code' => 10002,
                                    'fd' => $this->localip
                                )
                            )));
                            array_push($this->client_pool_ser, $this->connectioninfo['remote_ip']);
                        }
                    }
                } else {
                    switch ($val['type']) {
                        case 'filesize':
                            if (isset($val['data']['path'])) {
                                $data_s          = array(
                                    'type' => 'filesizemes',
                                    'data' => array(
                                        'path' => $val['data']['path']
                                    )
                                );
                                $this->filesizes = $val['data']['filesize'];
                                array_push($this->client_pool_ser_c, $val);
                                $serv->send($fd, FileDistributedClient::getInstance()->packmes($data_s));
                            }
                            break;
                        case 'file':
                            if (isset($val['data']['path'])) {
                                if (!file_exists(LISTENPATH . '/' . rawurldecode($val['data']['path']))) {
                                    $this->curpath['path'] = LISTENPATH . '/' . rawurldecode($val['data']['path']);
                                    $data_s                = array(
                                        'type' => 'filemes',
                                        'data' => array(
                                            'path' => $val['data']['path']
                                        )
                                    );
                                    $serv->send($fd, FileDistributedClient::getInstance()->packmes($data_s));
                                }
                            }
                            break;
                        case 'asyncfileclient':
                            if (isset($val['data']['path'])) {
                                //$extend = explode(".", $val['data']['fileex']['basename']);
                                //$va     = count($extend) - 1;
                                // if (in_array($extend[$va], array(
                                //   'txt',
                                //    'log',
                                //   'jpg',
                                //    'png',
                                //    'jpeg',
                                //    'JPG',
                                //    'JPEG',
                                //    'PNG',
                                //    'bmp'
                                //))) {
                                if (empty($val['data']['pre'])) {
                                    $dataas = array(
                                        'type' => 'asyncfile',
                                        'data' => array(
                                            'path' => $val['data']['fileex']
                                        )
                                    );
                                } else {
                                    $dataas = array(
                                        'type' => 'asyncfile',
                                        'data' => array(
                                            'path' => rawurlencode(rawurldecode($val['data']['pre']) . '/' . rawurldecode($val['data']['fileex']))
                                        )
                                    );
                                }
                                
                                $serv->send($fd, FileDistributedClient::getInstance()->packmes($dataas));
                                
                                //}
                            }
                            break;
                        case 'fileclient':
                            //$infofile = pathinfo($val['data']['path']);
                            // if ($infofile['basename']) {
                            // $extend = explode(".", $infofile['basename']);
                            // $va     = count($extend) - 1;
                            // if (in_array($extend[$va], array(
                            //     'txt',
                            //     'log',
                            //      'jpg',
                            //      'png',
                            //     'jpeg',
                            //      'JPG',
                            //       'JPEG',
                            //       'PNG',
                            //      'bmp'
                            // ))) {
                            if (isset($this->curpath['path']) && $val['data']['path'] == $this->curpath['path']) {
                            } else {
                                if (empty($val['data']['pre'])) {
                                    $datas = array(
                                        'type' => 'file',
                                        'data' => array(
                                            'path' => $val['data']['fileex']
                                        )
                                    );
                                } else {
                                    $datas = array(
                                        'type' => 'file',
                                        'data' => array(
                                            'path' => rawurlencode(rawurldecode($val['data']['pre']) . '/' . rawurldecode($val['data']['fileex']))
                                        )
                                    );
                                }
                                
                                foreach ($this->b_server_pool as $k => $v) {
                                    if (file_exists(rawurldecode($val['data']['path']))) {
                                        if ($this->localip != $v['fd'] && $this->curpath['path'] != $val['data']['path']) {
                                            if ($v['client']->send(FileDistributedClient::getInstance()->packmes($datas))) {
                                            }
                                        }
                                        
                                    }
                                    
                                }
                            }
                            // }
                            //}
                            break;
                        default:
                            break;
                    }
                }
            }
            
            //print_r($remote_info);
            echo date('[ c ]') . str_replace("\n", "", var_export($remote_info, true));
        }
        
    }
    /**
     * 服务器断开连接
     * @param $cli
     */
    public function onClose($server, $fd, $from_id)
    {
        if (!empty($this->client_pool)) {
            foreach ($this->client_pool as $k => $v) {
                if ($v['fd'] == $fd) {
                    FileDistributedClient::getInstance()->removeuser($v['remote_ip'], 'FileDistributed');
                    print_r($v['remote_ip'] . " have closed\n");
                    unset($this->client_pool[$k]);
                }
            }
        } else {
            FileDistributedClient::getInstance()->removeuser($this->localip, 'FileDistributed');
            print_r($this->localip . " have closed\n");
        }
    }
    
    public function onManagerStop($serv)
    {
        if (empty($this->client_pool)) {
            FileDistributedClient::getInstance()->removeuser($this->localip, 'FileDistributed');
            print_r($this->localip . " have closed\n");
        }
        swoole_event_del($this->filefd);
    }
    
    public function onWorkerError($serv, $worker_id, $worker_pid, $exit_code)
    {
        if (empty($this->client_pool)) {
            FileDistributedClient::getInstance()->removeuser($this->localip, 'FileDistributed');
            print_r($this->localip . " have closed\n");
        }
    }
    
    public function onTask($serv, $task_id, $from_id, $data)
    {
        /* ob_start();
        $this->application->execute(array('swoole_taskclient','query'),$data);
        $result = ob_get_contents();
        ob_end_clean();*/
        $result = json_encode(array(
            'mes' => 12
        ));
        return $result;
    }
    public function onFinish($serv, $task_id, $data)
    {
        
    }
    public static function getInstance()
    {
        if (!(self::$instance instanceof FileDistributedServer)) {
            self::$instance = new FileDistributedServer;
        }
        return self::$instance;
    }
}
FileDistributedServer::getInstance();
