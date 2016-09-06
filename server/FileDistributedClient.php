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
//namespace server;
//use server\lib\phpredis;
//use server\lib\dredis;
class FileDistributedClient
{
    public $application;
    public static $instance;
    public $c_client_pool = array();
    public $b_client_pool = array();
    private $table;
    private $cur_address;
    private $del_server = array();
    private $flagclient;
    public function __construct()
    {
        require_once __DIR__ . '/lib/phpredis.php';
        require_once __DIR__ . '/lib/dredis.php';
        $this->table = new swoole_table(1024);
        $this->table->column('fileclientfd', swoole_table::TYPE_INT, 8);
        $this->table->create();
    }
    
    public function addServerClient($address)
    {
        $client = new swoole_client(SWOOLE_TCP, SWOOLE_SOCK_ASYNC);
        $client->on('Connect', array(
            &$this,
            'onConnect'
        ));
        $client->on('Receive', array(
            &$this,
            'onReceive'
        ));
        $client->on('Close', array(
            &$this,
            'onClose'
        ));
        $client->on('Error', array(
            &$this,
            'onError'
        ));
        $client->connect($address, ServerPort);
        $this->cur_address = $address;
        $this->table->set(ip2long($address), array(
            'clientfd' => ip2long($address)
        ));
        $this->b_client_pool[ip2long($address)] = $client;
        return $client;
    }
    
    public function onConnect($serv)
    {
        $localinfo = swoole_get_local_ip();
        $serv->send($this->packmes(array(
            'type' => 'system',
            'data' => array(
                'code' => 10001,
                'status' => 1,
                'fd' => current($localinfo)
            )
        )));
    }
    
