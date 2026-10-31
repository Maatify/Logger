<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use Maatify\Logger\LoggerService;
use Psr\Log\LogLevel;

try {
    throw new RuntimeException('Unauthorized access');
} catch (Throwable $e) {
    LoggerService::logException($e, 'security/errors', LogLevel::CRITICAL, ['requestId' => 'abc123']);
}

echo "exceptions example ok\n";
