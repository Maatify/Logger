<div align="center">

# Maatify Logger

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://www.maatify.dev/company-profile/maatify_logo_white.svg">
  <source media="(prefers-color-scheme: light)" srcset="https://www.maatify.dev/company-profile/maatify_logo_dark.svg">
  <img alt="Maatify.dev" src="https://www.maatify.dev/company-profile/maatify_logo_dark.svg">
</picture>

[![Status](https://img.shields.io/badge/Status-Published-blue)](CHANGELOG.md)
[![Version](https://img.shields.io/badge/Version-7.0.0-blue)](https://packagist.org/packages/maatify/logger)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.4.27-8892BF)](composer.json)
[![License](https://img.shields.io/badge/License-Proprietary-green)](LICENSE)
[![PHPStan](https://img.shields.io/badge/PHPStan-Level%20Max-4E8CAE)](phpstan.neon)

[![Packagist](https://img.shields.io/badge/Packagist-Distribution-blue)](https://packagist.org/packages/maatify/logger)
[![Monthly Downloads](https://img.shields.io/packagist/dm/maatify/logger)](https://packagist.org/packages/maatify/logger/stats)
[![Total Downloads](https://img.shields.io/packagist/dt/maatify/logger)](https://packagist.org/packages/maatify/logger/stats)
[![Maatify Ecosystem](https://img.shields.io/badge/Maatify-Ecosystem-blueviolet)](https://github.com/Maatify)
[![Install](https://img.shields.io/badge/Install-7.0.0-blue)](#installation)

[![Usage Guide](https://img.shields.io/badge/Docs-Usage%20Guide-informational)](docs/guides/USAGE_GUIDE.md)
[![Examples](https://img.shields.io/badge/Docs-Examples-informational)](examples/)
[![Package Reference](https://img.shields.io/badge/Docs-Package%20Reference-informational)](LOGGER_PACKAGE_REFERENCE.md)
[![Changelog](https://img.shields.io/badge/Docs-Changelog-informational)](CHANGELOG.md)
[![Security](https://img.shields.io/badge/Docs-Security-informational)](SECURITY.md)
[![Contributing](https://img.shields.io/badge/Docs-Contributing-informational)](CONTRIBUTING.md)

`maatify/logger` is a PSR-3 compatible logger for PHP: a Monolog-backed `Logger`, a static `LoggerService` facade, a legacy `RecordLog` entry point, redaction of secrets before anything is written, and a JSON file fallback used only when Monolog is not loadable.

Published release: `7.0.0` (2026-10-31), distributed through [Packagist](https://packagist.org/packages/maatify/logger).

</div>

---

## Key Features

- **PSR-3 logger:** `Maatify\Logger\Logger` implements `Psr\Log\LoggerInterface`.
- **Facade and legacy entry points:** static `LoggerService` methods and `Logger::RecordLog()` for existing code.
- **Secrets are redacted before they are written:** sensitive context keys, `key=value` secrets, `Authorization: Bearer/Basic` values and `Throwable` messages and traces are masked. The list and a custom hook are configurable.
- **Request path only:** the file fallback records `REQUEST_URI` without its query string or fragment; the full URI is an explicit opt-in.
- **Time-partitioned files:** `logs/YYYY/MM/DD/HH/` with a `404` `index.php` guard in each created folder.
- **Never throws on I/O failure in the fallback:** write problems go to an internal error file instead.

## Requirements

[composer.json](composer.json) is the source of truth for requirements and dependencies; this table reflects it.

| Requirement | Constraint |
|---|---|
| PHP | `>=8.4.27 <8.5.0 \|\| >=8.5.12 <8.6.0` |
| Extensions | `ext-json` |
| `monolog/monolog` | `^3.9` |
| `psr/log` | `^3.0` |

## Installation

Install the published release:

```bash
composer require maatify/logger:7.0.0
```

The command resolves through Packagist.

## Quick Usage

```php
use Maatify\Logger\LoggerService;

LoggerService::info('User login successful', 'auth/logs');

try {
    throw new RuntimeException('DB connection failed');
} catch (Throwable $e) {
    LoggerService::logException($e, 'system/errors', context: ['requestId' => 'abc123']);
}
```

Complete, runnable examples: [examples/](examples/). Walkthroughs: [docs/guides/USAGE_GUIDE.md](docs/guides/USAGE_GUIDE.md).

## Public Runtime API

An overview only. The complete inventory (signatures, behavior, errors) is [LOGGER_PACKAGE_REFERENCE.md](LOGGER_PACKAGE_REFERENCE.md).

| Area | Types |
|---|---|
| PSR-3 logger | `Logger` (`emergency()` … `debug()`, `log()`) |
| Facade | `LoggerService` (static `log()`, level methods, `logException()`) |
| Legacy | `Logger::RecordLog()`, `Logger::LEVEL_*` constants |
| Redaction | `Logger::setSensitiveKeys()`, `Logger::setRedactor()`, `Logger::redactContext()` |
| Request URI policy | `Logger::sanitizeRequestUri()`, `Logger::logFullRequestUri()` |

## Critical Runtime Behavior

- **Monolog is the active backend:** because `monolog/monolog` is a required dependency, entries go to `logs/YYYY/MM/DD/HH/app_YYYY-MM-DD-HH.log`. The per-call log file name only routes files in the file fallback; with Monolog it is carried in the context.
- **Redaction runs first** on every call, in both backends, and never throws.
- **Invalid level:** a non-string or unknown level throws `Psr\Log\InvalidArgumentException`.

## Security and Trust Boundaries

The package masks well-known secret shapes; it cannot know every application secret. Add your own keys with `Logger::setSensitiveKeys()` or a hook with `Logger::setRedactor()`. Store logs outside the web root or deny access to them in your server configuration: the `index.php` guard in each log folder does not stop a web server from serving a known `.log` path. `REMOTE_ADDR` and `USER_AGENT` are written by the file fallback unconditionally; apply your own retention policy to log files. See [SECURITY.md](SECURITY.md) for reporting.

## Examples

Maintained, smoke-executed examples using only the Public Runtime API: [examples/](examples/).

## Documentation

- [Usage Guide](docs/guides/USAGE_GUIDE.md)
- [Package Reference](LOGGER_PACKAGE_REFERENCE.md)
- [Changelog](CHANGELOG.md)
- [Security Policy](SECURITY.md)
- [Contributing](CONTRIBUTING.md)

## Quality Status

PHPStan level max with no baseline, a PHPUnit suite, a Consumer Verification Harness (clean consumer install, production autoload) and smoke-executed examples run in [CI](.github/workflows/ci.yml); the `CI gate` job aggregates them.

## Development and Testing

```bash
composer ci
```

Each gate and its CI mapping are listed in [CONTRIBUTING.md](CONTRIBUTING.md).

## License

Proprietary. See [LICENSE](LICENSE).

## 👤 Author

Engineered by **Mohamed Abdulalim** ([@megyptm](https://github.com/megyptm))<br>
Backend Lead & Technical Architect<br>
[https://www.maatify.dev](https://www.maatify.dev)

---

<div align="center">

[Built with ❤️ by Maatify.dev — Unified Ecosystem for Modern PHP Libraries](https://www.maatify.dev)

</div>
