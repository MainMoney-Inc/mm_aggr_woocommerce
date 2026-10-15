#!/usr/bin/env bash
# Build an installable plugin zip (production vendor/ included, dev files excluded).
# Usage: scripts/build-zip.sh [output-dir]   (default: ./build)
set -euo pipefail

cd "$(dirname "$0")/.."
slug="woocommerce-mm-aggr"
out="${1:-build}"
version="$(sed -n 's/^ \* Version:[[:space:]]*//p' woocommerce-mm-aggr.php | head -1)"
stage="$(mktemp -d)"
trap 'rm -rf "$stage"' EXIT

mkdir -p "$stage/$slug" "$out"
rsync -a --exclude-from=- ./ "$stage/$slug/" <<'EXCLUDE'
.git
.git/
.github/
.cursor/
.gitignore
node_modules/
vendor/
tests/
examples/
docs/
build/
scripts/
AGENTS.md
CLAUDE.md
CONVENTIONS.md
CONTRIBUTING.md
CLA.md
package.json
package-lock.json
phpstan.neon.dist
phpunit.xml.dist
composer.lock
*.map
*.ts
.gitkeep
.phpunit.result.cache
EXCLUDE

(cd "$stage/$slug" && composer install --no-dev --no-interaction --optimize-autoloader --classmap-authoritative)
(cd "$stage" && zip -qr "$OLDPWD/$out/$slug-$version.zip" "$slug")
echo "Built $out/$slug-$version.zip"
