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
