#!/bin/bash

set -e

DOCROOT=/var/www/html/
SETUPDIR=${DOCROOT}/c/SETUP

sql(){
	mysql -h dp-sql --password=dp_password
}

echo waiting for mysql server...
while ! mysqladmin ping -h dp-sql --password=dp_password --silent; do
	sleep 1
done
echo got ping back from mysql

# for dp_db.users.postprocessor, and other tinytext fields
# e.g. "Field 'postprocessor' doesn't have a default value" in accounts/activate.php
echo "SET GLOBAL sql_mode=(SELECT REPLACE(@@sql_mode,'STRICT_TRANS_TABLES',''));" | sql

# configure PD & initialize database
# Note: remove SETUP directory in production
cd $SETUPDIR
./configure /var/www/html/c/config/dproofreads_config.sh $DOCROOT
php -f install_db.php

cd $DOCROOT
apache2-foreground &

# Set up phpBB
sleep 3	# wait for apache to start up
echo 'CREATE DATABASE IF NOT EXISTS phpbb  CHARACTER SET utf8mb4;' | sql
if echo 'select 1 from phpbb.phpbb_config;' | sql
then
	echo phpBB already set up
	if [ ! -e "${DOCROOT}/phpBB3/config.php" ]
	then
		cp /var/www/html/c/config/phpbb_config.php ${DOCROOT}/phpBB3/config.php
	fi
else
	echo setting up phpBB for first time
	chown www-data:www-data ${DOCROOT}/phpBB3/config.php
	/var/www/html/c/bin/phpbb-setup.bash 
fi
if echo 'select 1 from phpbb.phpbb_config;' | sql
then
	echo moving phpBB install directory
	mkdir -p /app/phpBB3
	mv $DOCROOT/phpBB3/install /app/phpBB3
fi

if ! /var/www/html/c/bin/create-admin.bash > /dev/null; then
	echo creating admin user failed
fi

wait # apache2-foreground
