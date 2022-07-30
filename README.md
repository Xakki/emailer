# How to start

## Create `.env` from `.env_dist`
Available params (* is require)
* ENV* [dev] - define config config/dev.php
* CFG_PHP [php]
* CFG_NGINX [nginx]
* CFG_DB [mariadb]
* DEBUG_MODE* [1] - Enable debug mode
* APPLICATION_HOST*
* EMAILER_API*
* SWAGGER_UI*
* MARIADB_ROOT_PASSWORD*
* MARIADB_PASSWORD*
* MARIADB_USER [emailer]
* MARIADB_DATABASE [emailer]
* PORT_DB [10002]
* PORT_PHP [9000]
* PORT_HTTP [80]
* PORT_HTTPS [443]

## Run `make build`
## Run `make up`
## Run `make up`


## Test
wep/_vendors/bin/phpunit -c wep/email-service/phpunit.xml

## Swager 
https://zircote.github.io/swagger-php/guide/annotations.html
https://github.com/swagger-api/swagger-ui/blob/master/docs/usage/installation.md


# TODO

## DOCKER
Добавить защиту
https://github.com/nemesida-waf/nemesida_waf_free/blob/master/README_RU.md
`openssl req -x509 -sha256 -nodes -newkey rsa:2048 -days 365 -keyout localhost.key -out localhost.crt`