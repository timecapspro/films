FROM yiisoftware/yii2-php:8.1-apache

# Change document root for Apache
RUN sed -i -e 's|/app/web|/app/backend/web|g' /etc/apache2/sites-available/000-default.conf

# Increase upload limits for poster files
RUN { \
    echo 'upload_max_filesize=20M'; \
    echo 'post_max_size=22M'; \
    echo 'memory_limit=256M'; \
} > /usr/local/etc/php/conf.d/uploads.ini
