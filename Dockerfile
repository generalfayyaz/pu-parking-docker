# ============================================================
#  PU Parking - application image (PHP + Apache)
#  Build:  docker build -t group03/pu-parking:week6 .
# ============================================================
FROM php:8.2-apache

# PDO MySQL driver (db.php uses PDO with the mysql driver)
RUN docker-php-ext-install pdo pdo_mysql

# App is written for BASE_URL = /parking_system/, so serve it from that path
WORKDIR /var/www/html/parking_system
COPY parking_system/ ./

# Visiting http://localhost:PORT/ redirects to the app
RUN printf '<?php header("Location: /parking_system/"); exit;\n' > /var/www/html/index.php \
 && chown -R www-data:www-data /var/www/html

# Apache listens on 80 inside the container
EXPOSE 80

# Ready when the login page answers
HEALTHCHECK --interval=15s --timeout=5s --start-period=10s --retries=3 \
  CMD php -r "exit(@file_get_contents('http://localhost/parking_system/auth/login.php') === false ? 1 : 0);"

CMD ["apache2-foreground"]
