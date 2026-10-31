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
| Workflow lint (actionlint 1.7.12) | `composer lint:workflows` | `workflow-lint` job |

CI (`.github/workflows/ci.yml`) runs PHP 8.4 and 8.5 with latest dependencies and PHP 8.4 with lowest dependencies, then a stable `CI gate` job that is the one to require in branch protection. `composer.lock` is not committed; CI resolves dependencies with `composer update`.

Prerequisites: PHP matching `require.php`, Composer 2, and `actionlint` (or Linux x86_64, where `scripts/lint-workflows.sh` downloads a checksum-verified copy). `composer check:php-floors` fails on an interpreter below the security patch floor; that is intentional.

Known gaps against the Maatify CI standard: no Consumer Verification Harness job and no Release Artifact Verification yet.
