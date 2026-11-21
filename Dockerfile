FROM php:8.2-apache

RUN a2enmod rewrite

COPY . /tmp/src/

RUN set -eux; \
        if [ -f /tmp/src/relay.php ]; then \
            cp -R /tmp/src/. /var/www/html/; \
        elif [ -f /tmp/src/cloud-relay/relay.php ]; then \
            cp -R /tmp/src/cloud-relay/. /var/www/html/; \
        else \
            echo "relay.php not found in build context"; \
            ls -la /tmp/src; \
            exit 1; \
        fi; \
        mkdir -p /var/www/html/data; \
        chown -R www-data:www-data /var/www/html/data; \
        rm -rf /tmp/src

EXPOSE 80