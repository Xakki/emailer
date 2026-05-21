# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project aims to
follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- PHP **8.4 / 8.5** support and a GitHub Actions CI matrix running PHPUnit,
  PHPStan (level 7) and PSR‑12 strict on both versions.
- Unit + integration test suite (~70% line coverage), including an in‑memory
  SQLite integration harness (`tests/Support/IntegrationCase.php`).
- `composer test`, `composer test-coverage` scripts; reusable CI Docker image
  under `docker/ci/`.
- Open‑source project files: `LICENSE` (GPL‑3.0‑or‑later), `README`, `CONTRIBUTING`,
  this changelog.

### Changed
- Minimum PHP requirement raised to **>= 8.4**.
- Migrated from **doctrine/dbal 3 → 4** (and `doctrine/migrations` ^3.8), bumped
  PHPMailer/Monolog, replaced PHPUnit 10 with 11 and PHPStan 1 with 2.
- Tests moved from `src/test/phpunit/` to `tests/` (namespace `Xakki\Emailer\Tests\`,
  loaded via `autoload-dev`) so they no longer ship with the package.
- Fixed the `composer.json` `license` field (was a misspelled `llicense: proprietary`).

### Fixed
- `Controller\Mail::initQueue` no longer warns / mis‑parses keys without a `-` separator.
- `Controller\AbstractController::renderImage` reads the MIME type from the file path
  instead of crashing on binary file contents.
- Nullable schema columns (`campaign.params/replacers`, `queue_data.last_error/transport_id`,
  `stats.uri_ref/domain_id`) are now nullable model properties; counter fields default to `0`
  to avoid "typed property accessed before initialization" errors.
