#!/bin/bash

SERVER=localhost

DBPARAMS=(
	--data-urlencode "dbms=mysqli" \
	--data-urlencode "dbhost=dp-sql" \
	--data-urlencode "dbport=" \
	--data-urlencode "dbname=phpbb" \
	--data-urlencode "dbuser=root" \
	--data-urlencode "dbpasswd=dp_password" \
	--data-urlencode "table_prefix=phpbb_" \
)

ADMINPARAMS=(
	--data-urlencode "default_lang=en" \
	--data-urlencode "admin_name=bbadmin" \
	--data-urlencode "admin_pass1=bbadmin" \
	--data-urlencode "admin_pass2=bbadmin" \
	--data-urlencode "board_email1=bbadmin@example.com" \
	--data-urlencode "board_email2=bbadmin@example.com" \
)

SITEPARAMS=(
	--data-urlencode "email_enable=1" \
	--data-urlencode "smtp_delivery=0" \
	--data-urlencode "smtp_host=" \
	--data-urlencode "smtp_auth=PLAIN" \
	--data-urlencode "smtp_user=" \
	--data-urlencode "smtp_pass=" \
	--data-urlencode "cookie_secure=0" \
	--data-urlencode "force_server_vars=0" \
	--data-urlencode "server_protocol=http://" \
	--data-urlencode "server_name=172.19.0.3" \
	--data-urlencode "server_port=80" \
	--data-urlencode "script_path=/phpBB3" \
)

curl "http://${SERVER}/phpBB3/install/index.html?mode=install&sub=database" \
	${DBPARAMS[*]} \
	--data-urlencode "testdb=true" \
	--data-urlencode "language=en"

curl "http://${SERVER}/phpBB3/install/index.html?mode=install&sub=administrator" \
	${ADMINPARAMS[*]} \
	${DBPARAMS[*]} \
	--data-urlencode "check=true" \
	--data-urlencode "language=en"

curl  "http://${SERVER}/phpBB3/install/index.html?mode=install&sub=config_file" \
	${ADMINPARAMS[*]} \
	${DBPARAMS[*]} \
	--data-urlencode "language=en"

curl "http://${SERVER}/phpBB3/install/index.html?mode=install&sub=create_table" \
	${SITEPARAMS[*]} \
	${DBPARAMS[*]} \
	${ADMINPARAMS[*]} \
	--data-urlencode "language=en"

curl "http://${SERVER}/phpBB3/install/index.html?mode=install&sub=final" \
	${DBPARAMS[*]} \
	${ADMINPARAMS[*]} \
	${SITEPARAMS[*]} \
	--data-urlencode "img_imagick=" \
	--data-urlencode "ftp_path=" \
	--data-urlencode "ftp_user=" \
	--data-urlencode "ftp_pass=" \
	--data-urlencode "language=en"
