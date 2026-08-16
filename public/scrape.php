<?php

declare(strict_types=1);

use WatchScraper\Application\ScrapeRunner;
use WatchScraper\Database\Connection;
use WatchScraper\Support\Env;
use WatchScraper\Support\Logger;

require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$token = Env::nullableString('SCRAPE_TOKEN');
if ($token !== null && !hash_equals($token, (string) ($_GET['token'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['status' => 'FORBIDDEN', 'reason' => 'invalid token'], JSON_UNESCAPED_UNICODE);
    exit;
}

$lockPath = dirname(__DIR__) . '/storage/scrape.lock';
$lock = fopen($lockPath, 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo json_encode(['status' => 'SKIPPED', 'reason' => 'scraping already in progress'], JSON_UNESCAPED_UNICODE);
    exit;
}

$runner = new ScrapeRunner(Connection::pdo(), require dirname(__DIR__) . '/config/sites.php', Logger::app());
$reason = $runner->shouldSkipForInterval();
if ($reason !== null) {
    flock($lock, LOCK_UN);
    echo json_encode(['status' => 'SKIPPED', 'reason' => $reason], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = $runner->run('http');
flock($lock, LOCK_UN);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
