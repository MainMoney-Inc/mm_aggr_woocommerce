#!/usr/bin/env bash
# Pick the next version, build the installable zip, commit and tag it.
# Usage: scripts/release.sh [patch|minor|major] [--dry-run] [--push]
#   --dry-run  build a zip from the current tree; no version bump, commit or tag
#   --push     push the branch and the tag (the tag push publishes via GitHub Actions)
set -euo pipefail

cd "$(dirname "$0")/.."
bump="patch"; dry=0; push=0
for arg in "$@"; do
  case "$arg" in
    patch|minor|major) bump="$arg" ;;
    --dry-run) dry=1 ;;
    --push) push=1 ;;
    *) echo "Unknown argument: $arg" >&2; exit 2 ;;
  esac
done

plugin="woocommerce-mm-aggr.php"
current="$(sed -n 's/^ \* Version:[[:space:]]*//p' "$plugin" | head -1)"
[[ "$current" =~ ^([0-9]+)\.([0-9]+)\.([0-9]+)$ ]] || { echo "Cannot parse version '$current'" >&2; exit 1; }
major="${BASH_REMATCH[1]}"; minor="${BASH_REMATCH[2]}"; patch="${BASH_REMATCH[3]}"

if [[ $dry -eq 1 ]]; then
  composer install --no-interaction >/dev/null
  vendor/bin/phpunit && vendor/bin/phpstan analyse --no-progress
  node scripts/build.mjs
  bash scripts/build-zip.sh build
  exit 0
fi

branch="$(git rev-parse --abbrev-ref HEAD)"
case "$branch" in develop|main) ;; *) echo "Release from develop or main (on '$branch')" >&2; exit 1 ;; esac
[[ -z "$(git status --porcelain --untracked-files=no)" ]] || { echo "Working tree is not clean; commit or stash first" >&2; exit 1; }

case "$bump" in
  major) next="$((major + 1)).0.0" ;;
  minor) next="${major}.$((minor + 1)).0" ;;
  patch) next="${major}.${minor}.$((patch + 1))" ;;
esac
git rev-parse -q --verify "refs/tags/v${next}" >/dev/null && { echo "Tag v${next} already exists" >&2; exit 1; }

rollback() { git checkout -- . 2>/dev/null || true; }
trap 'status=$?; if [[ $status -ne 0 ]]; then echo "Release failed; reverting version edits" >&2; rollback; fi' EXIT

sed -i "s/^ \* Version:.*/ * Version: ${next}/" "$plugin"
sed -i "s/define('WC_MM_AGGR_VERSION', '[^']*')/define('WC_MM_AGGR_VERSION', '${next}')/" "$plugin"
sed -i "s/^Stable tag:.*/Stable tag: ${next}/" readme.txt

last_tag="$(git describe --tags --abbrev=0 2>/dev/null || true)"
range="${last_tag:+${last_tag}..}HEAD"
notes="$(git log "$range" --no-merges --pretty='- %s' | grep -v '^- chore: release' || true)"
{
  printf '# Changelog\n\n## %s (%s)\n\n%s\n' "$next" "$(date +%F)" "${notes:-- Maintenance release.}"
  tail -n +2 CHANGELOG.md
} > CHANGELOG.md.new && mv CHANGELOG.md.new CHANGELOG.md

composer install --no-interaction >/dev/null
vendor/bin/phpunit
vendor/bin/phpstan analyse --no-progress
node scripts/build.mjs
bash scripts/build-zip.sh build

git add -u
git commit -q -m "chore: release v${next}"
git tag -a "v${next}" -m "v${next}"
trap - EXIT
echo "Released v${next} (zip: build/woocommerce-mm-aggr-${next}.zip)"
if [[ $push -eq 1 ]]; then
  git push origin "$branch" "v${next}"
else
  echo "Publish with: git push origin ${branch} v${next}"
fi
