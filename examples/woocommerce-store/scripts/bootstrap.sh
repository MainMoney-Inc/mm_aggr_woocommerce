#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PLUGIN_ROOT="$(cd "$ROOT/../.." && pwd)"
WP="$ROOT/wordpress"
cd "$ROOT"
if [[ -f .env ]]; then
  set -a
  # shellcheck disable=SC1091
  source .env
  set +a
fi

if ! command -v wp >/dev/null 2>&1; then
  echo "WP-CLI (wp) is required. https://wp-cli.org/" >&2
  exit 1
fi

mkdir -p "$WP"
if [[ ! -f "$WP/wp-load.php" ]]; then
  wp core download --path="$WP"
fi

if [[ ! -f "$WP/wp-config.php" ]]; then
  wp config create --path="$WP" \
    --dbname="${DB_NAME:-mm_woo_example}" \
    --dbuser="${DB_USER:-root}" \
    --dbpass="${DB_PASSWORD:-}" \
    --dbhost="${DB_HOST:-127.0.0.1}"
  wp db create --path="$WP" || true
fi

if ! wp core is-installed --path="$WP" >/dev/null 2>&1; then
  wp core install --path="$WP" \
    --url="http://127.0.0.1:${PORT:-8081}" \
    --title="${WP_TITLE:-MainMoney WooCommerce Example}" \
    --admin_user="${WP_ADMIN_USER:-admin}" \
    --admin_password="${WP_ADMIN_PASSWORD:-admin}" \
    --admin_email="${WP_ADMIN_EMAIL:-admin@example.test}" \
    --skip-email
fi

wp plugin install woocommerce --path="$WP" --activate
mkdir -p "$WP/wp-content/plugins"
ln -sfn "$PLUGIN_ROOT" "$WP/wp-content/plugins/mm-aggr-woocommerce"
(
  cd "$PLUGIN_ROOT"
  if [[ ! -d vendor ]]; then
    composer install
  fi
)
wp plugin activate mm-aggr-woocommerce --path="$WP"
echo "WooCommerce example is bootstrapped in $WP"
