FROM php:7.4-apache

RUN mkdir -p /src && \
	cd /src && \
	curl -L --insecure -O https://download.phpbb.com/pub/release/3.3/3.3.10/phpBB-3.3.10.tar.bz2 

# libpng12-dev for php gd extension
# libyaz4-dev for php yaz extension
# unzip is for c/tools/project_manager/add_files.php
# aspell is for WordCheck
# vim-tiny, less are just for debugging
RUN apt-get update 
RUN apt-get install -y libpng-dev libyaz4-dev unzip aspell \
	mariadb-client vim-tiny less

# mysql is for dproofreaders
# mysqli is for phpBB3 (note: things break if mysql is used instead of mysqli)
RUN docker-php-ext-install gd gettext intl && \
	pecl install yaz
RUN docker-php-ext-install mysqli pdo pdo_mysql && \
	docker-php-ext-enable pdo_mysql

COPY ./ /var/www/html/c
RUN mkdir -p /var/www/html/ && \
	cd /var/www/html/ && \
	tar xjf /src/phpBB-3.3.10.tar.bz2

# TODO: chmod
RUN cd /var/www/html && \
	mkdir -p \
	/tmp/sp_check \
	projects \
	d/locale \
	d/stats/automodify_logs \
	d/teams/avatar \
	d/teams/icon \
	d/pg \
	d/xmlfeeds \
	/home/dpscans && \
	chown -R www-data:www-data /tmp/sp_check projects d /home/dpscans

RUN cp /var/www/html/c/config/php.ini /usr/local/etc/php/

CMD ["/var/www/html/c/bin/runapache.bash"]
