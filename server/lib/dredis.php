<?php
/*
|---------------------------------------------------------------
|  Copyright (c) 2016
|---------------------------------------------------------------
| 作者：qieangel2013
| 联系：qieangel2013@gmail.com
| 版本：V1.0
| 日期：2016/6/25
|---------------------------------------------------------------
*/
//namespace server\lib;
class dredis {
	public static $instance;
	public static $redis_con;
	public function __construct() {
        $dredis_config['server']=redis_server;
        $dredis_config['port']=redis_port;
		self::$redis_con=new phpredis($dredis_config);
	}
	public function getname($userid){
		$where=array('id' =>$userid);
		$result =$this->user->where($where)->select();
	}
	public static function savefd($fd,$score,$kname='fd'){
		self::$redis_con->setAdd($kname,$fd,1,$score);
	}
	public static function getfd($kname='fd'){
		$result=self::$redis_con->setRange($kname,0,-1);
		echo json_encode($result);
	}
	public static function removefd($fd,$kname='fd'){
		self::$redis_con->setMove($kname,$fd,1);
	}
	public static function setkey($data,$kname='fd'){
		self::$redis_con->set($kname,$data);
	}
	public static function getkey($kname='fd'){
		return self::$redis_con->get($kname);
	}
	public static function delkey($kname='fd'){
		return self::$redis_con->delete($kname);
	}
	public static function getInstance() {
        if (!(self::$instance instanceof dredis)) {
            self::$instance = new dredis;
        }
        return self::$instance;
    }


}
