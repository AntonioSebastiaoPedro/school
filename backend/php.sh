#!/usr/bin/env bash
# Atalho para correr um comando PHP descartável no contentor (usa a stack de desenvolvimento).
# Uso: ./php.sh php artisan about | ./php.sh vendor/bin/pest
# Com pest/phpunit usa sempre a base school_test, nunca a de desenvolvimento. Preferir `make test`.
cd "$(dirname "$0")/.." || exit 1
ENVS=(-e DB_DATABASE=school)
case "$*" in
  *pest*|*phpunit*) ENVS=(-e APP_ENV=testing -e DB_DATABASE=school_test -e SESSION_DRIVER=array -e CACHE_STORE=array -e MAIL_MAILER=array -e QUEUE_CONNECTION=sync -e BCRYPT_ROUNDS=4) ;;
esac
exec docker compose -f compose.yml -f compose.dev.yml run --rm -T --no-deps "${ENVS[@]}" app "$@"
