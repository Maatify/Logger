# Changelog

## Unreleased

### Changed (behaviour change)
- File-fallback log entries now record only the **path** of `REQUEST_URI` (no query string, no fragment).
  Signatures, tokens and one-time codes in query strings are no longer written to log files.
  Anything parsing the `server.REQUEST_URI` field will no longer see the query string.
  To restore the old behaviour, opt in explicitly with `Logger::logFullRequestUri(true)` (off by default).
- CLI (no `REQUEST_URI`) still writes an empty string.

### Added
- Redaction of secrets before anything is logged (Monolog and file-fallback paths):
  - sensitive context keys (`password`, `token`, `secret`, `sig`, `authorization`, ...) are masked recursively;
  - `key=value` secrets inside messages, exception messages and traces are scrubbed;
  - `Logger::setSensitiveKeys([...])` replaces the key list, `Logger::setRedactor(callable)` adds a custom hook
    that receives the full context. A throwing hook never breaks logging and never leaks the original context.
- Behaviour change: values under the default sensitive keys now appear as `***REDACTED***` in logs.
- `Throwable` objects placed anywhere in the context are converted to a scrubbed array (class, message, code, file, line, trace, previous) before reaching Monolog.
- `Authorization: Bearer/Basic <credentials>` values are redacted from free text.
- Redaction is depth-limited (8 levels), so circular or very deep contexts cannot exhaust memory.

### Removed
- The `version` field from `composer.json`; releases are identified by VCS tags.

### CI and tooling
- GitHub Actions workflow (PHP 8.4/8.5 latest dependencies, PHP 8.4 lowest dependencies, workflow lint, stable `CI gate` job); actions pinned to commit SHAs, `contents: read` only.
- Composer scripts for every gate (`composer ci`), PHPStan level max without baseline, `CONTRIBUTING.md` mapping local commands to CI.
- **Breaking:** `require.php` is now `>=8.4.27 <8.5.0 || >=8.5.12 <8.6.0`. Previously no PHP constraint was declared (the code already required 8.3+ typed constants).
- `Logger::log()` now throws `Psr\Log\InvalidArgumentException` for a non-string or unknown level (previously an uncaught error from Monolog or `strtoupper`).
