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
        $serv->send(json_encode(array(
            'type' => 'system',
            'data' => array(
                'code' => 10001,
                'status' => 1,
                'fd' => $localinfo['eth0']
            )
        )));
    }
    
    public function onReceive($client, $data)
    {
        $remote_info = json_decode($data, true);
        if ($remote_info['type'] == 'filemes') {
            $strlendata = file_get_contents($remote_info['data']['path']);
            $datas      = array(
                'type' => 'filesize',
                'data' => array(
                    'path' => $remote_info['data']['path'],
                    'filesize' => strlen($strlendata)
                )
            );
            $client->send(json_encode($datas, true));
        } else if ($remote_info['type'] == 'filesizemes') {
            if ($client->sendfile($remote_info['data']['path'])) {
            }
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
