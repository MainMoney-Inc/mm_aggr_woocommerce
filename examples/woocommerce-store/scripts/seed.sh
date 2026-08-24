#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WP="$ROOT/wordpress"
cd "$ROOT"
if [[ -f .env ]]; then
  set -a
  # shellcheck disable=SC1091
  source .env
  set +a
fi

TEST_VALUE="no"
if [[ "${MM_TEST:-true}" == "true" || "${MM_TEST:-true}" == "1" || "${MM_TEST:-true}" == "yes" ]]; then
  TEST_VALUE="yes"
fi

python3 - "$TEST_VALUE" <<'PY' | wp option update woocommerce_mm_aggr_settings --path="$WP" --format=json
import json, os, sys
print(json.dumps({
    "enabled": "yes",
    "title": "MainMoney",
    "client_id": os.environ.get("MM_CLIENT_ID", ""),
    "secret": os.environ.get("MM_API_SECRET", ""),
    "test": sys.argv[1],
    "base_uri": os.environ.get("MM_BASE_URI", ""),
    "webhook_secret": os.environ.get("MM_WEBHOOK_SECRET", ""),
}))
PY

wp option update woocommerce_currency USD --path="$WP" || true

create_product() {
  local name="$1"
  local price="$2"
  local sku="$3"
  if wp wc product list --user=1 --path="$WP" --sku="$sku" --format=ids 2>/dev/null | grep -q '[0-9]'; then
    return
  fi
  wp wc product create --user=1 --path="$WP" --name="$name" --type=simple --regular_price="$price" --sku="$sku" --status=publish
}

create_product "Demo T-shirt" "25.00" "DEMO-SHIRT" || true
create_product "Demo coffee" "5.00" "DEMO-COFFEE" || true
create_product "Demo bundle" "10.00" "DEMO-BUNDLE" || true

wp rewrite structure '/%postname%/' --path="$WP"
echo "Seeded gateway settings and demo products."
