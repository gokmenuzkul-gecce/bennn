#!/usr/bin/env bash
#
# Point the casino at a permanent public host.
#
# The vendors call back to whatever we register, so the callback base must be a
# real, resolvable HTTPS host — not the temporary sandbox. Run this once the
# domain is live (DNS -> this server) and it rewrites .env, clears the config
# cache and prints the exact callback URLs to hand to each vendor portal.
#
# Usage: deploy/set-callback-domain.sh https://casino.example.com
set -euo pipefail

DOMAIN="${1:-}"
if [[ -z "$DOMAIN" ]]; then
    echo "Kullanim: $0 https://<kalici-alan-adi>" >&2
    exit 1
fi
if [[ "$DOMAIN" != https://* ]]; then
    echo "HATA: alan adi https:// ile baslamali (saglayicilar HTTPS bekliyor): $DOMAIN" >&2
    exit 1
fi

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$ROOT/.env"
[[ -f "$ENV_FILE" ]] || { echo "HATA: $ENV_FILE yok" >&2; exit 1; }

DOMAIN="${DOMAIN%/}"

set_env() {
    local key="$1" value="$2"
    if grep -qE "^${key}=" "$ENV_FILE"; then
        sed -i "s|^${key}=.*|${key}=${value}|" "$ENV_FILE"
    else
        printf '%s=%s\n' "$key" "$value" >> "$ENV_FILE"
    fi
}

set_env APP_URL "$DOMAIN"
set_env CASINO_CALLBACK_BASE "$DOMAIN"

php "$ROOT/artisan" config:clear >/dev/null 2>&1 || true

echo "Callback tabani guncellendi: $DOMAIN"
echo
echo "== Saglayici portallarina kaydettirilecek URL'ler =="
php "$ROOT/artisan" casino:integration-status | grep -E "callback URL|^Aggregator|^Gregmorn|^OroPlay|^smpl|alternatif" || true
