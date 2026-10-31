<?php

declare(strict_types=1);

// Fail on trailing whitespace in tracked text files.
exec('git ls-files -z', $files, $code);
if ($code !== 0) {
    fwrite(STDERR, "git ls-files failed\n");
    exit(1);
}
$bad = [];
foreach (explode("\0", $files[0] ?? '') as $path) {
    if ($path === '' || !is_file($path) || str_ends_with($path, '.lock') || $path === 'README.md') {
        continue;
    }
    $content = (string) file_get_contents($path);
    if (str_contains($content, "\0")) {
        continue; // binary
    }
    foreach (explode("\n", $content) as $i => $line) {
        if (preg_match('/[ \t\r]+$/', $line) === 1) {
            $bad[] = $path . ':' . ($i + 1);
        }
    }
}
if ($bad !== []) {
    fwrite(STDERR, "Trailing whitespace found:\n" . implode("\n", $bad) . "\n");
    exit(1);
}
echo "Whitespace OK\n";
