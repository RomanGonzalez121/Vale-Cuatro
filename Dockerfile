# El sitio entero en una sola imagen. El plan gratis de Render da un solo contenedor, y adentro corren
# cuatro cosas: la web (nginx y PHP), el tiempo real (Reverb), la cola y las tareas programadas.
# Quién arranca cada una y la mantiene viva está en docker/supervisord.conf.

# --- Estilos y scripts, compilados con Vite. De esta etapa a la imagen final pasa solo public/build.
FROM node:22-alpine AS vista

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

# Tailwind arma los estilos leyendo las vistas y el JavaScript: necesita el código, no solo resources/css.
COPY . .
RUN npm run build

# --- La imagen que se publica.
FROM php:8.3-fpm-alpine

# nginx atiende el puerto, supervisor mantiene vivos los procesos y su-exec les saca los permisos de root.
# pdo_mysql es la base; pcntl lo usan la cola y Reverb para apagarse bien; opcache evita leer el código en cada pedido.
RUN apk add --no-cache nginx supervisor su-exec \
    && docker-php-ext-install pdo_mysql pcntl opcache \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-vale-cuatro.ini"
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-vale-cuatro.conf
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

# Primero las dependencias: mientras no cambie composer.lock, Docker reutiliza esta capa.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .
COPY --from=vista /app/public/build public/build

# El cargador de clases ya ordenado y las carpetas donde el sitio escribe.
RUN composer dump-autoload --optimize --no-dev --no-scripts \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache /var/cache/opcache \
    && chown -R www-data:www-data storage bootstrap/cache /var/cache/opcache \
    && chmod +x docker/arranque.sh docker/tareas.sh

# Lo que no depende de dónde se publique se deja resuelto acá, y no en cada arranque: la lista de
# paquetes, las rutas y las vistas. Corre como el usuario del sitio, y de paso deja compilado en disco
# el código de Laravel (docker/php.ini), que es lo que más tarda en un procesador chico.
RUN su-exec www-data php artisan package:discover --ansi \
    && su-exec www-data php artisan route:cache \
    && su-exec www-data php artisan view:cache

# Render avisa por PORT en qué puerto espera al sitio. 10000 es el que usa si no se le dice otro.
ENV PORT=10000
EXPOSE 10000

CMD ["docker/arranque.sh"]
