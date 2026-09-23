#!/usr/bin/env bash
# bin/deploy.sh — corre EN EL SERVIDOR, lo invoca GitHub Actions.
# Sirve igual en pruebas (Nginx + php-fpm) y en produccion (Apache + mod_php).

set -euo pipefail
export COMPOSER_ALLOW_SUPERUSER=1

cd "$(dirname "$0")/.."
echo "==> Desplegando en $(pwd) — $(date '+%Y-%m-%d %H:%M:%S')"

trap 'php artisan up >/dev/null 2>&1 || true' EXIT

echo "==> Modo mantenimiento"
php artisan down --retry=15 || true

echo "==> Dependencias PHP"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "==> Migraciones"
php artisan migrate --force

echo "==> Enlace de storage"
php artisan storage:link 2>/dev/null || true

echo "==> Regenerando caches"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Reiniciando trabajadores de cola"
php artisan queue:restart || true

# El deploy corre como root: sin esto www-data no puede escribir y el sitio
# revienta con 500 en la siguiente subida de documento.
echo "==> Devolviendo la propiedad a www-data"
chown -R www-data:www-data .
# ug+rwX: la X mayuscula da permiso de ejecucion SOLO a las carpetas.
chmod -R ug+rwX storage bootstrap/cache

# Recarga solo lo que este corriendo en este servidor.
echo "==> Recargando servicios web"
systemctl is-active --quiet php8.3-fpm && systemctl reload php8.3-fpm && echo "   php-fpm" || true
systemctl is-active --quiet apache2    && systemctl reload apache2    && echo "   apache2" || true
systemctl is-active --quiet nginx      && systemctl reload nginx      && echo "   nginx"   || true

echo "==> Despliegue completado"
