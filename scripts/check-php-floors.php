<?php

declare(strict_types=1);

// PHP security patch floors (owned by the Maatify Composer Package Standard §15.1.1) verified per
// CI Workflow Standard §7.1: (1) the real interpreter patch, (2) constraint boundaries via Composer's parser.
use Composer\Semver\Semver;

require dirname(__DIR__) . '/vendor/autoload.php';

const FLOORS = ['8.4' => '8.4.27', '8.5' => '8.5.12'];

$composer = json_decode((string) file_get_contents(dirname(__DIR__) . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
$constraint = (string) ($composer['require']['php'] ?? '');
if ($constraint === '') {
    fwrite(STDERR, "composer.json has no require.php\n");
    exit(1);
}

$errors = [];
$minor = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
echo 'Installed PHP: ' . PHP_VERSION . "\n";
if (isset(FLOORS[$minor]) && version_compare(PHP_VERSION, FLOORS[$minor], '<')) {
    $errors[] = 'Interpreter ' . PHP_VERSION . ' is below the ' . $minor . ' floor ' . FLOORS[$minor];
}

foreach (FLOORS as $m => $floor) {
    [$maj, $min] = array_map('intval', explode('.', $m));
    [, , $patch] = array_map('intval', explode('.', $floor));
    $below = "$maj.$min." . ($patch - 1);
    $next = "$maj." . ($min + 1) . '.0';
    if (!Semver::satisfies($floor, $constraint)) {
        $errors[] = "$floor must be accepted";
    }
    if (Semver::satisfies($below, $constraint)) {
        $errors[] = "$below must be rejected";
    }
    if (Semver::satisfies($next, $constraint) && !isset(FLOORS["$maj." . ($min + 1)])) {
        $errors[] = "$next (minor without floor) must be rejected";
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}
echo "PHP floor checks OK for constraint: $constraint\n";
