FROM php:8.2-apache

RUN a2dismod mpm_event mpm_worker 2>/dev/null; a2enmod mpm_prefork rewrite headers

RUN docker-php-ext-install pdo pdo_mysql

COPY . /var/www/html/

RUN echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' >> /etc/apache2/apache2.conf

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
