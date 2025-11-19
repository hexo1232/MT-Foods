# Usar imagem oficial do PHP com Apache
FROM php:8.4-apache

# Instalar extensões necessárias
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copiar todos os arquivos do projeto para o container
COPY . /var/www/html/

# Ajustar permissões
RUN chown -R www-data:www-data /var/www/html

# Expôr a porta padrão do Apache (não precisa mexer, o Railway mapeará automaticamente)
EXPOSE 8080

# Comando padrão para rodar o Apache em foreground
CMD ["apache2-foreground"]
