
#!/usr/bin/env bash
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

echo "==> Devolviendo la propiedad a www-data"
chown -R www-data:www-data .
chmod -R 775 storage bootstrap/cache

echo "==> Recargando PHP-FPM"
systemctl reload php8.3-fpm 2>/dev/null || echo "   (omitido)"

echo "==> Despliegue completado"
