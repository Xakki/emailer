SHELL = /bin/sh

docker := $(shell command -v docker 2> /dev/null)
docker-compose:= docker compose

php := $(docker) exec -it emailer-php 
composer := $(php) composer

up:
	$(docker-compose) up

down:
	$(docker-compose) down

restart:
	$(docker-compose) up -d --force-recreate --remove-orphans

docker-prune:
	$(docker) system prune -a --volumes

build:
	$(docker-compose) build

logs:
	$(docker-compose) logs --follow

fix-permision:
	$(php) find ./src/Migration -type f -exec chmod 0666 {} \;
#	$(php) sh -c "chown -R root:root ./src/Migration"

fix-permision-all:
	chown -R $USER:docker .
	find . -type d -exec chmod 0775 {} \;
	find . -type f -exec chmod 0664 {} \;
	find . -type f -name "*.sh" -exec chmod 0775 {} \;
	find vendor/bin -type f -exec chmod 0775 {} \;
	chmod 0775 console

bash-php:
	$(php) bash

bash-db:
	$(docker) exec -it email-mariadb bash

composer-u:
	$(composer) u

composer-i:
	$(composer) i

phpunit:
	$(composer) phpunit

phpstan:
	$(composer) phpstan

psalm:
	$(composer) psalm

## Throwaway swagger-php container (writes src/swagger.json): same CPU/memory
## caps (CI_CPUS / CI_MEMORY) and invoking-user run as the test-ci runner. The
## image's /docker-entrypoint.sh is root-only (0711), so call openapi directly.
swagger-generate:
	$(docker) run --rm --cpus $(CI_CPUS) --memory $(CI_MEMORY) --user "$$(id -u):$$(id -g)" \
		--entrypoint /tmp/vendor/bin/openapi -v "$(CURDIR)/src":/app tico/swagger-php \
		/app/Controller/Api --output swagger.json

## https://github.com/swagger-api/swagger-ui/blob/master/docs/usage/installation.md
swagger-ui:
	docker run --rm --name uni-swagger --network default-network -p 82:8181 -e SWAGGER_JSON=/app/swagger.json -v src:/app swaggerapi/swagger-ui

cs-fix:
	$(php) sh -l -c "git diff --name-only --diff-filter=AM master | grep .php | xargs composer cs-fix"

cs-fix-all:
	$(php) sh -l -c "composer cs-fix"

cs-check:
	$(php) sh -l -c "git diff --name-only --diff-filter=AM master | grep .php | xargs composer cs-check"

cs-check-all:
	$(php) sh -l -c "composer cs-check"

migrations-up:
	$(php) sh -l -c "./console migrations migrate"

migrations-down:
	$(php) sh -l -c "./console migrations rollup"

migrations-create:
	$(php) sh -l -c "./console migrations generate"

migrations-status:
	$(php) sh -l -c "./console migrations status"

## Non-interactive test runner: builds the CI image (docker/ci/Dockerfile, same
## one .github/workflows/ci.yml uses) and runs composer install + phpunit inside
## a throwaway container. No `emailer-php`/`emailer-mariadb` service needed —
## the suite is unit tests + SQLite in-memory integration tests only. Use this
## instead of `phpunit` (which needs the long-lived docker-compose stack up).
## Build steps are capped via BuildKit's `--resource` (sets the RUN containers'
## cgroup memory.max / cpu.max; needs a buildx that has the flag). cpu-quota is
## in µs per 100 ms period: 200000 = 2 CPUs.
CI_BUILD_CPU_QUOTA ?= 200000
CI_BUILD_MEMORY ?= $(CI_MEMORY)
test-ci-build:
	$(docker) build --resource memory=$(CI_BUILD_MEMORY) --resource cpu-quota=$(CI_BUILD_CPU_QUOTA) \
		-f docker/ci/Dockerfile --build-arg PHP_VERSION=8.5 -t emailer-test:8.5 .

## Throwaway CI container: CPU/memory capped (override CI_CPUS / CI_MEMORY) and
## run as the invoking user so vendor/ and the caches stay owned by you; HOME
## points composer's home/cache at the container's /tmp.
CI_CPUS ?= 2
CI_MEMORY ?= 2g
ci-run = $(docker) run --rm --cpus $(CI_CPUS) --memory $(CI_MEMORY) --user "$$(id -u):$$(id -g)" \
	-e HOME=/tmp -v "$(CURDIR)":/app -w /app emailer-test:8.5

test-ci:
	$(ci-run) sh -c "composer install --no-interaction --prefer-dist --no-progress && vendor/bin/phpunit -c phpunit.xml"

## make test-ci-filter filter=testSomething — same runner, one test/pattern (fast
## red/green checks; vendor/ already installed by a prior test-ci run so this skips it).
test-ci-filter:
	$(ci-run) sh -c "vendor/bin/phpunit -c phpunit.xml --filter '$(filter)'"

## Same throwaway-container runner as test-ci, for the static-analysis gates
## (no long-lived `emailer-php`/`emailer-mariadb` stack needed).
test-ci-phpstan:
	$(ci-run) sh -c "composer install --no-interaction --prefer-dist --no-progress && vendor/bin/phpstan analyse --memory-limit 1G"

test-ci-cs-check:
	$(ci-run) sh -c "composer install --no-interaction --prefer-dist --no-progress && vendor/bin/phpcs"

