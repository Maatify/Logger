#!/usr/bin/env bash
# Smoke-executes every example against a clean consumer install (production autoload, no dev dependencies),
# so example logs land in a throw-away consumer root. Fails closed.
# Local-only: HARNESS_COMPOSER_FLAGS (e.g. --ignore-platform-req=php) for PHP below the patch floor.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
FLAGS="${HARNESS_COMPOSER_FLAGS:-}"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

TREE="$(git -C "$ROOT" stash create 2>/dev/null || true)"
mkdir "$WORK/snapshot" "$WORK/consumer"
git -C "$ROOT" archive "${TREE:-HEAD}" | tar -x -C "$WORK/snapshot"

cat > "$WORK/consumer/composer.json" <<JSON
{
  "name": "example/examples-consumer",
  "type": "project",
  "minimum-stability": "dev",
  "prefer-stable": true,
  "repositories": [
    { "type": "path", "url": "$WORK/snapshot", "options": { "symlink": false, "versions": { "maatify/logger": "dev-examples" } } }
  ],
  "require": { "maatify/logger": "dev-examples" },
  "config": { "optimize-autoloader": true }
}
JSON

# shellcheck disable=SC2086
(cd "$WORK/consumer" && composer install --no-dev --no-interaction --prefer-dist --no-progress $FLAGS)

[ -d "$WORK/snapshot/examples" ] || { echo "examples/ missing from the distribution archive" >&2; exit 1; }
count=0
for example in "$WORK"/snapshot/examples/[0-9]*.php; do
    [ -e "$example" ] || { echo "no examples found" >&2; exit 1; }
    echo "== $(basename "$example")"
    (cd "$WORK/consumer" && MAATIFY_EXAMPLES_AUTOLOAD="$WORK/consumer/vendor/autoload.php" php "$example")
    count=$((count + 1))
done
echo "Examples OK ($count executed)"
