#分布式文件服务器
    基于swoole和inotify实现分布式文件服务器（目前只支持指定目录，子目录随后会加上）
#服务启动
    需要php以cli模式运行/server.php
      php server.php start
      php server.php stop
      php server.php restart
#测试使用
    需要php以cli模式运行/test/test.php
#使用介绍
    安装swoole扩展和inotify扩展
    然后需要配置
    修改/config/config.php文件相应的配置
    然后启动服务，目前支持文件同步
