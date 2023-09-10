#!/bin/bash

set -e

SERVER=localhost
USERNAME=admin

sql(){
	mysql -h dp-sql --password=dp_password
}

curl "http://${SERVER}/c/accounts/addproofer.php" \
	--data-urlencode "password=proofer" \
	--data-urlencode "real_name=Site Admin" \
	--data-urlencode "userNM=${USERNAME}" \
	--data-urlencode "userPW=admin" \
	--data-urlencode "userPW2=admin" \
	--data-urlencode "email_updates=1"


ID=$(echo "select id from dp_db.non_activated_users where username='${USERNAME}';" | sql | sed '/^id$/d')
if [ -z "$ID" ]
then
	echo ID not found
	exit 2
fi
curl "http://${SERVER}/c/accounts/activate.php?id=${ID}"

echo "UPDATE dp_db.users SET sitemanager = 'yes' WHERE username = '${USERNAME}';" | sql
