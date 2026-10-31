<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use Maatify\Logger\LoggerService;
use Psr\Log\LogLevel;

LoggerService::info('User login successful', 'auth/logs');
LoggerService::debug(['userId' => 123, 'amount' => 99.99], 'payments/debug');
LoggerService::log(LogLevel::CRITICAL, 'Out of memory!', 'system/critical');

echo "facade example ok\n";
