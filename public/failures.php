<?php

declare(strict_types=1);

use WatchScraper\Database\Connection;
use WatchScraper\Repository\ProductRepository;
use WatchScraper\View\Html;

require dirname(__DIR__) . '/bootstrap.php';

$rows = (new ProductRepository(Connection::pdo()))->failures();
ob_start();
?>
<h2>실패 기록</h2>
<div class="wide"><table>
<thead><tr><th>실행 시간</th><th>사이트</th><th>URL</th><th>HTTP</th><th>오류 종류</th><th>오류 메시지</th><th>소요</th><th>Run ID</th></tr></thead>
<tbody>
<?php foreach ($rows as $row): ?>
<tr>
  <td><?= Html::e($row['started_at']) ?></td><td><?= Html::e($row['source_name']) ?></td>
  <td><?= Html::link($row['source_url'], 'open') ?></td><td><?= Html::e($row['http_status'] ?? '-') ?></td>
  <td><?= Html::e($row['error_type']) ?></td><td class="error"><?= Html::e($row['error_message']) ?></td>
  <td><?= Html::e($row['duration_ms']) ?> ms</td><td><a href="/run.php?id=<?= Html::e($row['scrape_run_id']) ?>">#<?= Html::e($row['scrape_run_id']) ?></a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php
Html::layout('Failures', ob_get_clean());
