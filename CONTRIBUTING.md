# Contributing to Emailer

Thanks for taking the time to contribute! This project targets **PHP 8.4 and 8.5**
and ships under the **GPL‑3.0‑or‑later** license — by submitting a contribution you
agree to license it under the same terms.

## Getting started

```bash
git clone https://github.com/Xakki/emailer
cd emailer
composer install
```

You do **not** need MySQL or Redis to run the test suite: unit tests use a mocked
DBAL connection and integration tests run against an in‑memory SQLite database.

## Before opening a pull request

Run the full local tool‑chain — CI runs exactly these on both PHP 8.4 and 8.5:

```bash
composer cs-check   # PSR-12 strict code style
composer phpstan    # PHPStan level 7 (src + tests)
composer test       # PHPUnit unit + integration suites
```

- `composer cs-fix` auto‑fixes most style issues.
- New code must keep PHPStan at **0 errors** and PHPCS clean. Suppress a rule only
  when a fix is genuinely impossible, and always add a one‑line comment explaining
  why (see the existing `phpcs:ignore` annotations for the expected format).
- Keep **line coverage at or above 70%**. Add tests next to the code you change:
  - pure logic → a unit test (see `tests/MailTest.php`, mock the DB via the `Mocks` trait);
  - anything touching repositories / models / CQRS → an integration test that extends
    `Xakki\Emailer\Tests\Support\IntegrationCase` (real in‑memory SQLite).

## Coding style

- `declare(strict_types=1);` in every PHP file.
- Comments explain **why**, not what — prefer self‑describing names (see the project
  style notes). English for public docs; inline comments may stay in the project's
  existing language.
- Conventional‑commit style messages are appreciated (`feat:`, `fix:`, `chore:`, …).

## Reporting bugs / requesting features

Open an issue with: PHP version, steps to reproduce, expected vs actual behaviour,
and a minimal snippet or failing test if possible.

## Database changes

Schema changes go through Doctrine Migrations:

```bash
./console migrations generate   # create a new migration class
./console migrations migrate
```

If you add or rename a column, update the matching model property **and** the test
schema in `tests/Support/IntegrationCase.php` so the integration suite stays honest.
