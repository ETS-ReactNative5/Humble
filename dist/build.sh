#!/bin/bash

# $1 is the action
# $2 is project
# $3 is the timestamp
# $4 is the DB Userid
# $5 is the DB Password
#create,backup,cleanup,remove,deploy,retain,rename

case $1 in
  'open')
        if [ -d "/var/www/$2" ]; then
                chown -R codeship:www-data /var/www/$2
                chmod -R 0775 /var/www/$2
        fi
        if [ -d "/var/www/$2_old" ]; then
                chown -R codeship:www-data /var/www/$2_old
                chmod -R 0775 /var/www/$2_old
        fi
        if [ -d "/var/www/Docs/$2" ]; then
                chown -R codeship:www-data /var/www/Docs/$2
                chmod -R 0775 /var/www/Docs/$2
        fi
        echo 'open';;
  'create')
        mkdir /var/www/$2_$3
        echo 'create';;
  'backup')
        echo $PWD
        mkdir /var/www/backups/$3
        cd /var/www/backups/$3
        echo $PWD
        mongodump
        mysqldump dashboard -u $4 -p$5 > mysql.sql
        echo 'backup';;
  'expand')
        mkdir /var/www/$2
        cd /var/www/$2
        tar xzf ../$2_$3.tar.gzip
        echo 'expand';;
  'purge')
        cd /var/www
        echo "Removing $2_$3.tar.gzip"
        rm $2_$3.tar.gzip
        echo 'purge';;
  'save')
        cd /var/www
        php backup.php save
        echo 'save';;
  'restore')
        cd /var/www
        php backup.php restore
        echo 'restore';;
  'cleanup')
        php cleanup.php $2 $3
        echo 'cleanup';;
  'remove')
        rm -R /var/www/$2_old
        echo 'remove';;
 'retain')
        mv /var/www/$2 /var/www/$2_old
        echo 'retain';;
  'rename')
        mv /var/www/$2_$3 /var/www/$2
        chown -R codeship:www-data /var/www/$2
        chmod -R 0775 /var/www/$2
        echo 'rename';;
  'composer')
        cd /var/www/$2/app
        echo 'composer update'
        composer update
        echo 'composer install'
        composer install
        echo 'composer';;
  'update')
        cd /var/www/$2/app
        php Module.php --u ns=*
        echo 'update';;
  'package')
        cd /var/www/$2/app
        php Module.php --package
        echo 'package';;
  'assign')
        cd /var/www
        chmod -R 0775 $2
        chown -R codeship:www-data $2
        echo 'assign';;
  'document')
        rm -R /var/www/Docs/$2
        mkdir /var/www/Docs/$2
        chmod -R 0775 /var/www/Docs/$2
        chown codeship:www-data /var/www/Docs/$2
        cd /var/www/Docs/$2
        /var/www/phpdoc/vendor/bin/phpdoc -c /var/www/$2/phpdoc.dist.xml
        echo 'document';;
  'npm')
        cd /var/www/$2/Hub
        echo $3
        IFS=' ' read -r -a modules <<< "$3"
        for w in "${modules[@]}"
        do
                npm install $w --save-dev
        done
        systemctl restart argushub
        echo 'npm';;
  'increment')
        cd /var/www/$2/app
        php Module.php --increment
        echo 'package';;
  'revert')
        cd /var/www
        mv $2 $2_revert
        mv $2_old $2
        echo 'revert';;
  'rollback')
        cd /var/www
        echo 'rollback';;
  *)
        echo "i dunno how to do that $1";;
esac
