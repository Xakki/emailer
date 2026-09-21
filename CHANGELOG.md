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
- Scheduled retries: `./console reSend [N]` retries `TEMP_ERROR` messages whose
  `queue.retry_at` is due, on a geometric backoff set by the new `retry` config
  (`max_attempts` 5, `first_delay` 900, `max_delay` 86400). **Add `reSend` to
  cron** — without it nothing is retried.
- Migration `Version20260921140000` adds the nullable `queue.retry_at`
  (+ index `ix_queue_status_retry_at`). **Run migrations before deploying the
  code**: without the column every temporary failure becomes a terminal `ERROR`.
  Rows from before the migration keep `retry_at = NULL` and are never retried.
- `Exception\CacheUnavailable` (extends `Exception\Exception`), thrown by
  `Emailer::getCache()` when Redis cannot be reached.

### Removed
- Dropped the unmaintained `phroute/phroute` dependency; HTTP routing now uses a
  small built‑in `Helper\Router` (same route DSL, so configuration is unchanged).
  This also removes the implicit‑nullable deprecations that would become fatal on PHP 9.
- **BC**: removed the `Emailer::i()` global accessor and the
  `protected static self $instances` singleton store. Callers that previously
  reached `Emailer::i()->getDb()` now go through `AbstractRepository::emailer()`,
  which is wired once by `Emailer::__construct()`. No user‑level code change is
  required if you construct `Emailer` normally; only direct callers of the
  static accessor break.

### Deprecated
- `Xakki\Emailer\Repository\expresion\NullExpresion` (namespace and class name
  both carried typos). Use `Xakki\Emailer\Repository\Expression\NullExpression`
  instead. The old class now extends the new one, so `instanceof` continues to
  work for both names. Removal target: v2.

### Changed
- Queue processing (`send` / `reSend`):
  - SMTP authentication failures and transient exceptions (Redis, DB deadlock /
    lock wait timeout / lost connection) now go through the retry backoff
    instead of failing terminally on the first attempt; other exceptions stay
    terminal `ERROR`.
  - When a relay transport's SMTP connect, TLS or AUTH step fails, the transport
    is paused for the rest of the run: its other messages are left untouched,
    other transports continue. Rejections after login (MAIL FROM / RCPT / DATA)
    never pause it, even when their text mentions authentication. A paused
    transport's messages are excluded by the selection query itself, so its
    backlog adds no query per message to the run.
  - Transport routing ties (several transports of a project with the same rank)
    now go to the lowest transport id; the order was unspecified before.
  - Each message is claimed (`RUN`) in a short transaction and sent outside any
    transaction (no DB lock held during the SMTP dialogue). Delivery is
    **at most once**: a crash or a failed result write leaves the row in `RUN`
    instead of sending it again.
  - The `retry` config is validated when `ConfigService` is constructed
    (integers; `max_attempts >= 1`, `first_delay >= 1`,
    `max_delay >= first_delay`). Integer-valued numeric strings (e.g. raw env
    `"900"`) are cast to int; other strings (`"900.5"`, `"abc"`) are rejected.
  - One `'Run queue'` (debug) line per message at start and one `'Send queue'`
    (info) outcome line with `status`, `retry`, `retry_at`.
- SMTP error classification: the authentication phrases are checked after the
  status table, so a SPAM / INVALID_* verdict wins (e.g. "DMARC authentication failed").
- Migration `Version20260922090000` drops the redundant `ix_queue_status`
  index (covered by `ix_queue_status_retry_at`).
- Minimum PHP requirement raised to **>= 8.4**.
- Migrated from **doctrine/dbal 3 → 4** (and `doctrine/migrations` ^3.8), bumped
  PHPMailer/Monolog, replaced PHPUnit 10 with 11 and PHPStan 1 with 2.
- Tests moved from `src/test/phpunit/` to `tests/` (namespace `Xakki\Emailer\Tests\`,
  loaded via `autoload-dev`) so they no longer ship with the package.
- Fixed the `composer.json` `license` field (was a misspelled `llicense: proprietary`).

### Fixed
- The console `send` / `reSend` summary no longer warns on a SPAM result
  (`TITLE_QUEUE_STATUS` gained `spam`; unknown statuses show as `unknown`).
- A failed `COMMIT` is reported as itself instead of being masked by a
  `NoActiveTransaction` from the follow‑up `rollBack()`.
- `Controller\Mail::initQueue` no longer warns / mis‑parses keys without a `-` separator.
- `Controller\AbstractController::renderImage` reads the MIME type from the file path
  instead of crashing on binary file contents.
- Nullable schema columns (`campaign.params/replacers`, `queue_data.last_error/transport_id`,
  `stats.uri_ref/domain_id`) are now nullable model properties; counter fields default to `0`
  to avoid "typed property accessed before initialization" errors.
