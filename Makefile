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

bash-php:
	$(php) bash

bash-db:
	$(docker) exec -it email-mariadb bash

composer-u:
	$(composer) u

composer-i:
	$(composer) i

test:
	$(composer) phpunit

phpstan:
	$(composer) phpstan

swagger-generate:
	docker run -it --rm -v src:/app tico/swagger-php /app/Controller/Api --output swagger.json

## https://github.com/swagger-api/swagger-ui/blob/master/docs/usage/installation.md
swagger-ui:
	docker run --rm --name uni-swagger --network default-network -p 82:8181 -e SWAGGER_JSON=/app/swagger.json -v src:/app swaggerapi/swagger-ui

cs-fix:
	$(php) sh -c "git diff --name-only --diff-filter=AM master | grep .php | xargs composer cs-fix"

cs-fix-all:
	$(php) sh -c "composer cs-fix"

cs-check:
	$(php) sh -c "git diff --name-only --diff-filter=AM master | grep .php | xargs composer cs-check"

cs-check-all:
	$(php) sh -c "composer cs-check"

migrations-up:
	$(php) sh -c "./console migrations migrate"

migrations-down:
	$(php) sh -c "./console migrations rollup"

migrations-create:
	$(php) sh -c "./console migrations generate"

migrations-status:
	$(php) sh -c "./console migrations status"