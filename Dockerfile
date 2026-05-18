# Imatge base: PHP amb Apache per servir les pàgines web
FROM php:apache

# Instal·lem l'extensió mysqli per connectar PHP amb MySQL
RUN docker-php-ext-install mysqli

# Instal·lem cron, dos2unix, zip i les llibreries necessàries per gd i xlsx
RUN apt-get update && apt-get install -y \
    cron dos2unix zip unzip libzip-dev \
    libpng-dev libjpeg-dev libfreetype6-dev

# Instal·lem les extensions PHP necessàries
RUN docker-php-ext-install zip
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install gd

# Instal·lem Composer globalment
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Instal·lem PhpSpreadsheet ignorant requisits de plataforma
RUN mkdir -p /opt/spreadsheet && \
    composer require phpoffice/phpspreadsheet:^2.0 \
    --working-dir=/opt/spreadsheet \
    --no-interaction \
    --ignore-platform-reqs

# Copiem l'script d'inici al contenidor i el convertim a format Linux
COPY start.sh /start.sh
RUN dos2unix /start.sh && chmod +x /start.sh

# Configurem el cron per sincronitzar cada 30 segons
RUN (echo '* * * * * php /var/www/html/sync_sheets.php > /dev/null 2>&1'; echo '* * * * * sleep 30 && php /var/www/html/sync_sheets.php > /dev/null 2>&1') | crontab -

# Quan arrenca el contenidor, executem l'script d'inici
CMD ["/start.sh"]