#!/bin/bash
echo -ne "\nexclude=centos-release*\n" >> /etc/yum.conf
yum update
rpm -Uvh http://dl.fedoraproject.org/pub/epel/6/x86_64/epel-release-6-8.noarch.rpm
rpm -Uvh http://rpms.famillecollet.com/enterprise/remi-release-6.rpm
rpm --import http://repos.1c-bitrix.ru/yum/RPM-GPG-KEY-BitrixEnv

cat <<EOF > /etc/yum.repos.d/bitrix.repo
[bitrix]
name=\$OS \$releasever - \$basearch
failovermethod=priority
baseurl=http://repos.1c-bitrix.ru/yum/el/6/\$basearch
enabled=1
gpgcheck=1
gpgkey=file:///etc/pki/rpm-gpg/RPM-GPG-KEY-BitrixEnv
EOF

curl --silent --location https://rpm.nodesource.com/setup_6.x | bash -
yum install wget postfix nano lsof htop lshw iotop httpd httpd-itk httpd-devel httpd-tools php-pear php-pdo php-mysql php-mcrypt php-process php-cli php-mssql php php-mbstring php-xml php-gd php-bcmath php-pecl-zendopcache php-common php-devel php-soap nodejs nginx -y
yum install -y curl-devel expat-devel gettext-devel openssl-devel zlib-devel gcc perl-ExtUtils perl-devel
yum install -y cmake ncurses-devel libxml2-devel bison-devel bison libevent-devel libevent gcc-c++ boost boost-devel boost-doc Judy Judy-devel
cd /usr/src
gitlatestver=`curl -s https://www.kernel.org/pub/software/scm/git/ | egrep "(git-[[:digit:]].*tar\.gz)" | tail -1 | grep -o -P '(?<=\>).*(?=\.tar.gz<)'`
echo "Latest stable MariaDB version is $gitlatestver."
wget https://www.kernel.org/pub/software/scm/git/$gitlatestver.tar.gz
tar -xvf $gitlatestver.tar.gz
cd $gitlatestver
make prefix=/usr/local/git all
make prefix=/usr/local/git install
cd /usr/src
marialatestver=`curl -s https://downloads.mariadb.org/mariadb/+releases/ | sed -e '/<tr/,/tr>/!d' | grep -A 1 -B 3 -m 1 "Stable" | grep -o -P '(?<=\">).*(?=\</a)'`
echo "Latest stable MariaDB version is $marialatestver."
wget http://mirror.timeweb.ru/mariadb/mariadb-$marialatestver/source/mariadb-$marialatestver.tar.gz
tar -xvf mariadb-$marialatestver.tar.gz
cd mariadb-$marialatestver
cmake . \
-DINSTALL_SYSCONFDIR=/usr/local/mysql/etc \
-DINSTALL_SYSCONF2DIR=/usr/local/mysql/etc \
-DDEFAULT_SYSCONFDIR=/usr/local/mysql/etc
make && make install
mkdir /var/lib/mariadb /var/lib/mariadbd /var/run/mariadbd 
useradd mysql -d/var/lib/mariadb 
chown mysql:mysql /var/lib/mariadb /var/lib/mariadbd /var/run/mariadbd
mv /usr/local/mysql/etc/my.cnf /usr/local/mysql/etc/my.cnf.orig
mkdir -p /etc/mysql/conf.d

cat <<EOF > /etc/mysql/conf.d/z_custom.cnf
[mysqld]
query_cache_size = 128M
query_cache_limit = 8M
innodb_buffer_pool_size = 1024M
max_connections = 50
table_cache = 8096
thread_cache_size = 32
max_heap_table_size = 512M
tmp_table_size = 512M
key_buffer = 32M
join_buffer_size = 12M
sort_buffer_size = 8M
bulk_insert_buffer_size = 2M
myisam_sort_buffer_size = 2M
EOF

cat <<EOF > /usr/local/mysql/etc/my.cnf
[client]
port = 3306
socket = /var/lib/mariadbd/mysqld.sock
default-character-set = utf8

[mysqld_safe]
socket = /var/lib/mariadbd/mysqld.sock
nice = 0

