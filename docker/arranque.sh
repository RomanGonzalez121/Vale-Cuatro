#!/bin/sh
# Lo primero que corre cuando el contenedor arranca: deja el sitio listo y después prende los procesos.
# Si algo de acá falla, el contenedor no arranca y el error queda en el registro: es preferible a un
# sitio prendido contra una base a medio migrar.
set -e

cd /var/www/html

# Render dice en qué dirección quedó publicado el servicio. De ahí salen los links que arma el sitio
# (el de invitación, por ejemplo) y adónde se conecta el navegador para el tiempo real.
export APP_URL="${APP_URL:-${RENDER_EXTERNAL_URL:-http://localhost:${PORT}}}"

if [ -z "${REVERB_PUBLICO_HOST:-}" ] && [ -n "${RENDER_EXTERNAL_HOSTNAME:-}" ]; then
    export REVERB_PUBLICO_HOST="$RENDER_EXTERNAL_HOSTNAME"
    export REVERB_PUBLICO_PORT=443
    export REVERB_PUBLICO_SCHEME=https
fi

# La clave del sitio. Render la sortea como 32 bytes en base64, y Laravel la espera con "base64:" adelante.
case "${APP_KEY:-}" in
    '') echo 'Falta APP_KEY: sin esa clave el sitio no puede cifrar las sesiones.' >&2; exit 1 ;;
    base64:*) ;;
    *) export APP_KEY="base64:${APP_KEY}" ;;
esac

if ! php -r 'exit(strlen((string) base64_decode(substr((string) getenv("APP_KEY"), 7), true)) === 32 ? 0 : 1);'; then
    echo 'APP_KEY no es una clave de 32 bytes en base64: generá una con "php artisan key:generate --show".' >&2
    exit 1
fi

# Las tres claves de Reverb salen de la del sitio, cada una con su propia mezcla: así no hay que cargar
# nada más, no cambian entre un arranque y otro, y conocer la pública no dice nada de la secreta.
# Van en letras y números porque dos de ellas viajan dentro de una dirección.
derivada() {
    printf '%s:%s' "$1" "$APP_KEY" | sha256sum | cut -c1-"$2"
}

export REVERB_APP_ID="${REVERB_APP_ID:-$(derivada reverb-id 12)}"
export REVERB_APP_KEY="${REVERB_APP_KEY:-$(derivada reverb-clave 32)}"
export REVERB_APP_SECRET="${REVERB_APP_SECRET:-$(derivada reverb-secreto 48)}"

# nginx escucha en el puerto que pide Render.
sed "s/__PUERTO__/${PORT}/" docker/nginx.conf > /etc/nginx/nginx.conf

# Todo lo de artisan corre como el usuario del sitio, no como root: así lo que escribe lo puede leer PHP.
como_el_sitio() {
    su-exec www-data "$@"
}

# La base al día. Con --force porque en producción artisan pregunta antes de migrar, y acá no hay a quién.
como_el_sitio php artisan migrate --force

# La cuenta que administra el sitio, si en el servicio están cargados ADMIN_EMAIL y ADMIN_PASSWORD.
# El comando la crea la primera vez y después la deja como está. Si falla (una contraseña corta, por
# ejemplo) lo dice en el registro, y el sitio arranca igual: sin panel, pero andando.
como_el_sitio php artisan administrador:crear || echo 'La cuenta de administración no se pudo dejar lista.' >&2

# La configuración, leída una vez y guardada ya resuelta. Se hace acá y no al armar la imagen porque
# sale de las variables del servicio. Las rutas y las vistas ya vienen resueltas en la imagen.
como_el_sitio php artisan config:cache

exec supervisord -c docker/supervisord.conf
