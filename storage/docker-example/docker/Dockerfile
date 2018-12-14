FROM webdevops/php-nginx:7.3

ADD php-conf/php.ini /opt/docker/etc/php/php.ini
ADD nginx-conf/nginx.conf /etc/nginx/nginx.conf
ADD nginx-conf/default.conf /opt/docker/etc/nginx/vhost.conf
ADD nginx-conf/10-location-root.conf /opt/docker/etc/nginx/vhost.common.d/10-location-root.conf
ADD key/* /root/.ssh/
ADD key/* /home/application/.ssh/
ADD composer.phar /bin/composer

RUN set -ex \
&&  chown -R root:root /root/.ssh/ \
&&  chmod -R 600 /root/.ssh/ \
&&  chown -R application:application /home/application/.ssh/ \
&&  chmod 700 /home/application/.ssh/ \
&&  chmod 600 /home/application/.ssh/* \
&&  chmod +x /bin/composer 
