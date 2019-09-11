## 基于centos创建

~~~
FROM docker-centos6:0.0.1
# install jdk1.7
ADD jdk-7u51-linux-x64.tar.gz /usr/local/src  
ADD mycat  /usr/local/src/mycat 
ENV JAVA_HOME=/usr/local/src/jdk1.7.0_51        
ENV PATH=$JAVA_HOME/bin:$PATH
ENV CLASSPATH=.:$JAVA_HOME/lib/dt.jar:$JAVA_HOME/lib/tools.jar

EXPOSE 8066 9066 3306
RUN chmod -R 777 /usr/local/src/mycat/bin  
CMD ["./usr/local/src/mycat/bin/mycat", "console"]  
~~~
