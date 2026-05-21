# Emailer

[![CI](https://github.com/Xakki/emailer/actions/workflows/ci.yml/badge.svg)](https://github.com/Xakki/emailer/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/php-8.4%20%7C%208.5-777bb4.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPL--3.0--or--later-blue.svg)](LICENSE)

Self‑hosted transactional & notification email service for PHP. It stores projects,
campaigns, templates and recipients in a relational database, renders HTML emails
from reusable template blocks, sends them over SMTP (PHPMailer), and tracks opens,
clicks and (un)subscriptions through a small set of HTTP endpoints.

> The public surface is a plain PHP library (`Xakki\Emailer\Emailer`) plus an
> HTTP front controller and a console runner. There is no framework lock‑in — you
> wire it into your own app, container, or the bundled Docker stack.

## Features

- **Projects → campaigns → templates → queue** domain model on top of Doctrine DBAL 4.
- **Template engine** with `{{placeholder}}` substitution, reusable wrapper / content / block templates and per‑project parameters.
- **SMTP delivery** via PHPMailer with DKIM support and SMTP error classification (spam / quota / invalid mailbox / temporary, …).
- **Open & click tracking**: tracking pixel, link rewriting, and per‑recipient statistics.
- **Subscription management**: one‑click subscribe / unsubscribe endpoints, `List-Unsubscribe` header.
- **HTTP API** (Phroute router) and a **console runner** for queue processing and migrations.
- **Redis** caching for MX lookups and auth tokens; **Doctrine Migrations** for schema.
- Tested on **PHP 8.4 and 8.5**, static‑analysed with PHPStan (level 7) and PSR‑12 (strict).

## Requirements

- PHP **>= 8.4** with extensions: `pdo`, `pdo_mysql`, `json`, `fileinfo`, `redis`, `intl`, `mbstring`
- A MySQL / MariaDB database
- Redis
- Composer 2

## Installation

Once the package is published on Packagist:

```bash
composer require xakki/emailer
```

Until then (or to track `master`), add the repository explicitly:

```bash
composer config repositories.emailer vcs https://github.com/Xakki/emailer
composer require xakki/emailer:dev-master
```

Run the database migrations (against your configured connection):

```bash
./console migrations migrate
```

## Configuration

Configuration is a plain PHP array passed to `ConfigService`. Only `db.password`
is strictly required; everything else has sane defaults (see
[`src/ConfigService.php`](src/ConfigService.php)).

```php
use Xakki\Emailer\ConfigService;

$config = new ConfigService([
    'db' => [
        'driver'   => 'pdo_mysql',
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'user'     => 'emailer',
        'password' => 'a-strong-unique-password', // required
        'dbname'   => 'emailer',
    ],
    'redis' => ['host' => '127.0.0.1', 'port' => 6379],

    // Optional: enables the read-only GET /emailer/get/{key}/{secret} accessor.
    'secret_key' => getenv('SECRET_EMAILER_KEY') ?: '',
]);
```

| Key          | Default                       | Notes                                             |
|--------------|-------------------------------|---------------------------------------------------|
| `db`         | `pdo_mysql` localhost set     | Doctrine DBAL connection params; `password` required |
| `redis`      | `emailer-redis:6379`          | Used for MX / auth caches                         |
| `route`      | built‑in tracking routes      | Phroute route → `[Controller, method]` map        |
| `migration`  | `src/Migration`               | Doctrine Migrations config                        |
| `secret_key` | `''` (disabled)               | Guards the read‑only body accessor                |

## Usage

### As a library

```php
use Monolog\Logger;
use Xakki\Emailer\Emailer;
use Xakki\Emailer\Model\Template;
use Xakki\Emailer\Transports\Smtp;

$emailer = new Emailer($config, new Logger('emailer'));

// 1. One-time setup: project, templates, transport, notify channel, campaign.
$project = $emailer->createProject('My project', [
    Template::NAME_HOST     => 'mail.example.com',
    Template::NAME_ROUTE    => '/emailer',
    Template::NAME_LANG     => 'en',
    Template::NAME_URL_LOGO => __DIR__ . '/tpl/logo.png',
]);

$wrapper = $project->createTplWrapper('Base', file_get_contents('tpl/wrapper.html'));
$content = $project->createTplContent('News', file_get_contents('tpl/content.html'));
$notify  = $project->createNotify('Newsletter');

$smtp = new Smtp($emailer);
$smtp->fromEmail = 'robot@example.com';
$smtp->fromName  = 'Robot';
$smtp->host      = 'smtp.example.com';
$smtp->port      = 587;
$project->createTransport($smtp);

$campaign = $project->createCampaign('Welcome {{name}}', $wrapper, $content, $notify, []);

// 2. Queue a message for a recipient.
$mail = $emailer->getNewMail()
    ->setEmail('user@example.com')
    ->setEmailName('Jane Doe')
    ->setData(['name' => 'Jane']);

$hashRoute = $emailer
    ->getNewSender($campaign->project_id, $campaign->id)
    ->send($mail);
```

A runnable end‑to‑end example lives in [`example/as-vendor/init.php`](example/as-vendor/init.php).

### Processing the queue (console)

```bash
./console send         # send pending messages from the queue
./console newDay       # reset per-day transport counters (run daily via cron)
./console migrations migrate
```

### HTTP tracking endpoints

The front controller (`Emailer::dispatchRoute()`) exposes the tracking surface.
Default routes (see `ConfigService::$route`):

| Method & path                                  | Purpose                              |
|------------------------------------------------|--------------------------------------|
| `GET /emailer/home/{key}`                      | Click‑through landing / open marker  |
| `GET /emailer/goto/{key}/{url}`                | Tracked outbound link redirect       |
| `GET /emailer/logoimg/{key}`                   | Tracking pixel (logo image)          |
| `GET /emailer/unsubscribe/{key}`               | One‑click unsubscribe                |
| `GET /emailer/subscribe/{key}`                 | Re‑subscribe                         |
| `GET /emailer/status/{key}`                    | Per‑message delivery status page     |
| `GET /emailer/get/{key}/{secret}`              | Read‑only rendered body (secret‑gated; opaque 404 when `secret_key` is unset) |

```php
echo $emailer->dispatchRoute($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
```

The JSON management API (login / dashboard / SMTP test) is documented in
[`swagger.json`](swagger.json).

## Docker

A full dev stack (PHP‑FPM, Nginx, MariaDB, Redis) is provided:

```bash
cp .env_dist .env   # edit passwords
make build
make up
```

See the [`Makefile`](Makefile) for `migrations-*`, `phpunit`, `phpstan`, `cs-*` targets.

## Development & quality

The CI matrix runs the whole tool‑chain on PHP 8.4 and 8.5. To reproduce locally
you only need PHP with the extensions listed above plus Composer:

```bash
composer install

composer test          # PHPUnit
composer test-coverage # PHPUnit + HTML/text coverage (needs Xdebug or PCOV)
composer phpstan       # PHPStan level 7 (src + tests)
composer cs-check      # PSR-12 strict (squizlabs/php_codesniffer)
composer cs-fix        # auto-fix style
```

Current line coverage is **~70%**. Tests live in [`tests/`](tests/) and split into
pure unit tests (mocked DBAL connection) and integration tests that run against an
in‑memory SQLite database (`tests/Support/IntegrationCase.php`).

## Project layout

```
src/
  Emailer.php          Entry point / service locator
  ConfigService.php    Typed configuration
  Mail.php             Outgoing message value object
  Sender.php           Queues a message for a campaign
  Controller/          HTTP + console + JSON API controllers
  Model/               Active-record style domain models
  Repository/          Doctrine DBAL data access
  Cqrs/                Single-purpose command/query handlers
  Transports/          SMTP transport (PHPMailer)
  Migration/           Doctrine schema migration
  Helper/, Exception/, locale/, view/
tests/                 PHPUnit unit + integration suites
example/               Runnable usage examples
docker/                Local dev & CI images
```

## Contributing

Contributions are welcome — please read [CONTRIBUTING.md](CONTRIBUTING.md) first.

## License

Distributed under the **GNU General Public License v3.0 or later**. See [LICENSE](LICENSE).
