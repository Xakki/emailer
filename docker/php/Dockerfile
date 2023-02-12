# Version: 0.1.0
FROM php:8.2-fpm
MAINTAINER Xakki <xakki@ya.ru>
ARG DEBIAN_FRONTEND=noninteractive
ENV REFRESHED_AT="2023–01–05"

USER root

RUN apt-get update && apt-get install -y --no-install-recommends \
    apt-utils cron mc htop dialog bash-completion curl wget bash nano git lsb-release apt-transport-https \
    ca-certificates gnupg2 tzdata man zip unzip pkg-config
RUN apt-get install -y \
    libjpeg62-turbo-dev libpng-dev libwebp-dev libfreetype6-dev libgmp-dev \
    libicu-dev libzip-dev libmcrypt-dev libssl-dev libonig-dev fontconfig
#      libffi-dev libmcrypt4 libmcrypt-dev   \
#     zlib1g-dev libpq-dev freetds-bin freetds-dev freetds-common \
#     graphviz libc-client-dev libcurl4-openssl-dev

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
#RUN curl -o composer https://getcomposer.org/download/2.1.3/composer.phar && chmod 0555 composer && mv composer /usr/bin/


RUN if [ "$ENV" = "dev" ] ; then pecl install xdebug && docker-php-ext-enable xdebug ; fi

RUN pecl install redis && docker-php-ext-enable redis
RUN docker-php-ext-configure intl --enable-intl
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp
RUN docker-php-ext-install -j$(nproc) gd intl pdo_mysql mbstring zip pcntl opcache sockets bcmath

###Possible values for ext-name:
### bcmath bz2 calendar ctype curl dba dom enchant exif ffi fileinfo filter ftp gd gettext gmp hash iconv imap intl json ldap mbstring mysqli 
### oci8 odbc opcache pcntl pdo pdo_dblib pdo_firebird pdo_mysql pdo_oci pdo_odbc pdo_pgsql pdo_sqlite pgsql phar posix pspell readline reflection 
### session shmop simplexml snmp soap sockets sodium spl standard sysvmsg sysvsem sysvshm tidy tokenizer xml xmlreader xmlwriter xsl zend_test zip

RUN docker-php-source delete && apt-get autoremove --purge -y && apt-get autoclean -y && apt-get clean -y

RUN echo "access.log = /dev/null" >> /usr/local/etc/php-fpm.d/docker.conf

COPY cronjob /etc/cron.d/
RUN chmod 0644 /etc/cron.d/cronjob
RUN crontab /etc/cron.d/cronjob

STOPSIGNAL SIGKILL

CMD printenv > /etc/environment && cron -f && php-fpm -R
