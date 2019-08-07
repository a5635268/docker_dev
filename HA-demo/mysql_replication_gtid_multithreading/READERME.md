# 基于GTID的主从复制

## 什么是GTID Replication

从 MySQL 5.6.5 开始新增了一种基于 GTID 的复制方式。**通过 GTID 保证了每个在主库上提交的事务在集群中有一个唯一的ID**。这种方式强化了数据库的**主备一致性，故障恢复以及容错能力。** （推荐）

在原来基于二进制日志的复制中，从库需要告知主库要从哪个偏移量进行增量同步，如果指定错误会造成数据的遗漏，从而造成数据的不一致。借助GTID，在发生主备切换的情况下，MySQL的其它从库可以自动在新主库上找到正确的复制位置，这大大简化了复杂复制拓扑下集群的维护，也减少了人为设置复制位置发生误操作的风险。另外，基于GTID的复制可以忽略已经执行过的事务，减少了数据发生不一致的风险。

**什么是GTID**

GTID (Global Transaction ID) 是对于一个已提交事务的编号，并且是一个全局唯一的编号。 GTID 实际上 是由 UUID+TID 组成的。其中 UUID 是一个 MySQL 实例的唯一标识。TID 代表了该实例上已经提交的事务数量，并且随着事务提交单调递增。下面是一个GTID的具体形式：

    3E11FA47-71CA-11E1-9E33-C80AA9429562:23

一组连续的事务可以用`-`连接的事务序号范围表示。例如：

    e6954592-8dba-11e6-af0e-fa163e1cf111:1-5

GTID 集合可以包含来自多个 MySQL 实例的事务，它们之间用逗号分隔。如果来自同一 MySQL 实例的事务序号有多个范围区间，各组范围之间用冒号分隔。例如：

    e6954592-8dba-11e6-af0e-fa163e1cf111:1-5:11-18,  
    e6954592-8dba-11e6-af0e-fa163e1cf3f2:1-27

可以使用`SHOW MASTER STATUS`实时看当前的事务执行数。

## 配置步骤

MySQL 版本都为 5.7.26。

master: 172.31.0.2
slave: 172.31.0.3

**GTID主从复制的配置思路：**

![](https://i.vgy.me/5bRiko.png)

1\.  **修改MySQL主配置文件**

配置 MySQL 基于GTID的复制

在前文**基于日志点的主从复制**的配置文件`[mysqld]`段中添加以下内容即可：

~~~
gtid-mode = ON
enforce-gtid-consistency = ON
log-slave-updates = ON
~~~

在 MySQL 5.6 版本时，基于 GTID 的复制中`log-slave-updates`选项是必须的。但是其增大了从服务器的IO负载, 而在 MySQL 5.7 中该选项已经不是必须项。

2\. **创建具有复制权限的用户**

~~~
mysql> grant replication slave on *.* to 'repl'@'172.31.0.%' identified by '123456';
mysql> flush privileges;
~~~

>[danger] 基于 GTID 的复制会自动地将没有在从库执行过的事务重放，所以不要在其它从库上建立相同的账号。 如果建立了相同的账户，有可能造成复制链路的错误。

3\. **查看主库与从库的GTID是否开启**

```
# 如下图就代表开启了
mysql> show variables like "%gtid%";
+----------------------------------+-----------+
| Variable_name                    | Value     |
+----------------------------------+-----------+
| binlog_gtid_simple_recovery      | ON        |
| enforce_gtid_consistency         | ON        |
| gtid_executed_compression_period | 1000      |
| gtid_mode                        | ON        |
| gtid_next                        | AUTOMATIC |
| gtid_owned                       |           |
| gtid_purged                      |           |
| session_track_gtids              | OFF       |
+----------------------------------+-----------+
```

4\. **查看服务器server\_uuid**

```
mysql> show global variables like '%uuid%';
+---------------+--------------------------------------+
| Variable_name | Value                                |
+---------------+--------------------------------------+
| server_uuid   | 2879c33d-9707-11e9-9d60-0242ac1f0003 |
+---------------+--------------------------------------+
```

5\. **查看主服务器状态**

```
mysql> show master status;
```

6\. **从库连接至主库，开启复制**

~~~
mysql> CHANGE MASTER TO MASTER_HOST='172.31.0.2',MASTER_USER='repl',MASTER_PASSWORD='123456',MASTER_AUTO_POSITION=1;
mysql> START SLAVE;
~~~

7\. **启动成功后查看SLAVE的状态**
~~~
mysql> SHOW SLAVE STATUS\G

...
Slave_IO_Running: Yes
Slave_SQL_Running: Yes
...
~~~

8\. **验证主从是否一致**

略
