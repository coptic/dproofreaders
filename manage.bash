#!/bin/bash

set -e

NET=coptic
VERSION=R201701
DP_IMAGE=coptic/dproofreaders

containerstatus(){
	docker inspect -f '{{.State.Status}}' "$1" 2>/dev/null
}

runimg(){
	name=$1
	shift

	case "$(containerstatus ${name})" in
	running)
		echo $name already running
		;;
	exited)
		echo docker start $name	'#' currently stopped
		;;
	*)
		docker run -d --network=$NET \
			--name=${name} --hostname=${name} "$@"
		;;
	esac
}

if ! docker network inspect "$NET" >/dev/null 2>&1
then
	docker network create $NET
fi

containerip(){
	docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' $1
}

showurl(){
	echo http://$(containerip dp-web)/
}

case "$1" in
url)
	showurl
	;;
kill)
	docker stop dp-sql dp-web
	docker rm dp-sql dp-web
	;;
build)
	docker build -t ${DP_IMAGE}:${VERSION} -t ${DP_IMAGE}:latest .
	;;
run)
	shift
	volarg=
	if [ $# -gt 0 ]; then
		if [ ! -d "$1" ]; then
			echo $1 is not a directory
			exit 2
		fi
		volarg="-v $1:/var/www/html/c"
	fi
	runimg dp-sql -e 'MYSQL_ROOT_PASSWORD=dp_password' mariadb:11.1.2
	runimg dp-web $volarg $DP_IMAGE
	showurl
	;;
sql)
	mysql -h $(containerip dp-sql) -u root --password=dp_password
	;;
bounce)
	docker stop dp-sql dp-web
	docker rm dp-sql dp-web
	docker build -t ${DP_IMAGE}:${VERSION} -t ${DP_IMAGE}:latest .
	shift
	volarg=
	if [ $# -gt 0 ]; then
		if [ ! -d "$1" ]; then
			echo $1 is not a directory
			exit 2
		fi
		volarg="-v $1:/var/www/html/c"
	fi
	runimg dp-sql -e 'MYSQL_ROOT_PASSWORD=dp_password' mariadb:11.1.2
	runimg dp-web $volarg $DP_IMAGE
	showurl
	;;
*)
	echo usage: $0 '<cmd> [args...]'
	echo
	echo "    build      Build web container using docker."
	echo "    kill       Kill mysql and web containers."
	echo "    run [dir]  Run mysql and web containers."
	echo "               DProofeaders source is replaced with dir"
	echo "               (bound to /var/www/html/c)."
	echo "    sql        Start mysql client."
	echo "    url        Print web server URL."
	exit 2
	;;
esac
