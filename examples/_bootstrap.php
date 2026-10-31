<?php

declare(strict_types=1);

// Examples use the production Composer autoload of the consuming project.
$autoload = getenv('MAATIFY_EXAMPLES_AUTOLOAD') ?: dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Composer autoload not found: $autoload\n");
    exit(1);
}
require $autoload;
