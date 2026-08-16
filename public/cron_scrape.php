<?php

declare(strict_types=1);

use WatchScraper\Support\Env;

require dirname(__DIR__) . '/bootstrap.php';

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

$url = $scheme . '://' . $host . $basePath . '/scrape.php';
if ($token !== null) {
    $url .= '?token=' . rawurlencode($token);
}

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 900,
        'ignore_errors' => true,
    ],
]);

$result = file_get_contents($url, false, $context);
echo $result === false ? "scrape request failed\n" : $result . "\n";
