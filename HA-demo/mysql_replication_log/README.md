# 基于日志的主从复制

[toc]

## 使用主从的好处

1. 采用主从服务器这种架构，稳定性得以提升。如果主服务器发生故障，我们可以使用从服务器来提供服务。
2. 在主从服务器上分开处理用户的请求，如读写分离，可以提升数据处理效率。
3. 将主服务器上的数据复制到从服务器上，保护数据免受意外的损失（数据热备）。

## 原理（基于Binlog）

主从复制有两种方式

*   基于Binlog
*   基于GTID（全局事务标识符）

![](https://i.vgy.me/nCHhDe.png)

1. 主库记录二进制日志，每次准备提交事务完成数据库更新前，先记录二进制日志，记录二进制日志后，主库会告诉存储引擎可以提交事务了
2. 备库将主库的二进制日志复制到本地的中继日志中，首先，备库会先启动一个工作进程，称为IO工作线程，负责和主库建立一个普通的客户端连接。如果该进程追赶上了主库，它将进入睡眠状态，直到主库有新的事件产生通知它，他才会被唤醒，将接收到的事件记录到中继日志中。
3. 备库的SQL线程执行最后一步，该线程从中继日志中读取事件并且在备库执行，当SQL线程赶上IO线程的时候，中继日志通常记录在系统缓存中，所以中继日志的开销很低。SQL线程也可以根据配置选项来决定是否写入其自己的二进制日志中。

## 复制的方式

**异步复制**

MySQL复制默认是异步复制，Master将事件写入binlog，提交事务，自身并不知道slave是否接收是否处理；

缺点：不能保证所有事务都被所有slave接收。

**同步复制**

Master提交事务，直到事务在所有slave都已提交，才会返回客户端事务执行完毕信息；

缺点：完成一个事务可能造成延迟。


**半同步复制**

当Master上开启半同步复制功能时，至少有一个slave开启其功能。当Master向slave提交事务，且事务已写入relay-log中并刷新到磁盘上，slave才会告知Master已收到；若Master提交事务受到阻塞，出现等待超时，在一定时间内Master 没被告知已收到，此时Master自动转换为异步复制机制；

注：半同步复制功能要在Master和slave上开启才会起作用，只开启一边，依然是异步复制。

## 关于主从复制和读写分离

主从复制只是实现读写分离的基础，要实现读写分离还需要借助数据库中间件或者程序实现。

## 主从复制注意点

1. 主从服务器操作**系统版本和位数一致**
2. Master 和 Slave 数据库的**版本要一致**
3. 同步之前Master 和 Slave 数据库中的**数据要一致**
4. Master 开启二进制日志，Master 和 Slave 的 **server\_id 在局域网内必须唯一**

## 配置

master ： 172.30.0.2
slave： 172.30.0.3

### master

**在 \[mysqld\] 中增加以下配置项**

~~~
## 设置 server_id，一般设置为局域网IP最后一段
server_id=2

## 复制过滤：需要备份的数据库，输出 binlog，如果有多项就复制多段
# 如果有过滤，不建议在master端处理。Slave端操作粒度更细，并且master全量复制不需要另外修改。
#binlog-do-db=test
#binlog-do-db=test2  

## 复制过滤：不需要备份的数据库，不输出（mysql 库一般不同步）
binlog-ignore-db=mysql

## 开启二进制日志功能，可以随便取，最好有含义
log-bin=edu-mysql-bin

## 为每个 session 分配的内存，在事务过程中用来存储二进制日志的缓存
binlog_cache_size=1M

## 主从复制的格式（mixed,statement,row，默认格式是 statement）
binlog_format=mixed

## 二进制日志自动删除/过期的天数。默认值为 0，表示不自动删除。
expire_logs_days=7

## 跳过主从复制中遇到的所有错误或指定类型的错误，避免 slave 端复制中断。
## 如：1062 错误是指一些主键重复，1032 错误是因为主从数据库数据不一致
slave_skip_errors=1062
~~~

**关于MySQL 对于二进制日志 (binlog)的复制类型**

1. 基于语句的复制：在 Master 上执行的 SQL 语句，在 Slave 上执行同样的语句。MySQL 默认采用基于语句的复制，效率比较高。一旦发现没法精确复制时，会自动选着基于行的复制。
2.  基于行的复制：把改变的内容复制到 Slave，而不是把命令在 Slave 上执行一遍。从MySQL5.0 开始支持。
3. 混合类型的复制：默认采用基于语句的复制，一旦发现基于语句的无法精确的复制时，就会采用基于行的复制。

**创建数据同步用户，并授予相应的权限**

~~~
mysql -uroot -p

# 5.7以前(会有个warnning)
mysql> grant replication slave, replication client on *.* to 'repl'@'172.30.0.3' identified by 'caiwen.123'

# 5.7以后
create user 'dba'@'172.30.0.%' identified by 'caiwen.123';
grant replication slave on *.* to dba@'172.30.0.%';

# 刷新授权表信息
mysql> flush privileges;
# 查看 position 号，记下 position 号（从机上需要用到这个 position 号和现在的日志文件)
mysql> show master status;
~~~

![](https://i.vgy.me/bJG8LG.png)

<br />

**锁表同步数据**

~~~
# 锁定当前表
mysql> flush tables with read lock;
# 进行备份同步当前数据到主库
# 解锁表
mysql> unlock tables;
~~~

### slave

**在 \[mysqld\] 中增加以上的master配置项，然后添加或修改以下选项**

~~~
## 设置 server_id，一般设置为 IP
server_id=3

## 开启二进制日志，以备 Slave 作为其它 Slave 的 Master 时使用
log-bin=edu-mysql-slave1-bin

## relay_log 配置中继日志
relay_log=edu-mysql-relay-bin

## log_slave_updates 表示 slave 将复制事件写进自己的二进制日志
log_slave_updates=1

## 防止改变数据(除了特殊的线程)
read_only=1
~~~

如果 Slave 为其它 Slave 的 Master 时，必须设置 bin\_log。在这里，我们开启了二进制日志，而且显式的命名(默认名称为 hostname，但是，如果 hostname 改变则会出现问题)。
relay\_log 配置中继日志，log\_slave\_updates 表示 slave 将复制事件写进自己的二进制日志。当设置 log\_slave\_updates 时，你可以让 slave 扮演其它 slave 的 master。此时，slave 把 SQL线程执行的事件写进行自己的二进制日志(binary log)，然后，它的 slave 可以获取这些事件并执行它。如下图所示（发送复制事件到其它 Slave）：

![](https://i.vgy.me/2VVOWG.png)

~~~
mysql -uroot -p

# 配置主从
# master_log_file ##指定 Slave 从哪个日志文件开始读复制数据
#（可在 Master 上使用 show master status 查看到日志文件名）

# master_log_pos=429 ## 从哪个 POSITION 号开始读

# master_connect_retry=30 ##当重新建立主从连接时，如果连接建立失败，间隔多久后重试。
# 单位为秒，默认设置为 60 秒，同步延迟调优参数。

mysql> change master to master_host='172.30.0.2',master_user='repl', master_password='caiwen.123', master_port=3306, master_log_file='edu-mysql-bin.000001', master_log_pos=618, master_connect_retry=30;


## 查看主从同步状态
mysql> show slave status\G;
# 可看到 Slave_IO_State 为空， Slave_IO_Running 和 Slave_SQL_Running 是 No，表明 Slave 还 没有开始复制过程。

## 开启主从同步
mysql> start slave;

## 再查看主从同步状态
mysql> show slave status\G;
~~~

### 查看状态

可查看 master 和 slave 上线程的状态。在 master 上，可以看到 slave 的 I/O 线程创建的连接：

~~~
Master : mysql> show processlist\G;
~~~

![](https://i.vgy.me/eufSqL.png)


~~~
Slave: mysql> show processlist\G;
~~~

![](https://i.vgy.me/QjWDFu.png)

2\. row : 为 I/O 线程状态
3\. row ：为 SQL 线程状态。

<br />

当主从复制正在进行中时，如果想查看从库两个线程运行状态，可以通过执行在从库里执行”show slave statusG”语句，以下的字段可以给你想要的信息：

~~~
Master_Log_File — 上一个从主库拷贝过来的binlog文件
Read_Master_Log_Pos — 主库的binlog文件被拷贝到从库的relay log中的位置
Relay_Master_Log_File — SQL线程当前处理中的relay log文件
Exec_Master_Log_Pos — 当前binlog文件正在被执行的语句的位置
~~~

### 重置主从复制设置

测试过程中，如果遇到同步出错，可在 Slave 上重置主从复制设置（选操作）：

```
(1) mysql> reset slave;
(2) mysql> change master to master_host='172.30.0.2',master_user='repl', master_password='caiwen.123', master_port=3306, master_log_file='edu-mysql-bin.000001', master_log_pos=618, master_connect_retry=30;
(此时，master_log_file 和 master_log_pos 要在 Master 中用 show master status 命令查看)
```

>[warning] 注意：如果在 Slave 没做只读控制的情况下，千万不要在 Slave 中手动插入数据，那样数据 就会不一致，主从就会断开，就需要重新配置了。

### 主从相关命令

~~~
show master status;     //查看master的状态，尤其是当前的日志及位置
show slave status;     //查看slave的状态
reset slave;            //重置slave状态
start slave;            //启动slave状态（开启监听master的变化）
stop slave;             //暂停salve状态
~~~

## 关于主主复制

两台服务器上都开启二进制日志和relay日志，都设置replication账号，都设置对方为自己的master。  即可完成主主复制。

但是，这样配置主主复制会出现很多同步冲突的问题，一般都是通过第三方工具来处理。后续会介绍。

## 关于半同步复制

我们知道，普通的replication，即MySQL的异步复制，依靠MySQL二进制日志也即binary log进行数据复制。比如两台机器，一台主机（master），另外一台是从机（slave）。

1）正常的复制为：事务一（t1）写入binlog buffer；dumper线程通知slave有新的事务t1；binlog buffer进行checkpoint；slave的io线程接收到t1并写入到自己的的relay log；slave的sql线程写入到本地数据库。 这时，master和slave都能看到这条新的事务，即使master挂了，slave可以提升为新的master。

2）异常的复制为：事务一（t1）写入binlog buffer；dumper线程通知slave有新的事务t1；binlog buffer进行checkpoint；slave因为网络不稳定，一直没有收到t1；master挂掉，slave提升为新的master，t1丢失。

3）很大的问题是：主机和从机事务更新的不同步，就算是没有网络或者其他系统的异常，当业务并发上来时，slave因为要顺序执行master批量事务，导致很大的延迟。

为了弥补以上几种场景的不足，MySQL从5.5开始推出了半同步复制。相比异步复制，半同步复制提高了数据完整性，因为很明确知道，在一个事务提交成功之后，这个事务就至少会存在于两个地方。即在master的dumper线程通知slave后，增加了一个ack（消息确认），即是否成功收到t1的标志码，也就是dumper线程除了发送t1到slave，还承担了接收slave的ack工作。如果出现异常，没有收到ack，那么将自动降级为普通的复制，直到异常修复后又会自动变为半同步复制。

半同步复制具体特性：

*   从库会在连接到主库时告诉主库，它是不是配置了半同步。
*   如果半同步复制在主库端是开启了的，并且至少有一个半同步复制的从库节点，那么此时主库的事务线程在提交时会被阻塞并等待，结果有两种可能，要么至少一个从库节点通知它已经收到了所有这个事务的Binlog事件，要么一直等待直到超过配置的某一个时间点为止，而此时，半同步复制将自动关闭，转换为异步复制。
*   从库节点只有在接收到某一个事务的所有Binlog，将其写入并Flush到Relay Log文件之后，才会通知对应主库上面的等待线程。
*   如果在等待过程中，等待时间已经超过了配置的超时时间，没有任何一个从节点通知当前事务，那么此时主库会自动转换为异步复制，当至少一个半同步从节点赶上来时，主库便会自动转换为半同步方式的复制。
*   半同步复制必须是在主库和从库两端都开启时才行，如果在主库上没打开，或者在主库上开启了而在从库上没有开启，主库都会使用异步方式复制。

![](https://i.vgy.me/ArY36h.png)

## MySQL 主从数据同步延迟问题的调优

基于局域网的 Master/Slave 机制在通常情况下已经可以满足“实时”备份的要求了。如果延
迟比较大，可以从以下几个因素进行排查：
(1) 网络延迟；
(2) Master 负载过高；
(3) Slave 负载过高；

一般的做法是使用多台Slave来分摊读请求，再单独配置一台Slave只作为备份用，不进行 其他任何操作，就能相对最大限度地达到“实时”的要求了。MySQL 5.7之后，可以使用多线程复制，使用MGR复制架构
