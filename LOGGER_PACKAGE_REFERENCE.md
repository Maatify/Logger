# Maatify Logger — Package Reference

The canonical public, runtime and behavioral contract of `maatify/logger` and the complete Public Runtime API inventory. [README.md](README.md) gives the overview; [docs/guides/USAGE_GUIDE.md](docs/guides/USAGE_GUIDE.md) gives walkthroughs. Package identity, PHP and dependency constraints are owned by [composer.json](composer.json).

## 1. Public Runtime API

Namespace `Maatify\Logger`. Anything not listed here is not part of the supported contract. This section is the complete inventory of the public classes.

### 1.1 `Logger` (implements `Psr\Log\LoggerInterface`)

| Member | Contract |
|---|---|
| `new Logger()` | Creates the Monolog backend (channel `maatify`, one stream handler) when `Monolog\Logger` is loadable. |
| `emergency/alert/critical/error/warning/notice/info/debug($message, array $context = [])` | PSR-3 level methods; delegate to `log()`. |
| `log($level, $message, array $context = []): void` | Core call. `$level` must be a PSR-3 level name (case-insensitive); `$message` may be a string, `Stringable`, array or `Throwable`. |
| `static RecordLog(string\|array\|Throwable $message, string $level = 'info', string $logFile = 'app'): void` | Legacy entry point; creates a `Logger` and logs with `['logFile' => $logFile]`. |
| `LEVEL_INFO`, `LEVEL_ERROR`, `LEVEL_DEBUG` | Legacy level constants (PSR-3 level names). |
| `static setSensitiveKeys(array $keys): void` | Replaces the list of sensitive context keys (case-insensitive substring match). |
| `static setRedactor(?callable $redactor): void` | Registers (or with `null` removes) a hook `fn(array $context): array` run after built-in masking. |
| `static redactContext(array $context): array` | Applies built-in masking and the hook; never throws. |
| `static sanitizeRequestUri(mixed $uri): string` | Returns only the path of a request URI: no query string, fragment, scheme, userinfo, host or port. `https://user:pw@host/cb?x=1` becomes `/cb`; an absolute URL without a path becomes `/`. Anything that is not an origin-form target (missing or non-string input, `*`, `host:port`) and any absolute URL with an empty authority (such as `https:///user:pw@host/p`) yields `''`. |
| `static logFullRequestUri(bool $enabled): void` | Opt-in to keep the full URI in `sanitizeRequestUri()`. Default off. |

### 1.2 `LoggerService` (final, static facade)

| Member | Contract |
|---|---|
| `log(string $level, string\|array\|Throwable $message, string $logFile = 'app')` | Generic call. |
| `emergency/alert/critical/error/warning/notice/info/debug(string\|array\|Throwable $message, string $logFile = 'app')` | Level wrappers. |
| `logException(Throwable $e, string $logFile = 'exceptions', string $level = 'error', array $context = [])` | Logs `'Exception captured'` with the exception details and the extra context. |

The constructor is private; the class holds one shared `Logger`.

### 1.3 `LoggerCleanupService` (final)

| Member | Contract |
|---|---|
| `new LoggerCleanupService(?string $logBasePath = null)` | Directory to clean. The default is the same `logs/` directory the logger writes to (the consumer project root for `vendor/maatify/logger`). |
| `deleteOlderThanDays(int $days): void` | Deletes files under the directory whose modification time is older than the threshold, then removes empty sub-directories. |
| `deleteOlderThanHours(int $hours): void` | Same, with an hours threshold. |

Files named `index.php` (the logger's access-guard file) are never deleted on age alone. A folder that holds only an old-enough guard file is removed together with it; a folder that still holds newer logs keeps its guard. Symbolic links inside the directory are neither followed nor deleted, so nothing outside the log directory is ever touched. A missing directory is ignored; deletion errors are suppressed. The service deletes files permanently.

### 1.4 `Maatify\Store\File\Path` (legacy helper)

`new Path(string $path = '')` and `Get(): string` return the given path (default: the directory above the package namespace folder) followed by four `..` segments and a trailing separator. It is not used by `Logger` or `LoggerService` and is kept only for existing callers; new code should not depend on it.

## 2. Behavior

### 2.1 Messages
An array message is logged as `'Array log entry'` with the array under `context.data`. A `Throwable` message is logged as `'Exception log entry'` with its details under `context.exception`.

### 2.2 Backends and files
- Both backends validate the level first (see §3), then redact, then write. Level validation does not depend on Monolog, so the file fallback works when Monolog is not installed.
- With Monolog loadable (always, when installed through Composer), entries are written to `logs/YYYY/MM/DD/HH/app_YYYY-MM-DD-HH.log` under the consumer project root (the directory four levels above the package `src/`, i.e. the project root for `vendor/maatify/logger`). The `logFile` argument is not used for routing; it is carried in the context.
- Only when Monolog is not loadable, the file fallback writes pretty-printed JSON entries to `logs/YYYY/MM/DD/HH/<logFile>_YYYY-MM-DD-HH.log`; a `/` in `logFile` creates sub-folders. Each created log folder (both backends) receives an `index.php` that answers `404`. That file does not prevent a web server from serving a known `.log` path: keep the log directory outside the web root or deny access to it in the server configuration.
- Fallback I/O failures are written to `error_logger.log` and never thrown.

### 2.3 Fallback entry shape
`level`, `time`, `server` (`REMOTE_ADDR` or `CLI`, `HTTP_HOST`, `REQUEST_URI` path only, `USER_AGENT`), `message`, `context`.

### 2.4 Redaction (both backends)
Applied to the message and context on every call, before any write:
1. Context keys containing a sensitive key (default: `password`, `passwd`, `secret`, `token`, `authorization`, `api_key`, `apikey`, `sig`, `signature`, `otp`, `cookie`) get the value `***REDACTED***`, recursively.
2. Strings are scrubbed of `key=value` / `key: value` secrets for those keys and of `Bearer <token>` / `Basic <credentials>` values.
3. `Throwable` objects anywhere in the context become arrays (`class`, `message`, `code`, `file`, `line`, `trace`, `previous`) with message and trace scrubbed.
4. Objects are expanded into what a formatter would serialize and then masked: `JsonSerializable` output, the string of a `Stringable`, otherwise `['class' => ..., 'properties' => ...]` from public properties. `DateTimeInterface` and enum values are kept as they are. An object whose serialization throws is replaced by `['class' => ..., 'unserializable' => true]`.
5. Nesting deeper than 8 levels is truncated, so circular structures (arrays or objects) are safe.
6. The optional hook runs last and its output is masked again with the same rules, so a hook cannot re-introduce a sensitive key or `key=value` secret. If the hook throws or returns a non-array, the context becomes `['redaction' => 'failed']`.

Matching is by substring: a key such as `design` also contains `sig` and is masked.

## 3. Errors

| Condition | Result |
|---|---|
| `log()` with a non-string or unknown level (both backends) | `Psr\Log\InvalidArgumentException` |
| Redaction hook throws or returns a non-array | Context replaced by `['redaction' => 'failed']`; logging continues |
| Fallback write or folder failure | Recorded in `error_logger.log`; no exception |

Exceptions raised by Monolog's own handlers are not caught by the package.

## 4. Boundaries and non-goals

No log rotation, retention, shipping or sampling; no per-call routing with Monolog; no guarantee that arbitrary application secrets are recognized (configure sensitive keys or a hook).
