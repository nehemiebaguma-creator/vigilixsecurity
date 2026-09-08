FROM php:8.2-apache

# Installe Python et pip
RUN apt-get update && apt-get install -y python3 python3-pip

# Copie le code PHP
COPY . /var/www/html/

# Installe les dépendances Python
WORKDIR /var/www/html/drone-api
RUN pip3 install -r requirements.txt

# Active mod_rewrite pour Apache (si besoin)
RUN a2enmod rewrite

# Expose le port 80
EXPOSE 80

CMD ["apache2-foreground"]
