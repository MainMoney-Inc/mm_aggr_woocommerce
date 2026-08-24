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
if [[ ! -d "$WP" ]]; then
  echo "Run ./scripts/bootstrap.sh first." >&2
  exit 1
fi
wp server --path="$WP" --host=127.0.0.1 --port="${PORT:-8081}"
