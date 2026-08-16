<?php

declare(strict_types=1);

use WatchScraper\Database\Connection;
use WatchScraper\Repository\RunRepository;
use WatchScraper\Support\Env;
use WatchScraper\View\Html;

require dirname(__DIR__) . '/bootstrap.php';

$pdo = Connection::pdo();
$runs = new RunRepository($pdo);
$last = $runs->lastRun();
$recentSources = $runs->recentSourceRuns(20);

$dbPath = Env::string('DB_PATH', dirname(__DIR__) . '/storage/database.sqlite');
$storageDir = dirname(__DIR__) . '/storage';
$logDir = $storageDir . '/logs';
$debugDir = $storageDir . '/debug';
$logPath = $logDir . '/app.log';
$tokenEnabled = Env::nullableString('SCRAPE_TOKEN') !== null;
$tokenParam = $tokenEnabled ? '?token=SCRAPE_TOKEN값' : '';
$forceParam = $tokenEnabled ? '?token=SCRAPE_TOKEN값&force=1' : '?force=1';
$staleMinutes = Env::int('SCRAPE_STALE_AFTER_MINUTES', 10);

$logLines = [];
if (is_file($logPath) && is_readable($logPath)) {
    $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $logLines = $lines === false ? [] : array_slice($lines, -40);
}

$checks = [
    ['PHP version', PHP_VERSION],
    ['timezone', date_default_timezone_get()],
    ['max_execution_time', ini_get('max_execution_time')],
    ['memory_limit', ini_get('memory_limit')],
    ['allow_url_fopen', ini_get('allow_url_fopen') ? 'on' : 'off'],
    ['openssl extension', extension_loaded('openssl') ? 'loaded' : 'missing'],
    ['pdo_sqlite extension', extension_loaded('pdo_sqlite') ? 'loaded' : 'missing'],
    ['DB path', $dbPath],
    ['DB directory writable', is_writable(dirname($dbPath)) ? 'yes' : 'no'],
    ['DB file writable', is_file($dbPath) ? (is_writable($dbPath) ? 'yes' : 'no') : 'not created yet'],
    ['storage writable', is_writable($storageDir) ? 'yes' : 'no'],
    ['logs writable', is_writable($logDir) ? 'yes' : 'no'],
    ['debug writable', is_writable($debugDir) ? 'yes' : 'no'],
    ['SCRAPE_TOKEN', $tokenEnabled ? 'set' : 'empty'],
    ['SCRAPE_MIN_INTERVAL_MINUTES', (string) Env::int('SCRAPE_MIN_INTERVAL_MINUTES', 30)],
    ['SCRAPE_STALE_AFTER_MINUTES', (string) $staleMinutes],
];

ob_start();
?>
<h2>스크레이핑 진단</h2>

<section class="panel">
  <h3>실행 URL</h3>
  <p>수동 실행: <a href="<?= Html::e(Html::appUrl('/scrape.php' . $tokenParam)) ?>"><?= Html::e(Html::appUrl('/scrape.php' . $tokenParam)) ?></a></p>
  <p>강제 재실행: <a href="<?= Html::e(Html::appUrl('/scrape.php' . $forceParam)) ?>"><?= Html::e(Html::appUrl('/scrape.php' . $forceParam)) ?></a></p>
  <p class="small">강제 재실행은 30분 제한을 무시하고, 멈춰 있던 RUNNING 실행을 먼저 정리합니다.</p>
</section>

<section class="panel">
  <h3>최근 실행</h3>
  <?php if ($last): ?>
    <p>Run #<a href="<?= Html::e(Html::appUrl('/run.php?id=' . $last['id'])) ?>"><?= Html::e($last['id']) ?></a>
      / <?= Html::statusBadge($last['status']) ?>
      / 시작 <?= Html::e($last['started_at']) ?>
      / 종료 <?= Html::e($last['finished_at'] ?? '-') ?>
      / <?= Html::e($last['duration_ms'] ?? '-') ?> ms</p>
    <p class="small">성공 <?= Html::e($last['success_count']) ?> / 실패 <?= Html::e($last['failure_count']) ?> / 상품 <?= Html::e($last['total_products']) ?> / 신규 <?= Html::e($last['new_products']) ?></p>
  <?php else: ?>
    <p>아직 실행 기록이 없습니다.</p>
  <?php endif; ?>
</section>

<h3>환경 체크</h3>
<div class="wide"><table>
  <thead><tr><th>항목</th><th>값</th></tr></thead>
  <tbody>
  <?php foreach ($checks as [$name, $value]): ?>
    <tr><td><?= Html::e($name) ?></td><td><?= Html::e($value) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table></div>

<h3>최근 소스 실행</h3>
<div class="wide"><table>
  <thead><tr><th>Run</th><th>사이트</th><th>시작</th><th>종료</th><th>상태</th><th>HTTP</th><th>상품</th><th>신규</th><th>오류</th></tr></thead>
  <tbody>
  <?php foreach ($recentSources as $row): ?>
    <tr>
      <td><a href="<?= Html::e(Html::appUrl('/run.php?id=' . $row['scrape_run_id'])) ?>">#<?= Html::e($row['scrape_run_id']) ?></a></td>
      <td><?= Html::e($row['source_name']) ?></td>
      <td><?= Html::e($row['started_at']) ?></td>
      <td><?= Html::e($row['finished_at'] ?? '-') ?></td>
      <td><?= Html::statusBadge($row['status']) ?></td>
      <td><?= Html::e($row['http_status'] ?? '-') ?></td>
      <td><?= Html::e($row['product_count']) ?></td>
      <td><?= Html::e($row['new_product_count']) ?></td>
      <td class="error"><?= Html::e(trim(($row['error_type'] ?? '') . ' ' . ($row['error_message'] ?? ''))) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>

<h3>최근 로그</h3>
<section class="panel">
  <?php if ($logLines === []): ?>
    <p>읽을 로그가 없습니다.</p>
  <?php else: ?>
    <pre><?= Html::e(implode("\n", $logLines)) ?></pre>
  <?php endif; ?>
</section>
<?php
Html::layout('Diagnostics', ob_get_clean());
