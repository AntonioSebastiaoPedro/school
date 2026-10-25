#!/usr/bin/env bash
# Cópia da base de dados, com retenção de 30 dias.
set -euo pipefail
COMPOSE="${COMPOSE:-docker compose -f compose.yml -f compose.prod.yml}"
mkdir -p backups
stamp="$(date +%F-%H%M)"
$COMPOSE exec -T postgres sh -c 'pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB"' | gzip > "backups/db-$stamp.sql.gz"
find backups -name 'db-*.sql.gz' -mtime +30 -delete
echo "backups/db-$stamp.sql.gz"
