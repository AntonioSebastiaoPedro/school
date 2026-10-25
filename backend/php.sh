#!/usr/bin/env bash
# Corre um comando PHP/Composer/Artisan dentro do contentor (PHP 8.4 com pdo_pgsql).
# Uso: ./php.sh php artisan migrate | ./php.sh vendor/bin/pest
# Os testes usam sempre a base school_test, nunca a de desenvolvimento.
cd "$(dirname "$0")/.." || exit 1
DB=school
case "$*" in *pest*|*phpunit*|*--env=testing*) DB=school_test ;; esac
exec docker compose run --rm -T --no-deps --user "$(id -u):$(id -g)" \
  -e COMPOSER_HOME=/tmp/c -e HOME=/tmp -e DB_DATABASE="$DB" php "$@"
