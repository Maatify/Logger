<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use Maatify\Logger\Logger;
use Psr\Log\LoggerInterface;

function handle(LoggerInterface $logger): void
{
    $logger->warning('Cache miss ratio high', ['ratio' => 0.42]);
}

handle(new Logger());

try {
    (new Logger())->log('not-a-level', 'x');
} catch (Psr\Log\InvalidArgumentException $e) {
    echo "unknown level rejected\n";
}

echo "psr3 example ok\n";
