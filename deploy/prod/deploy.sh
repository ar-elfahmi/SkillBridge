#!/usr/bin/env bash
# Redeploy manual di server. Jalankan dari folder ini: ./deploy.sh [IMAGE_TAG]
# Alur: backup DB -> pull image -> migrate -> up -d -> cek /up -> rollback tag jika gagal.
set -euo pipefail
cd "$(dirname "$0")"

[ -f .env ] || { echo "File .env belum ada (salin dari .env.example)."; exit 1; }

get_tag() { grep -E '^IMAGE_TAG=' .env | cut -d= -f2-; }
set_tag() { sed -i "s|^IMAGE_TAG=.*|IMAGE_TAG=$1|" .env; }

PREV_TAG="$(get_tag)"
NEW_TAG="${1:-$PREV_TAG}"
set_tag "$NEW_TAG"

compose() { docker compose "$@"; }
rollback() {
  echo "Deploy gagal. Kembali ke tag: $PREV_TAG"
  set_tag "$PREV_TAG"
  compose up -d || true
  exit 1
}

mkdir -p backups
if compose ps --status running --services 2>/dev/null | grep -qx postgres; then
  # shellcheck disable=SC1091
  . ./.env
  OUT="backups/db-$(date +%Y%m%d-%H%M%S).sql.gz"
  compose exec -T postgres pg_dump -U "$DB_USERNAME" "$DB_DATABASE" | gzip > "$OUT"
  echo "Backup DB: $OUT"
fi

compose pull || rollback
compose up -d postgres redis
compose run --rm --no-deps app php artisan migrate --force || rollback
compose up -d --remove-orphans

for _ in $(seq 1 30); do
  if compose ps web --format '{{.Health}}' | grep -q '^healthy$'; then
    echo "OK: $NEW_TAG berjalan."
    docker image prune -f >/dev/null
    exit 0
  fi
  sleep 3
done
rollback
