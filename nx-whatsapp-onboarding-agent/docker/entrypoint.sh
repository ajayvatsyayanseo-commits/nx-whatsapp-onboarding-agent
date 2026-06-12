#!/usr/bin/env sh
set -eu

cd "${APP_DIR:-/var/www/html}"

if [ -f artisan ]; then
  php artisan config:cache || true
  php artisan route:cache || true
fi

case "${1:-web}" in
  web)
    exec sh -c "php-fpm -D && nginx -g 'daemon off;'"
    ;;
  worker)
    if [ ! -f artisan ]; then
      echo "artisan not found; worker cannot start" >&2
      exit 1
    fi
    exec php artisan queue:work "${QUEUE_CONNECTION:-redis}" --queue="${WHATSAPP_ONBOARDING_QUEUE:-whatsapp-onboarding}" --sleep=1 --tries=3 --timeout=120
    ;;
  migrate)
    if [ ! -f artisan ]; then
      echo "artisan not found; migrations cannot run" >&2
      exit 1
    fi
    exec php artisan migrate --force
    ;;
  *)
    exec "$@"
    ;;
esac
