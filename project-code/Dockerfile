# Utilisation d'une image PHP officielle avec Apache
FROM php:8.2-apache

# --- DEPENDANCES SYSTEME ---
# Installation des outils nécessaires pour Composer (git, unzip) 
# et des librairies de compression (zlib, libzip)
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install mysqli pdo pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

# --- INSTALLATION DE COMPOSER ---
# On récupère l'exécutable directement depuis l'image officielle de Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# On active le module de réécriture d'Apache (essentiel pour les API REST)
RUN a2enmod rewrite

# Copie du code source dans le conteneur
COPY ./src /var/www/html/

# On s'assure que les permissions sont correctes
RUN chown -R www-data:www-data /var/www/html

# On définit le répertoire de travail
WORKDIR /var/www/html