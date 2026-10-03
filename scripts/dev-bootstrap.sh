#!/usr/bin/env bash
# Bring the PROMEX suite up from a cold container.
#
# The runtime image is reset between sessions: PHP, MariaDB and the database are
# gone, while the repo (and install.sql) survive. This script reinstalls the
# runtime, restores the schema, applies migrations, syncs the real aggregator
# catalogue and starts the PHP server on $PORT. Safe to re-run.
set -u

REPO=/workspace/project
PORT="${PORT:-12000}"
DB=promex

echo "== packages =="
if ! command -v php >/dev/null 2>&1; then
    export DEBIAN_FRONTEND=noninteractive
    sudo apt-get update -qq
    sudo apt-get install -y -qq php-cli php-mysql php-mbstring php-xml php-curl \
        php-zip php-bcmath php-gd php-intl mariadb-server
fi

echo "== mariadb =="
sudo mkdir -p /run/mysqld && sudo chown mysql:mysql /run/mysqld
if ! sudo mariadb-admin ping >/dev/null 2>&1; then
    sudo service mariadb start
    for _ in $(seq 1 30); do sudo mariadb-admin ping >/dev/null 2>&1 && break; sleep 1; done
fi

echo "== database =="
sudo mariadb -e "CREATE DATABASE IF NOT EXISTS $DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB'@'127.0.0.1' IDENTIFIED BY '$DB';
CREATE USER IF NOT EXISTS '$DB'@'localhost' IDENTIFIED BY '$DB';
GRANT ALL PRIVILEGES ON $DB.* TO '$DB'@'127.0.0.1';
GRANT ALL PRIVILEGES ON $DB.* TO '$DB'@'localhost';
FLUSH PRIVILEGES;"

tables=$(sudo mariadb -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB';")
if [ "${tables:-0}" -lt 50 ]; then
    echo "  importing install.sql"
    sudo mariadb "$DB" < "$REPO/install.sql"
fi

cd "$REPO/casino"
php artisan migrate --force
php artisan config:clear >/dev/null
php artisan view:clear >/dev/null

echo "== catalogue =="
php artisan casino:sync-catalog --prune --hide-unlinked

echo "== server =="
pkill -f "php -S 0.0.0.0:$PORT" 2>/dev/null
nohup php -S "0.0.0.0:$PORT" -t "$REPO" "$REPO/index.php" > /tmp/promex-server.log 2>&1 &
sleep 3
curl -s -o /dev/null -w "GET / -> %{http_code}\n" --max-time 30 "http://127.0.0.1:$PORT/"
