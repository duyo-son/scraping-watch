<?php

declare(strict_types=1);

use WatchScraper\Support\Env;

require dirname(__DIR__) . '/bootstrap.php';

ignore_user_abort(true);
@set_time_limit(900);

$token = Env::nullableString('SCRAPE_TOKEN');
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? Env::string('APP_HOST', '');
$configuredBasePath = Env::string('APP_BASE_PATH', '');
$scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
if (str_ends_with($scriptDir, '/public')) {
    $scriptDir = substr($scriptDir, 0, -7);
}
$detectedBasePath = ($scriptDir === '/' || $scriptDir === '.') ? '' : $scriptDir;
$basePath = '/' . trim($configuredBasePath !== '' ? $configuredBasePath : $detectedBasePath, '/');
$basePath = $basePath === '/' ? '' : $basePath;

if ($host === '') {
    echo "APP_HOST or HTTP_HOST is required\n";
    exit(1);
}

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => Env::int('CRON_CHUNK_TIMEOUT', 180),
        'ignore_errors' => true,
    ],
]);

$limit = max(1, Env::int('SCRAPE_SOURCES_PER_REQUEST', 3));
$offset = 0;
$chunks = [];

do {
    $query = [
        'force' => '1',
        'limit' => (string) $limit,
        'offset' => (string) $offset,
    ];
    if ($token !== null) {
        $query['token'] = $token;
    }

    $url = $scheme . '://' . $host . $basePath . '/scrape.php?' . http_build_query($query);
    $result = file_get_contents($url, false, $context);
    if ($result === false) {
        echo "scrape request failed at offset {$offset}\n";
        exit(1);
    }

    $decoded = json_decode($result, true);
    $chunks[] = is_array($decoded) ? $decoded : ['status' => 'INVALID_JSON', 'body' => $result];
    $offset = is_array($decoded) && isset($decoded['next_offset']) ? (int) $decoded['next_offset'] : null;
} while ($offset !== null);

echo json_encode([
    'status' => 'DONE',
    'chunk_count' => count($chunks),
    'chunks' => $chunks,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
