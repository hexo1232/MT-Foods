# Usar imagem oficial do PHP com Apache
FROM php:8.4-apache

# 1. INSTALAR DEPENDÊNCIAS DO SISTEMA
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    curl \
    && rm -rf /var/lib/apt/lists/*

# 2. INSTALAR EXTENSÕES PHP
RUN docker-php-ext-install mysqli pdo pdo_mysql zip

# 3. INSTALAR O COMPOSER
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# 4. COPIAR OS ARQUIVOS DO PROJETO
COPY . /var/www/html/

WORKDIR /var/www/html/

# 5. RODAR COMPOSER INSTALL (no build)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# 6. AJUSTAR PERMISSÕES
RUN chown -R www-data:www-data /var/www/html

# 7. HABILITAR mod_rewrite
RUN a2enmod rewrite

# 8. CONFIGURAR APACHE PARA PORTA 8080
RUN sed -i 's/Listen 80/Listen 8080/g' /etc/apache2/ports.conf && \
    sed -i 's/<VirtualHost \*:80>/<VirtualHost *:8080>/g' /etc/apache2/sites-available/000-default.conf

# 9. VERIFICAR CONFIGURAÇÃO (debug)
RUN echo "=== Verificando configuração Apache ===" && \
    cat /etc/apache2/ports.conf | grep Listen && \
    cat /etc/apache2/sites-available/000-default.conf | head -n 5

# 10. EXPOR A PORTA
EXPOSE 8080

# 11. COMANDO PADRÃO
CMD ["apache2-foreground"]
```


