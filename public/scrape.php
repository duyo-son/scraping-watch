<?php

declare(strict_types=1);

use WatchScraper\Application\ScrapeRunner;
use WatchScraper\Database\Connection;
use WatchScraper\Support\Env;
use WatchScraper\Support\Logger;
use WatchScraper\View\Html;

require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
ignore_user_abort(true);
@set_time_limit(900);
@ini_set('max_execution_time', '900');

$token = Env::nullableString('SCRAPE_TOKEN');
if ($token !== null && !hash_equals($token, (string) ($_GET['token'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['status' => 'FORBIDDEN', 'reason' => 'invalid token'], JSON_UNESCAPED_UNICODE);
    exit;
}

$force = (string) ($_GET['force'] ?? '') === '1';
$sourceName = trim((string) ($_GET['source'] ?? ''));
$sourceName = $sourceName === '' ? null : $sourceName;
$sourceOffset = max(0, (int) ($_GET['offset'] ?? 0));
$sourceLimit = max(1, (int) ($_GET['limit'] ?? Env::int('SCRAPE_SOURCES_PER_REQUEST', 3)));
$lockPath = dirname(__DIR__) . '/storage/scrape.lock';
$lock = fopen($lockPath, 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo json_encode(['status' => 'SKIPPED', 'reason' => 'scraping already in progress'], JSON_UNESCAPED_UNICODE);
    exit;
}

$runner = new ScrapeRunner(Connection::pdo(), require dirname(__DIR__) . '/config/sites.php', Logger::app());
$recovered = $runner->recoverStaleRunningRuns($force);
$reason = $force ? null : $runner->shouldSkipForInterval();
if ($reason !== null) {
    flock($lock, LOCK_UN);
    echo json_encode([
        'status' => 'SKIPPED',
        'reason' => $reason,
        'recovered_stale_runs' => $recovered,
        'scrape_url' => Html::appUrl('/scrape.php'),
        'force_scrape_url' => Html::appUrl('/scrape.php?force=1'),
        'diagnostics_url' => Html::appUrl('/diagnostics.php'),
        'runs_url' => Html::appUrl('/runs.php'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

$result = $runner->run($force ? 'http-force' : 'http', $sourceLimit, $sourceOffset, $sourceName);
$result['recovered_stale_runs'] = $recovered;
$result['run_url'] = Html::appUrl('/run.php?id=' . $result['run_id']);
$result['failures_url'] = Html::appUrl('/failures.php');
$result['diagnostics_url'] = Html::appUrl('/diagnostics.php');
if ($result['next_offset'] !== null) {
    $nextQuery = 'force=1&limit=' . $sourceLimit . '&offset=' . $result['next_offset'];
    $result['next_url'] = Html::appUrl('/scrape.php?' . $nextQuery);
}
flock($lock, LOCK_UN);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
