# Contributing

## Local quality gates and their CI mapping

Every CI step runs the same Composer script a developer can run locally (`composer ci` runs them all except the workflow lint).

| Gate | Local command | CI step |
|---|---|---|
| Composer manifest | `composer validate:strict` | `composer validate --strict` |
| Platform requirements | `composer check:platform` | `composer check-platform-reqs` |
| PHP security patch floors | `composer check:php-floors` | same |
| PHP syntax | `composer lint:php` | same |
| Whitespace | `composer lint:whitespace` | same |
| PHPStan (level max, no baseline) | `composer analyse` | same |
| Tests | `composer test` | same |
| Composer audit | `composer audit:deps` | same |
| Consumer Verification Harness | `composer harness` | `consumer-harness` job |
| Workflow lint (actionlint 1.7.12) | `composer lint:workflows` | `workflow-lint` job |

CI (`.github/workflows/ci.yml`) runs PHP 8.4 and 8.5 with latest dependencies and PHP 8.4 with lowest dependencies, then a stable `CI gate` job that is the one to require in branch protection. `composer.lock` is not committed; CI resolves dependencies with `composer update`.

Prerequisites: PHP matching `require.php`, Composer 2, and `actionlint` (or Linux x86_64, where `scripts/lint-workflows.sh` downloads a checksum-verified copy). `composer check:php-floors` fails on an interpreter below the security patch floor; that is intentional.

`composer harness` installs a `git archive` of the committed tree (plus tracked edits; honours `.gitattributes` `export-ignore`) into a clean consumer project twice, with `--no-dev` and production autoload, then exercises the public API and checks the consumer's log files. Locally, set `HARNESS_COMPOSER_FLAGS=--ignore-platform-req=php` if your PHP is below the patch floor (CI never does).

## Release Artifact Verification

`composer release:verify <X>` (CI: manual `Release Artifact Verification` workflow with the exact target) checks the archived tree of the candidate SHA for target `X`: manifest, required release-facing files, the undated `## [X]` CHANGELOG section, empty `[Unreleased]`, README install target, SECURITY identity badges and archive contents. It prints the target, SHA and every check outcome, and fails closed. It is **expected to fail today**: `SECURITY.md`, `CODE_OF_CONDUCT.md`, `LICENSE`, `llms.txt`, `docs/guides/USAGE_GUIDE.md` and `examples/` do not exist yet and need owner-approved content. README/SECURITY wording still needs the semantic review the standard requires.
