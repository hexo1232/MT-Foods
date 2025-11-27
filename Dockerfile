# Usar imagem oficial do PHP com Apache
FROM php:8.4-apache

# ----------------------------------------------------
# 1. INSTALAR DEPENDÊNCIAS DO SISTEMA
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    libssl-dev \
    default-libmysqlclient-dev \
    && rm -rf /var/lib/apt/lists/*

# 2. INSTALAR EXTENSÕES PHP
RUN docker-php-ext-install pdo_mysql zip

# 3. INSTALAR O COMPOSER
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# ----------------------------------------------------
# 4. COPIAR OS ARQUIVOS DO PROJETO
COPY . /var/www/html/

# Define o diretório de trabalho padrão
WORKDIR /var/www/html/

# 5. EXECUTAR COMPOSER INSTALL
RUN composer install --no-dev --optimize-autoloader

# 6. AJUSTAR PERMISSÕES
RUN chown -R www-data:www-data /var/www/html

# 7. HABILITAR mod_rewrite (importante para Laravel/frameworks PHP)
RUN a2enmod rewrite

# 8. EXPOR A PORTA (Railway usa a variável PORT)
EXPOSE ${PORT:-8080}

# 9. CRIAR SCRIPT DE INICIALIZAÇÃO
RUN echo '#!/bin/bash\n\
set -e\n\
PORT=${PORT:-8080}\n\
sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf\n\
sed -i "s/:80/:$PORT/g" /etc/apache2/sites-available/000-default.conf\n\
exec apache2-foreground' > /usr/local/bin/start-apache.sh \
    && chmod +x /usr/local/bin/start-apache.sh

# 10. COMANDO PADRÃO
CMD ["/usr/local/bin/start-apache.sh"]
