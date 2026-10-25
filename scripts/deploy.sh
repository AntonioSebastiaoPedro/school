#!/usr/bin/env bash
set -euo pipefail
DC="docker compose -f compose.yml -f compose.prod.yml"
# www-data: os ficheiros criados (logs, cache) têm de ser escrevíveis pelo php-fpm.
ART="exec -T -u www-data app php artisan"
# No primeiro deploy ainda não há base de dados para guardar.
if $DC ps --status running --services 2>/dev/null | grep -qx postgres; then ./scripts/backup.sh; fi
$DC up -d --build
$DC $ART migrate --force
$DC $ART db:seed --class=GeographySeeder --force   # catálogos; é idempotente
$DC $ART optimize
