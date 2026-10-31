<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use Maatify\Logger\Logger;

Logger::setSensitiveKeys(['password', 'token', 'iban']);
Logger::setRedactor(static function (array $context): array {
    unset($context['internalNote']);
    return $context;
});

$masked = Logger::redactContext([
    'user'         => 'ali',
    'password'     => 'hunter2',
    'iban'         => 'EG00 0000',
    'internalNote' => 'drop me',
    'message'      => 'callback failed token=abc123',
]);

if (($masked['password'] ?? null) === 'hunter2' || str_contains(json_encode($masked, JSON_THROW_ON_ERROR), 'abc123')) {
    fwrite(STDERR, "secret leaked\n");
    exit(1);
}

(new Logger())->info('redaction example', $masked);

echo "redaction example ok\n";
