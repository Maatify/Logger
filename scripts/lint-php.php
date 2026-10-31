<?php

declare(strict_types=1);

// Syntax-check every PHP file owned by the package (runtime, tests, tool scripts). Fails closed.
$root = dirname(__DIR__);
$failed = 0;
$count = 0;

foreach (['src', 'tests', 'scripts'] as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $count++;
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()) . ' 2>&1', $out, $code);
        if ($code !== 0) {
            $failed++;
            fwrite(STDERR, implode("\n", $out) . "\n");
        }
        $out = [];
    }
}

if ($count === 0 || $failed > 0) {
    fwrite(STDERR, "PHP syntax check failed ({$failed} failing, {$count} checked)\n");
    exit(1);
}
echo "PHP syntax OK ({$count} files)\n";
