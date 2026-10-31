# Contributing

## Package identity and boundaries

`maatify/logger` is a standalone PSR-3 logger package: logging, redaction of secrets and file output. Log shipping, rotation, retention and application-specific secret detection are out of scope. The supported contract is [LOGGER_PACKAGE_REFERENCE.md](LOGGER_PACKAGE_REFERENCE.md).

## Ways to contribute

Report bugs and propose changes through issues and pull requests. Discuss architecture-affecting changes (new public API, changed log format, changed redaction semantics, PHP constraint changes) in an issue before opening a pull request. Report vulnerabilities privately as described in [SECURITY.md](SECURITY.md), never in a public issue.

## Pull request expectations

- Work on a branch and open a pull request against `master`; do not push to `master`.
- Keep the change focused; update `CHANGELOG.md` under the right section and call out behavior changes.
- A fixed defect needs a test that detects its recurrence; show it failing before the fix when the defect can be reproduced safely.
- Observable behavior needs a system-level test through the public API (see `tests/EndToEndLoggingTest.php`).
- All gates below must pass; the `CI gate` job is the required check.

## Local verification commands and their CI mapping

Every CI step runs the same Composer script a developer can run locally (`composer ci` runs all of them except the workflow lint).

| Gate | Local command | CI step |
|---|---|---|
| Composer manifest | `composer validate:strict` | `composer validate --strict` |
| Platform requirements | `composer check:platform` | `composer check-platform-reqs` |
| PHP security patch floors | `composer check:php-floors` | same |
| PHP syntax | `composer lint:php` | same |
| Whitespace | `composer lint:whitespace` | same |
| PHPStan (level max, no baseline) | `composer analyse` | same |
| Tests | `composer test` | same |
| Consumer Verification Harness | `composer harness` | `consumer-harness` job |
| Examples smoke execution | `composer examples` | `consumer-harness` job |
| Composer audit | `composer audit:deps` | same |
| Workflow lint (actionlint 1.7.12) | `composer lint:workflows` | `workflow-lint` job |

CI (`.github/workflows/ci.yml`) runs PHP 8.4 and 8.5 with latest dependencies and PHP 8.4 with lowest dependencies, then a stable `CI gate` job that is the one to require in branch protection.

Prerequisites: PHP matching `require.php`, Composer 2, and `actionlint` (or Linux x86_64, where `scripts/lint-workflows.sh` downloads a checksum-verified copy). `composer check:php-floors` fails on an interpreter below the security patch floor; that is intentional.

`composer harness` and `composer examples` install a `git archive` of the committed tree (plus tracked working-tree edits; stage new files first; honours `.gitattributes` `export-ignore`) into a clean consumer project with `--no-dev` and production autoload. Locally, set `HARNESS_COMPOSER_FLAGS=--ignore-platform-req=php` if your PHP is below the patch floor (CI never does).

## Test and integration requirements

There is no database or service dependency, so no Integration infrastructure is needed. Unit tests are in `tests/`; the end-to-end test and the harness exercise the public API through a clean consumer root.

## `composer.lock` policy

`composer.lock` is not committed (reusable library); CI resolves dependencies with `composer update`.

## Release Artifact Verification

`composer release:verify <X>` (CI: the manual `Release Artifact Verification` workflow with the exact target) checks the archived tree of the candidate SHA for target `X`: manifest, required release-facing files, the undated `## [X]` CHANGELOG section, empty `[Unreleased]`, README install target, SECURITY identity badges, `llms.txt` structure and links, and archive contents. It prints the target, SHA and every check outcome, and fails closed. README and SECURITY wording still need the semantic review the standard requires.
