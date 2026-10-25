#!/usr/bin/env bash
# Uso: restore.sh backups/db-AAAA-MM-DD-HHMM.sql.gz
# Restaura por cima da base actual: pare a aplicação antes (make prod-down) se precisar de uma base limpa.
set -euo pipefail
COMPOSE="${COMPOSE:-docker compose -f compose.yml -f compose.prod.yml}"
case "${1:-}" in
  *.sql.gz) gunzip -c "$1" | $COMPOSE exec -T postgres sh -c 'psql -U "$POSTGRES_USER" "$POSTGRES_DB"' ;;
  *) echo "Uso: restore.sh backups/db-AAAA-MM-DD-HHMM.sql.gz" >&2; exit 1 ;;
esac
