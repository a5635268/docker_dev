# 多源复制（基于GTID）

[toc]

## 概述

多主一从，也称为多源复制，数据流向：

*   主库1 -> 从库s
*   主库2 -> 从库s
*   主库n -> 从库s

从库能够同时从多个源接收事务。多源复制可用于将多个服务器备份到单个服务器，合并表分片，以及将来自多个服务器的数据合并到单个服务器。

**通过 GTID 保证了每个在主库上提交的事务在集群中有一个唯一的ID**。这种方式强化了数据库的**主备一致性，故障恢复以及容错能力。**  所以基于GTID的复制方式是推荐的主从复制方式。本文是基于GTID做的多源复制。

## 应用场景

*   数据汇总，可将多个主数据库同步汇总到一个从数据库中，方便夸库查询与数据统计分析。
*   读写分离，从库只用于查询，提高数据库整体性能。
*   数据备份，对多个库数据进行热备。

## 部署环境

通过docker部署以下环境

    msyql版本： 5.7.26-log
    主1： 192.168.16.2 
    主2： 192.168.16.3
    从1： 192.168.16.4

## 配置 **/etc/my.cnf**

分别在主从库的 **\[mysqld\]** 段增加以下配置

### master_1

~~~
server_id = 2
log-bin = master1-bin
gtid-mode=on
enforce-gtid-consistency=true
~~~

### master_2

~~~
server_id = 3
log-bin = master2-bin
gtid-mode=on
enforce-gtid-consistency=true
~~~

### slave

~~~
server_id    = 4
relay-log    = relay-bin
master_info_repository = TABLE
relay_log_info_repository = TABLE
log-slave-updates=true
gtid-mode=on
enforce-gtid-consistency=true
sync-master-info=1
slave-parallel-workers=2
binlog-checksum=CRC32
master-verify-checksum=1
slave-sql-verify-checksum=1
binlog-rows-query-log_events=1
~~~


## 创建测试数据

![](https://i.vgy.me/C8U4sq.png)

```
m1 -> m1_table
m2 -> m2_table
```

##  创建复制账号

~~~
# 在主1执行
grant replication slave on *.* to 'rep1'@'192.168.16.4' identified by 'rep1';
flush privileges;

# 在主2执行
grant replication slave on *.* to 'rep2'@'192.168.16.4' identified by 'rep2';
flush privileges;
~~~

## 初始化从库

执行 `reset master` 后同步主库数据

>[warning] 注意！ 库名和表名都要一样。

![](https://i.vgy.me/LOYcE5.png)


## 登录Slave创建复制通道

~~~
mysql> CHANGE MASTER TO MASTER_HOST='192.168.16.2 ', MASTER_USER='rep1', MASTER_PORT=3306, MASTER_PASSWORD='rep1', MASTER_AUTO_POSITION = 1 FOR CHANNEL 'Master_1';
 
mysql> CHANGE MASTER TO MASTER_HOST='192.168.16.3', MASTER_USER='rep2', MASTER_PORT=3306, MASTER_PASSWORD='rep2', MASTER_AUTO_POSITION = 1 FOR CHANNEL 'Master_2';
~~~

## 开启复制通道

~~~
# 默认开启所有的复制通道
mysql> start slave;

# 也可以指定复制通道开启
mysql> start slave for CHANNEL  'Master_1'; 
mysql> start slave for CHANNEL  'Master_2';
~~~

## 查看从库复制状态

~~~
mysql> SHOW SLAVE STATUS\G

...
Slave_IO_Running: Yes
Slave_SQL_Running: Yes
...

# 也可以指定复制通道查看
mysql> SHOW SLAVE STATUS FOR CHANNEL 'Master_1'/G;
~~~

## 配置多库到单库

**配置slave的my.conf**

默认情况下，多源复制都是库表一一对应的，就如上文一样。但很多情况下，我们需要配置多库到单库。此时就可以在slave上进行如下配置：

~~~
# replicate-rewrite-db 多库同步到单库，库名重写，其他的replicate-*会在replicate-rewrite-db评估后执行，多个映射的话，配置文件中包含多行即可。如果同时有多个replicate*过滤器，先评估数据库级别的、然后表级别的；先评估do，后评估ignore（也就是在白名单或者不在黑名单的模式）。比如，主库多个分库合并到从库一个库
replicate-rewrite-db=ta_base->ta
replicate-rewrite-db=ta_1->ta
replicate-rewrite-db=ta_2->ta
~~~

## 验证

略；




