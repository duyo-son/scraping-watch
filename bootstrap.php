<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use WatchScraper\Database\Connection;
use WatchScraper\Support\Env;

require __DIR__ . '/vendor/autoload.php';

if (is_file(__DIR__ . '/.env')) {
    Dotenv::createImmutable(__DIR__)->safeLoad();
}

date_default_timezone_set(Env::string('APP_TIMEZONE', 'Asia/Tokyo'));

Connection::migrate();
