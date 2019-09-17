# 用于测试多容器网络链接

    # 网络相关命令
    docker network --help
    
    # 创建一个网络 
    docker network create  \--driver=bridge --subnet=192.168.88.0/24 demo
    
    # 安装ping来测试
    apt-get install iputils-ping

- 每创建一个docker network，ifconfig中就会新增一组
     
*****

* [docker-compose的每个设置项都介绍了](https://docs.docker.com/compose/compose-file/#network-configuration-reference)

*****

> 1.  未显式声明网络环境，自动生成“当前目录名_default”
> 2. networks关键字指定自定义网络
> 3. 配置默认网络 （除非要指定特定的网络驱动程序，否则不用）
> 4. 使用已存在的网络

~~~
# 默认连接某个已存在的网络existing-network
networks:
  default:
    external:
      name: existing-network

# 引入外部的demo网络
networks:
  demo:
    external: true
~~~


* [docker-compose网络设置之networks](https://blog.csdn.net/Kiloveyousmile/article/details/79830810)

*****

> 1. docker默认的三种网络介绍
> 2.  如何创建自定义网络

* [docker-compose文件中networks使用已经创建的网络](https://blog.csdn.net/henni_719/article/details/89376111)
* [Docker自定义网络和运行时指定IP](https://blog.csdn.net/sbxwy/article/details/78962809)
