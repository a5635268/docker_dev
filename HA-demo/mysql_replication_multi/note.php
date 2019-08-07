# slave的my.conf增加以下配置：
slave-skip-errors=1032

# 重启数据库
/etc/init.d/mysqld restart

# 跳过一个事务
mysql@slave>STOP SLAVE;
mysql@slave>SET GLOBAL SQL_SLAVE_SKIP_COUNTER = 1
mysql@slave>slave start;







