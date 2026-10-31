# Maatify Logger — Usage Guide

## Fit, requirements and non-goals

Use `maatify/logger` when you want a PSR-3 logger that writes time-partitioned files and masks secrets before they are written. Requirements are in [README.md](../../README.md#requirements). It is not a log shipper, rotator or retention tool. The complete contract is [LOGGER_PACKAGE_REFERENCE.md](../../LOGGER_PACKAGE_REFERENCE.md).

Logs are written under the root of the consuming project, in `logs/YYYY/MM/DD/HH/`.

## Capability map

| Capability | Walkthrough | Example |
|---|---|---|
| Log through the static facade | [1](#1-facade-logging) | [01-facade.php](../../examples/01-facade.php) |
| Use as a PSR-3 logger | [2](#2-psr-3-logger) | [02-psr3.php](../../examples/02-psr3.php) |
| Log exceptions with context | [3](#3-exceptions) | [03-exceptions.php](../../examples/03-exceptions.php) |
| Redact secrets and customize masking | [4](#4-redaction) | [04-redaction.php](../../examples/04-redaction.php) |

## 1. Facade logging

Input: a message (string, array or `Throwable`) and optional log file name. Public call: `LoggerService::info()` and the other level methods, or `LoggerService::log($level, ...)`. Result: one entry in the current hour's file. Boundary: with Monolog the file is always `app_...log`; the file name only routes in the fallback.

## 2. PSR-3 logger

Input: a PSR-3 level and message with context. Public call: `(new Logger())->error('...', [...])` or any PSR-3 consumer receiving a `Psr\Log\LoggerInterface`. Result: an entry with the context. Boundary: an unknown level throws `Psr\Log\InvalidArgumentException`.

## 3. Exceptions

Input: a `Throwable` and optional extra context. Public call: `LoggerService::logException($e, 'system/errors', 'error', ['requestId' => 'abc'])`. Result: an `'Exception captured'` entry with message, file, line, code and trace, scrubbed of secrets. Boundary: only the documented fields are logged.

## 4. Redaction

Input: context containing secrets. Public call: nothing extra for the defaults; `Logger::setSensitiveKeys([...])` replaces the key list and `Logger::setRedactor(fn (array $c): array => ...)` adds a hook. Result: `***REDACTED***` for sensitive keys and scrubbed strings. Boundary: only known shapes are masked, including values reached through objects (`JsonSerializable`, `Stringable`, public properties); add your own keys for application-specific secrets. A hook's output is masked again, so it cannot re-introduce what the built-in rules mask.

## Cleaning old logs

`LoggerCleanupService` deletes files older than a number of days or hours. Pass the log directory explicitly (`new LoggerCleanupService('/path/to/project/logs')`); its default does not match where the logger writes. Deletion is permanent.