    public function onReceive($client, $data)
    {
        $remote_info = $this->unpackmes($data);
        if (is_array($remote_info)) {
            foreach ($remote_info as &$val) {
                switch ($val['type']) {
                    case 'filemes':
                        $strlendata = file_get_contents(LISTENPATH . '/' . rawurldecode($val['data']['path']));
                        $datas      = array(
                            'type' => 'filesize',
                            'data' => array(
                                'path' => $val['data']['path'],
                                'filesize' => strlen($strlendata)
                            )
                        );
                        $client->send($this->packmes($datas));
                        break;
                    case 'filesizemes':
                        if ($client->sendfile(LISTENPATH . '/' . rawurldecode($val['data']['path']))) {
                        }
                        break;
                    case 'system': //启动一个进程来处理已存在的图片
                        $listenpath       = LISTENPATH;
                        $this->flagclient = $flagclient = 0;
                        $process = new swoole_process(function($process) use ($listenpath, $flagclient)
                        {
                            if (!$flagclient) {
                                $filelist = $this->getlistDirFile($listenpath);
                                if (!empty($filelist)) {
                                    foreach ($filelist as &$v) {
                                        $process->write($v);
                                    }
                                    $flagclient = 1;
                                }
                            }
                            
                        });
                        $process->start();
                        swoole_event_add($process->pipe, function($pipe) use ($client, $listenpath, $process)
                        {
                            $data_l = $process->read();
                            $extends = explode("/", $data_l);
                            $vas     = count($extends) - 1;
                            $pre_dir = substr($data_l, 0, strripos($data_l, "/") + 1);
                            if ($pre_dir == $listenpath) {
                                $data = array(
                                    'type' => 'asyncfileclient',
                                    'data' => array(
                                        //'path' => iconv('GB2312', 'UTF-8', $data_l),
                                        'path' => rawurlencode($data_l),
                                        'fileex' => rawurlencode($extends[$vas]),
                                        'pre' => ''
                                    )
                                );
                            } else {
                                $data = array(
                                    'type' => 'asyncfileclient',
                                    'data' => array(
                                        'path' => rawurlencode($data_l),
                                        'fileex' => rawurlencode($extends[$vas]),
                                        'pre' => rawurlencode(substr($pre_dir, strlen($listenpath) + 1, strlen($pre_dir)))
                                    )
                                );
                            }
                            
                            
                            $client->send($this->packmes($data));
                        });
                        break;
                    case 'asyncfile':
                        $data_sa = array(
                            'type' => 'file',
                            'data' => array(
                                'path' => $val['data']['path']
                            )
                        );
                        
                        $client->send($this->packmes($data_sa));
                        break;
                    default:
                        break;
                        
                        
                }
            }
            
            
        } else {
            echo date('[ c ]') . '参数不对 \r\n';
        }
        
        
        
    }
    public function onTask($serv, $task_id, $from_id, $data)
    {
        $fd       = json_decode($data, true);
        $tmp_data = $fd['data'];
        $this->application->execute(array(
            'swoole_task',
            'demcode'
        ), $tmp_data);
        $serv->send($fd['fd'], "Data in Task {$task_id}");
        return 'ok';
    }
    public function onFinish($serv, $task_id, $data)
    {
        echo "Task {$task_id} finish\n";
        echo "Result: {$data}\n";
    }
    /**
     * 服务器断开连接
     * @param $cli
     */
    public function onClose($client)
    {
        unset($client);
    }
    /**
     * 服务器连接失败
     * @param $cli
     */
    public function onError($client)
    {
        $this->removeuser($this->cur_address);
        $this->del_server[ip2long($this->cur_address)] = $this->cur_address;
        $this->table->del(ip2long($this->cur_address));
        $this->setkey($this->cur_address);
        unset($this->b_client_pool[$this->cur_address]);
        unset($client);
    }
    //获取分布式服务器列表
    public function getserlist($keyname = 'FileDistributed')
    {
        ob_start();
        dredis::getInstance()->getfd($keyname);
        $result = ob_get_contents();
        ob_end_clean();
        return $result;
    }
    //添加到分布式服务器列表
    public function appendserlist($data, $score, $keyname = 'FileDistributed')
    {
        dredis::getInstance()->savefd($data, $score, $keyname);
    }
    //从分布式服务器列表删除
    public function removeuser($data, $keyname = 'FileDistributed')
    {
        dredis::getInstance()->removefd($data, $keyname);
    }
    //设置错误服务器
    public function setkey($data, $keyname = 'errserfile')
    {
        return dredis::getInstance()->setkey($data, $keyname);
    }
    //获取错误服务器
    public function getkey($keyname = 'errserfile')
    {
        return dredis::getInstance()->getkey($keyname);
    }
    //删除错误服务器
    public function delkey($keyname = 'errserfile')
    {
        return dredis::getInstance()->delkey($keyname);
    }
    //获取目录
    public function getlistDir($dir)
    {
        $dir .= substr($dir, -1) == '/' ? '' : '/';
        $dirInfo = array();
        foreach (glob($dir . '*', GLOB_ONLYDIR) as $v) {
            $dirInfo[] = $v;
            if (is_dir($v)) {
                $dirInfo = array_merge($dirInfo, $this->getlistDir($v));
            }
        }
        return $dirInfo;
    }
    //解包装数据
    public function unpackmes($data, $format = '\r\n\r\n')
    {
        $pos = strpos($data, $format);
        if ($pos !== false) {
            $tmpdata = explode($format, $data);
            foreach ($tmpdata as $k => $v) {
                if (empty($v)) {
                    unset($tmpdata[$k]);
                } else {
                    $tmpdata[$k] = json_decode($v, true);
                }
            }
            return $tmpdata;
        } else {
            return $data;
        }
    }
    //包装数据
    public function packmes($data, $format = '\r\n\r\n')
    {
        return json_encode($data, true) . $format;
    }
    //获取目录文件
    public function getlistDirFile($dir)
    {
        $dir .= substr($dir, -1) == '/' ? '' : '/';
        $dirInfo = array();
        foreach (glob($dir . '*') as $v) {
            if (!is_dir($v)) {
                $dirInfo[] = $v;
            }
            if (is_dir($v)) {
                $dirInfo = array_merge($dirInfo, $this->getlistDirFile($v));
            }
        }
        return $dirInfo;
    }
    //创建目录
    public function mklistDir($dir)
    {
        if (is_dir($dir) && is_readable($dir)) {
            $this->mklistDir(dirname($dir));
        } else {
            mkdir($dir, 0777, true);
        }
    }
    //定时获取移除的服务器
    public function geterrlist($data)
    {
        if (!empty($data)) {
            $datas = json_decode($data, true);
            if (empty($this->del_server)) {
                return false;
            } else {
                foreach ($datas as $k => $v) {
                    if ($this->del_server[$k] == $v) {
                        return $v;
                    }
                }
                return false;
            }
        }
        return false;
    }
    //单例
    public static function getInstance()
    {
        if (!(self::$instance instanceof FileDistributedClient)) {
            self::$instance = new FileDistributedClient;
        }
        return self::$instance;
    }
}
