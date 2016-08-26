#分布式文件服务器
[![Build Status](https://img.shields.io/wercker/ci/wercker/docs.svg)](https://packagist.org/packages/qieangel2013/dfs)
![Supported PHP versions: >=5.5](https://img.shields.io/badge/php-%3E%3D5.5-blue.svg)
![Supported SWOOLE versions: >=1.8.0](https://img.shields.io/badge/swoole-%3E%3D1.8.0-orange.svg)
![License](https://img.shields.io/badge/license-Apache%202-yellow.svg)
###核心特性
    1.基于swoole和inotify实现分布式文件服务
    2.文件实时同步服务
    3.文件实时监控及监控子目录服务
    4.自动断线重连服务
###服务启动
    需要php以cli模式运行/server.php
      php server.php start
      php server.php stop
      php server.php restart
###composer 安装
	{
    		"require": {
        		"qieangel2013/dfs": "0.1.0"
		 }
	}
###使用介绍
    安装swoole扩展和inotify扩展
    修改/config/config.php文件相应的配置
    交流群：337937322
###License
    Apache License Version 2.0 see http://www.apache.org/licenses/LICENSE-2.0.html
#如果你对我的辛勤劳动给予肯定，请给我捐赠，你的捐赠是我最大的动力
![](https://github.com/qieangel2013/zys/blob/master/public/images/ali.png)
