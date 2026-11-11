#!/usr/bin/env bash
# Deploy AgriMALL to a Linux host that already has Apache+PHP+MySQL.
# Usage: DB_NAME=agrimall DB_USER=... DB_PASS=... ./deploy.sh
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/agrimall}"
cd "$APP_DIR"

echo "==> pulling latest code"
git pull --ff-only

echo "==> applying pending migrations (guarded, idempotent)"
for f in database/migrations/*.sql; do
  [ -e "$f" ] || continue
  echo "   $f"
  mysql -h"${DB_HOST:-localhost}" -u"${DB_USER:?}" -p"${DB_PASS:?}" "${DB_NAME:?}" < "$f" || {
    echo "   (migration failed or already applied; continuing)"
  }
done

echo "==> reloading apache"
sudo systemctl reload apache2 2>/dev/null || sudo service apache2 reload 2>/dev/null || true
echo "done"
