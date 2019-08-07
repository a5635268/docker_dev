edu-mysql-bin.000001 |      618

MySQL 主从数据同步延迟问题的调优


mysql> grant replication slave on *.* to 'repl'@'172.31.0.%' identified by '123456';
mysql> flush privileges;


mysql> CHANGE MASTER TO MASTER_HOST='172.31.0.2',MASTER_USER='repl',MASTER_PASSWORD='123456',MASTER_AUTO_POSITION=1;
mysql> START SLAVE;

Retrieved_Gtid_Set: 1b74566f-9707-11e9-b449-0242ac1f0002:1-27
Executed_Gtid_Set: 1b74566f-9707-11e9-b449-0242ac1f0002:1-27