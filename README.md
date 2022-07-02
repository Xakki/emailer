# Сделать установку из готового образа для Php
# Сделать конфиг прода для прода
# Миграция

******

## Отправка писем
* немедленна/отложенная
    * микросервис на GO
* с трекингом +
    * роутинг, контролер + 
* Панель управения
    * настройка проектов
    * статистика
* Тесты
    * юниттесты


nano src/migration/sql/_db.sql // Change password
mysql email_service < src/migration/sql/_db.sql
mysql email_service < src/migration/sql/begin.sql


## Test
wep/_vendors/bin/phpunit -c wep/email-service/phpunit.xml


## panel
* https://github.com/erdkse/adminlte-3-vue
* https://github.com/Materialfy/M-Dash
* https://github.com/ColorlibHQ/AdminLTE


## Swager 
https://zircote.github.io/swagger-php/guide/annotations.html
https://github.com/swagger-api/swagger-ui/blob/master/docs/usage/installation.md


# TODO

## DOCKER
Добавить защиту
https://github.com/nemesida-waf/nemesida_waf_free/blob/master/README_RU.md
`openssl req -x509 -sha256 -nodes -newkey rsa:2048 -days 365 -keyout localhost.key -out localhost.crt`