# mycat集群

一HAproxy，二mycat，一主一从


# vi /usr/local/haproxy/conf/haproxy.cfg

## global配置中的参数为进程级别的参数，通常与其运行的操作系统有关
global

    ## 定义全局的syslog服务器，最多可以定义2个
    ## local0是日志设备，对应于/etc/rsyslog.conf中的配置，默认回收info的日志级别
    log 127.0.0.1 local0 info 
    #log 127.0.0.1 local1 info
    
    
    ### 修改HAProxy的工作目录至指定的目录并在放弃权限之前执行
    chroot /usr/share/haproxy 
    
    ### chroot() 操作，可以提升 haproxy 的安全级别
    group haproxy ## 同gid，不过这里为指定的用户组名
    user haproxy ## 同uid，但这里使用的为用户名
    daemon ## 设置haproxy后台守护进程形式运行
    
    ### 只能用于守护进程模式的haproxy；默认为止启动1个进程，
    ### 一般只在单进程仅能打开少数文件描述符的场中中才使用多进程模式
    nbproc 1 ## 指定启动的haproxy进程个数，
    maxconn 4096 ## 设定每个haproxy进程所接受的最大并发连接数，

    ### 其等同于命令行选项"-n"，"ulimit-n"自动计算的结果正式参照从参数设定的
    ulimit-n	16384

    ### 进程文件（默认路径 /var/run/haproxy.pid）
    # pidfile /var/run/haproxy.pid 

    ### 定义当前节点的名称，用于HA场景中多haproxy进程共享同一个IP地址时
    # node edu-haproxy-01 
    
    ### 当前实例的描述信息
    description edu-haproxy-01 

## defaults：用于为所有其他配置段提供默认参数，这默认配置参数可由下一个"defaults"所重新设定
defaults

    ## 继承global中log的定义
    log global 

    ## mode:所处理的模式 (tcp:四层 , http:七层 , health:状态检查,只会返回OK)
    mode http 

    ### tcp: 实例运行于纯tcp模式，在客户端和服务器端之间将建立一个全双工的连接，
    ### 且不会对7层报文做任何类型的检查，此为默认模式
    
    ### http:实例运行于http模式，客户端请求在转发至后端服务器之前将被深度分析，
    #### 所有不与RFC模式兼容的请求都会被拒绝

    ### health：实例运行于health模式，其对入站请求仅响应“OK”信息并关闭连接，
    #### 且不会记录任何日志信息 ，此模式将用于相应外部组件的监控状态检测请求

    option httplog
    retries 3
    
    ## serverId对应的服务器挂掉后,强制定向到其他健康的服务器
    option redispatch 

    maxconn 2000 ## 前端的最大并发连接数（默认为2000）

    ### 其不能用于backend区段，对于大型站点来说，可以尽可能提高此值以便让haproxy管理连接队列，
    ### 从而避免无法应答用户请求。当然，此最大值不能超过“global”段中的定义。
    ### 此外，需要留心的是，haproxy会为每个连接维持两个缓冲，每个缓存的大小为8KB，
    ### 再加上其他的数据，每个连接将大约占用17KB的RAM空间，这意味着经过适当优化后 ，
    ### 有着1GB的可用RAM空间时将维护40000-50000并发连接。
    ### 如果指定了一个过大值，极端场景中，其最终所占据的空间可能会超过当前主机的可用内存，
    ### 这可能会带来意想不到的结果，因此，将其设定一个可接受值放为明智绝对，其默认为2000

    timeout connect 5000ms ## 连接超时(默认是毫秒,单位可以设置us,ms,s,m,h,d)
    timeout client 50000ms ## 客户端超时
    timeout server 50000ms ## 服务器超时

## HAProxy的状态信息统计页面

listen admin_stats

bind :48800 ## 绑定端口

stats uri /admin-status ##统计页面

stats auth admin:admin ## 设置统计页面认证的用户和密码，如果要设置多个，另起一行写入即可

mode http

option httplog ## 启用日志记录HTTP请求

## listen: 用于定义通过关联“前端”和“后端”一个完整的代理，通常只对TCP流量有用

listen mycat_servers

bind :3306 ## 绑定端口

mode tcp

option tcplog ## 记录TCP请求日志

option tcpka ## 是否允许向server和client发送keepalive

option httpchk OPTIONS * HTTP/1.1\r\nHost:\ www ## 后端服务状态检测

### 向后端服务器的48700端口（端口值在后端服务器上通过xinetd配置）发送 OPTIONS 请求

### (原理请参考HTTP协议) ，HAProxy会根据返回内容来判断后端服务是否可用.

### 2xx 和 3xx 的响应码表示健康状态，其他响应码或无响应表示服务器故障。

balance roundrobin ## 定义负载均衡算法，可用于"defaults"、"listen"和"backend"中,默认为轮询方式

server mycat_01 192.168.1.203:8066 check port 48700 inter 2000ms rise 2 fall 3 weight 10

server mycat_02 192.168.1.204:8066 check port 48700 inter 2000ms rise 2 fall 3 weight 10

## 格式：server <name> <address>[:[port]] [param*]

### serser 在后端声明一个server，只能用于listen和backend区段。

### <name>为此服务器指定的内部名称，其将会出现在日志及警告信息中

### <address>此服务器的IPv4地址，也支持使用可解析的主机名，但要在启动时需要解析主机名至响应的IPV4地址

### [:[port]]指定将客户端连接请求发往此服务器时的目标端口，此为可选项

### [param*]为此server设定的一系列参数，均为可选项，参数比较多，下面仅说明几个常用的参数：

#### weight:权重，默认为1，最大值为256，0表示不参与负载均衡

#### backup:设定为备用服务器，仅在负载均衡场景中的其他server均不可以启用此server

#### check:启动对此server执行监控状态检查，其可以借助于额外的其他参数完成更精细的设定

#### inter:设定监控状态检查的时间间隔，单位为毫秒，默认为2000，

##### 也可以使用fastinter和downinter来根据服务器端专题优化此事件延迟

#### rise:设置server从离线状态转换至正常状态需要检查的次数（不设置的情况下，默认值为2）

#### fall:设置server从正常状态转换至离线状态需要检查的次数（不设置的情况下，默认值为3）

#### cookie:为指定server设定cookie值，此处指定的值将会在请求入站时被检查，

##### 第一次为此值挑选的server将会被后续的请求所选中，其目的在于实现持久连接的功能

#### maxconn:指定此服务器接受的最大并发连接数，如果发往此服务器的连接数目高于此处指定的值，

#####其将被放置于请求队列，以等待其他连接被释放