[mysqld]
user = mysql
pid-file = /var/run/mariadbd/mysqld.pid
socket = /var/lib/mariadbd/mysqld.sock
port = 3306
basedir = /usr/local/mysql
datadir = /var/lib/mariadb
tmpdir = /tmp
skip-external-locking
query_cache_size = 32M
table_cache = 4096
thread_cache_size = 32
max_heap_table_size     = 32M
tmp_table_size = 32M
innodb_buffer_pool_size = 32M
innodb_flush_log_at_trx_commit = 2
innodb_log_file_size = 64M
innodb_flush_method = O_DIRECT
transaction-isolation = READ-COMMITTED
default-storage-engine = innodb
#bind-address = 127.0.0.1
key_buffer = 16M
max_allowed_packet = 16M
thread_stack = 128K
myisam-recover = BACKUP
expire_logs_days = 10
max_binlog_size = 100M
join_buffer_size = 2M
sort_buffer_size = 2M
character-set-server = utf8
collation-server = utf8_unicode_ci
init-connect = "SET NAMES utf8 COLLATE utf8_unicode_ci"
skip-character-set-client-handshake
innodb_file_per_table
default-time-zone='+03:00'


[mysqldump]
quick
quote-names
max_allowed_packet = 16M
default-character-set = utf8

[mysql]

[isamchk]
key_buffer = 16M

!includedir /etc/mysql/conf.d/
EOF

cat <<EOF > /root/.my.cnf
[client]
port = 3306
socket = /var/lib/mariadbd/mysqld.sock
default-character-set = utf8
user=root
password=
EOF

chmod +x /usr/local/mysql/scripts/mysql_install_db
/usr/local/mysql/scripts/mysql_install_db --user=mysql --defaults-file=/usr/local/mysql/etc/my.cnf --basedir=/usr/local/mysql --datadir=/var/lib/mariadb
cp /usr/local/mysql/share/english/errmsg.sys /usr/share/errmsg.sys
cp /usr/local/mysql/support-files/mysql.server /etc/init.d/mariadbd
sed -i '0,/datadir=/ s/datadir=/datadir=\/var\/lib\/mariadb/' /etc/init.d/mariadbd
sed -i '/PATH="\/sbin:\/usr\/sbin:\/bin:\/usr\/bin:$basedir\/bin"/d' /etc/init.d/mariadbd
sed -i '/export PATH/d' /etc/init.d/mariadbd
chmod +x /etc/init.d/mariadbd
chkconfig mariadbd on
echo "export PATH=/usr/local/mysql/bin:/usr/local/git/bin:$PATH" >> /etc/bashrc
source /etc/bashrc

cat <<EOF >> /root/.bash_profile
clear
/root/symfony-sandbox/index
echo "Users"
ls /home/
echo "Projects"
ls /home/dev/projects/
EOF
cat <<EOF >> /root/.bashrc
if [ -f ~/.bash_aliases ]; then
    . ~/.bash_aliases
fi
EOF
cat <<EOF >> /root/.bash_aliases
alias sandbox:configure='$PWD/index sandbox:configure'
alias sandbox:project_add='$PWD/index sandbox:project_add'
alias sandbox:project_remove='$PWD/index sandbox:project_remove'
alias sandbox:sandbox_add='$PWD/index sandbox:sandbox_add'
alias sandbox:sandbox_remove='$PWD/index sandbox:sandbox_remove'
alias sandbox:user_add='$PWD/index sandbox:user_add'
alias sandbox:user_remove='$PWD/index sandbox:user_remove'
alias sandbox:virtualhost_add='$PWD/index sandbox:virtualhost_add'
alias sandbox:virtualhost_remove='$PWD/index sandbox:virtualhost_remove'
EOF

wget https://getcomposer.org/composer.phar
chmod +x composer.phar
mv composer.phar /usr/local/bin/composer


# Конфигурация
echo "HTTPD=/usr/sbin/httpd.itk" >> /etc/sysconfig/httpd
/bin/cp -rf $PWD/etc/* /etc/
ln -s /etc/nginx/sandbox/site_avaliable/s1.conf /etc/nginx/sandbox/site_enabled/
# Конфигурация

# Рестарт сервисов
service httpd restart && service mariadbd restart && service nginx restart
