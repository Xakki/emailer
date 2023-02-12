<?php

return [
    'db' => [
        'password' => getenv('MARIADB_PASSWORD'),
        'user' => getenv('MARIADB_USER'),
        'dbname' => getenv('MARIADB_DATABASE'),
        'host' => getenv('MARIADB_HOST'),
        'port' => getenv('MARIADB_PORT'),
    ],
    'redis' => [
        'host' => getenv('REDIS_HOST'),
        'port' => (int) getenv('REDIS_PORT'),
    ],
    'api' => [
        'email' => 'dev@localhost',
        'password' => 'change_me',
        'key' => 'CHANGE_THIS_SECRET_CODE',
    ],
];
