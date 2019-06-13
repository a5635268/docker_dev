**个人技术栈docker一键安装程序**

## todo list

- [x] 新增docker工具`.bashrc_docker`
- [x] 支持**多版本PHP**随意切换（PHP5.6、PHP7.2)
- [x] PHP7.2版本支持swoole与yaconf等扩展
- [x] nginx配置挂载，绑定任意**多个域名**,支持**HTTPS和HTTP/2**
- [x] mysql配置和数据目录挂载
- [x] redis数据目录挂载
- [x] phpredisadmin
- [x] phpmyadmin
- [x] seaslog配置文件挂载
- [ ] gitlab
- [ ] rabbitMq
- [ ] memcache
- [ ] seaslog,swoole,clickhouse日志架构
- [ ] elasticsearsh搜索架构
- [ ] python
- [ ] go
- [ ] java

不断迭代中...

## 快速使用

1. 本地安装`git`、`docker`和`docker-compose`。

2. `clone`项目：
    ```
    $ git clone https://github.com/a5635268/docker_dev.git
    mv .env.example .env
    ```

3. 如果不是`root`用户，还需将当前用户加入`docker`用户组：
    ```
    $ sudo gpasswd -a ${USER} docker
    ```
4. 启动：
    ```
    $ cd dnmp
    # 指定具体的服务名启动
    $ docker-compose up -d php72 mysql
    ```

## 快捷命令

    wget -P ~ https://github.com/a5635268/docker_dev/.bashrc_docker;
    echo "[ -f ~/.bashrc_docker ] && . ~/.bashrc_docker" >> ~/.bashrc; source ~/.bashrc

    # 进入容器
    docker-enter nginx

    # 运行容器上命令
    docker-enter nginx -- uptime
    docker-enter nginx -- df -h

    # 信息查看
    docker-ip nginx
    docker-pid nginx

## PHP版本切换和共存

```
# 两个版本的php端口分别是9000和9001
$ docker-compose up -d php72 php56
```

配置nginx

```
fastcgi_pass   php72:9000;
fastcgi_pass   php56:9001;
```

## HTTPS和HTTP/2

    配置示例查看 conf\nginx\conf.d\site2.conf


## 使用Log

1. Log文件生成的位置依赖于conf下各log配置的值。
2. Nginx日志要在nginx配置中打开
3. seaslog日志要配置到挂载目录


### MySQL日志
因为MySQL容器中的MySQL使用的是`mysql`用户启动，它无法自行在`/var/log`下的增加日志文件。所以，我们把MySQL的日志放在与data一样的目录，即项目的`mysql`目录下，对应容器中的`/var/lib/mysql/`目录。
```bash
slow-query-log-file     = /var/lib/mysql/mysql.slow.log
log-error               = /var/lib/mysql/mysql.error.log
```
以上是mysql.conf中的日志文件的配置。

## 使用composer
dnmp默认已经在容器中安装了composer，使用时先进入容器：
```
$ docker-enter php72
```

## phpmyadmin和phpredisadmin
本项目默认在`docker-compose.yml`中开启了用于MySQL在线管理的*phpMyAdmin*，以及用于redis在线管理的*phpRedisAdmin*，可以根据需要修改或删除。

### phpMyAdmin
phpMyAdmin容器映射到主机的端口地址是：`8080`

MySQL连接信息：
- host：(本项目的MySQL容器网络)
- port：`3306`
- username：（手动在phpmyadmin界面输入）
- password：（手动在phpmyadmin界面输入）

### phpRedisAdmin

phpRedisAdmin容器映射到主机的端口地址是：`8081`

Redis连接信息如下：
- host: (本项目的Redis容器网络)
- port: `6379`


## 使用XDEBUG调试

要使用xdebug调试，在php.ini文件最后加上这几行：

```
[XDebug]
xdebug.remote_enable = 1
xdebug.remote_handler = "dbgp"
xdebug.remote_host = "172.17.0.1"
xdebug.remote_port = 9000
xdebug.remote_log = "/var/log/dnmp/php.xdebug.log"
```

然后重启PHP容器。
注意XDEBUG只在php5.6容器中安装。因为与php7.2的swoole扩展冲突。

## 错误解决

### 解决nginx启动报：error creating overlay mount to /var/lib/docker/overlay2

https://www.jianshu.com/p/66f5f1e2bfa8

### 访问挂载目录Permission Denied
https://www.jianshu.com/p/1ed499037b02

