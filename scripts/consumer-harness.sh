#!/usr/bin/env bash
# Consumer Verification Harness: installs the package through Composer into a clean, throw-away
# consumer project (production autoload, no dev dependencies, no test namespace) and exercises the
# public API. Runs twice from clean states. Fails closed.
#
# Local-only escape hatch: HARNESS_COMPOSER_FLAGS (e.g. --ignore-platform-req=php) for machines whose PHP
# is below the security patch floor. CI never sets it.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
FLAGS="${HARNESS_COMPOSER_FLAGS:-}"

# The consumer installs what a distribution archive contains (honours .gitattributes export-ignore),
# built from the committed tree plus tracked working-tree edits (untracked files are not included).
SNAPSHOT="$(mktemp -d)"
trap 'rm -rf "$SNAPSHOT"' EXIT
TREE="$(git -C "$ROOT" stash create 2>/dev/null || true)"
git -C "$ROOT" archive "${TREE:-HEAD}" | tar -x -C "$SNAPSHOT"

run_once() {
    local run="$1"
    local dir
    dir="$(mktemp -d)"

    mkdir -p "$dir/consumer"
    cat > "$dir/consumer/composer.json" <<JSON
{
  "name": "example/consumer",
  "type": "project",
  "minimum-stability": "dev",
  "prefer-stable": true,
  "repositories": [
    { "type": "path", "url": "$SNAPSHOT", "options": { "symlink": false, "versions": { "maatify/logger": "dev-harness" } } }
  ],
  "require": { "maatify/logger": "dev-harness" },
  "config": { "optimize-autoloader": true }
}
JSON

    cat > "$dir/consumer/app.php" <<'PHP'
<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Maatify\Logger\Logger;
use Maatify\Logger\LoggerService;

$logger = new Logger();
$logger->error('callback https://x/cb?sig=SECRET_A failed', ['password' => 'SECRET_B', 'visible' => 'kept']);
$logger->info('Authorization: Bearer SECRET_C');
LoggerService::logException(new RuntimeException('token=SECRET_D'), 'exceptions');
LoggerService::error(['note' => 'array entry', 'api_key' => 'SECRET_E']);

foreach (['Maatify\\Logger\\Tests\\RedactionTest', 'PHPUnit\\Framework\\TestCase'] as $devClass) {
    if (class_exists($devClass)) {
        fwrite(STDERR, "dev class autoloadable in consumer: $devClass\n");
        exit(2);
    }
}
echo "ok\n";
PHP

    echo "== Harness run $run: composer install (clean state)"
    # shellcheck disable=SC2086
    (cd "$dir/consumer" && composer install --no-dev --no-interaction --prefer-dist --no-progress $FLAGS)

    local mode
    mode="$(php -r '$i=json_decode(file_get_contents($argv[1]),true); foreach($i["packages"] as $p){ if($p["name"]==="maatify/logger"){ echo $p["installation-source"] ?? "unknown"; } }' "$dir/consumer/vendor/composer/installed.json")"
    echo "installed maatify/logger via: $mode"
    [ -d "$dir/consumer/vendor/maatify/logger/src" ] || { echo "package not installed" >&2; exit 1; }
    [ ! -d "$dir/consumer/vendor/maatify/logger/tests" ] || { echo "tests directory leaked into consumer install" >&2; exit 1; }

    echo "== Harness run $run: public API workflow"
    (cd "$dir/consumer" && php app.php)

    local logs
    logs="$(find "$dir/consumer/logs" -name '*.log' -type f 2>/dev/null || true)"
    [ -n "$logs" ] || { echo "no log files written under consumer root" >&2; exit 1; }

    if echo "$logs" | xargs grep -l -E 'SECRET_[A-E]' >/dev/null 2>&1; then
        echo "secret leaked into consumer log files" >&2
        exit 1
    fi
    echo "$logs" | xargs grep -q 'kept' || { echo "expected non-secret content missing" >&2; exit 1; }
    rm -rf "$dir"
    echo "== Harness run $run OK"
}

run_once 1
run_once 2
echo "Consumer Verification Harness OK (2 clean runs)"
