<?php

declare(strict_types=1);

/**
 * Release Artifact Verification for exact target X (CI Workflow Standard §2.5).
 * Usage: php scripts/verify-release-artifact.php <X>   e.g. 5.2.0 or 1.0.0-rc.1
 * Verifies the committed tree (git archive of HEAD), prints target, SHA and per-check outcome, fails closed.
 * Deterministic structural facts only: README/SECURITY wording still needs the semantic review the standard requires.
 */

$target = $argv[1] ?? '';
if (preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $target) !== 1) {
    fwrite(STDERR, "Usage: verify-release-artifact.php <exact SemVer target>\n");
    exit(2);
}

$root = dirname(__DIR__);
$sha = trim((string) shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse HEAD'));
echo "Target: $target\nCandidate SHA: $sha\n";

$tmp = sys_get_temp_dir() . '/maatify-release-' . bin2hex(random_bytes(4));
mkdir($tmp);
exec('git -C ' . escapeshellarg($root) . ' archive HEAD | tar -x -C ' . escapeshellarg($tmp), $o, $code);
if ($code !== 0) {
    fwrite(STDERR, "git archive failed\n");
    exit(1);
}

$results = [];
$check = static function (string $name, bool $ok, string $detail = '') use (&$results): void {
    $results[] = [$name, $ok, $detail];
    echo ($ok ? 'PASS' : 'FAIL') . "  $name" . ($detail !== '' ? " — $detail" : '') . "\n";
};
$read = static fn(string $f): string => is_file("$tmp/$f") ? (string) file_get_contents("$tmp/$f") : '';

// Manifest
$composer = json_decode($read('composer.json'), true) ?: [];
$check('composer.json has no committed version field', !isset($composer['version']));
$check('composer.json package name is maatify/logger', ($composer['name'] ?? '') === 'maatify/logger');
$check('composer.lock is not distributed', !is_file("$tmp/composer.lock"));

// Required release-facing files (LIBRARY_PRESENTATION_STANDARD §6)
foreach (['README.md', 'LOGGER_PACKAGE_REFERENCE.md', 'CHANGELOG.md', 'SECURITY.md', 'CONTRIBUTING.md', 'CODE_OF_CONDUCT.md', 'LICENSE', 'composer.json', 'llms.txt', 'docs/guides/USAGE_GUIDE.md'] as $f) {
    $check("required file present: $f", is_file("$tmp/$f"));
}
$check('examples/ present and non-empty', is_dir("$tmp/examples") && (glob("$tmp/examples/*") ?: []) !== []);

// CHANGELOG target allocation
$changelog = $read('CHANGELOG.md');
$quoted = preg_quote($target, '/');
$check('CHANGELOG has undated section for target', preg_match('/^## \[' . $quoted . '\]\s*$/m', $changelog) === 1);
$check('CHANGELOG target section is not dated', preg_match('/^## \[' . $quoted . '\] - /m', $changelog) !== 1);
$unreleased = preg_match('/^## \[Unreleased\]\s*\n(.*?)(?=^## \[|\z)/ms', $changelog, $m) === 1 ? trim($m[1]) : '';
$check('[Unreleased] holds no target-allocated changes', $unreleased === '', $unreleased === '' ? '' : 'non-empty: move changes allocated to the target');

// README identity / install target
$readme = $read('README.md');
$check('README shows the exact install command', str_contains($readme, 'composer require maatify/logger:' . $target));
$check('README ends with the canonical Maatify footer', str_ends_with(rtrim($readme), "</div>") && str_contains($readme, '[Built with ❤️ by Maatify.dev — Unified Ecosystem for Modern PHP Libraries](https://www.maatify.dev)'));
$check('README has the canonical Author block', str_contains($readme, 'Engineered by **Mohamed Abdulalim** ([@megyptm](https://github.com/megyptm))<br>'));
$check('README has a single H1 inside the header block', preg_match_all('/^# /m', $readme) === 1);

// SECURITY.md identity badges
$security = $read('SECURITY.md');
$check('SECURITY.md has repository + ecosystem identity badges', str_contains($security, 'github.com/Maatify/Logger') && str_contains($security, 'https://github.com/Maatify)'));

$check('SECURITY.md identifies the exact target', str_contains($security, $target));

// llms.txt structure and links (LIBRARY_PRESENTATION_STANDARD §10.3)
$llms = $read('llms.txt');
$check('llms.txt has exactly one H1', preg_match_all('/^# /m', $llms) === 1);
$check('llms.txt has a blockquote summary', preg_match('/^> .+/m', $llms) === 1);
$badLinks = [];
if (preg_match_all('/\]\(([^)#]+)\)/', $llms, $links) > 0) {
    foreach ($links[1] as $link) {
        if (!preg_match('#^https?://#', $link) && !file_exists("$tmp/" . rtrim($link, '/'))) {
            $badLinks[] = $link;
        }
    }
}
$check('llms.txt links resolve', $badLinks === [], implode(', ', $badLinks));
$check('llms.txt links the Package Reference, Usage Guide and examples', str_contains($llms, '(LOGGER_PACKAGE_REFERENCE.md)') && str_contains($llms, '(docs/guides/USAGE_GUIDE.md)') && str_contains($llms, '(examples/)'));

// Archive exclusions must not drop required files nor include secrets / dev artifacts
$bad = [];
foreach (['.env', 'vendor', 'logs', 'error_logger.log'] as $p) {
    if (file_exists("$tmp/$p")) {
        $bad[] = $p;
    }
}
$check('archive contains no secrets/dev artifacts', $bad === [], implode(', ', $bad));

exec('rm -rf ' . escapeshellarg($tmp));

$failed = array_filter($results, static fn(array $r): bool => !$r[1]);
echo $failed === [] ? "Release Artifact Verification PASSED for $target @ $sha\n" : 'Release Artifact Verification FAILED (' . count($failed) . " check(s)) for $target @ $sha\n";
exit($failed === [] ? 0 : 1);
