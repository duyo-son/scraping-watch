<?php

declare(strict_types=1);

use WatchScraper\Database\Connection;
use WatchScraper\Repository\RunRepository;
use WatchScraper\View\Html;

require dirname(__DIR__) . '/bootstrap.php';

$rows = (new RunRepository(Connection::pdo()))->recentRuns();
ob_start();
?>
<h2>스크레이핑 실행 기록</h2>
<div class="wide"><table>
<thead><tr><th>Run ID</th><th>시작</th><th>종료</th><th>소요</th><th>대상</th><th>성공</th><th>실패</th><th>상품</th><th>신규</th><th>상태</th></tr></thead>
<tbody>
<?php foreach ($rows as $row): ?>
<tr>
  <td><a href="<?= Html::e(Html::appUrl('/run.php?id=' . $row['id'])) ?>">#<?= Html::e($row['id']) ?></a></td>
  <td><?= Html::e($row['started_at']) ?></td><td><?= Html::e($row['finished_at']) ?></td>
  <td><?= Html::e($row['duration_ms']) ?> ms</td><td><?= Html::e($row['source_count']) ?></td>
  <td><?= Html::e($row['success_count']) ?></td><td><?= Html::e($row['failure_count']) ?></td>
  <td><?= Html::e($row['total_products']) ?></td><td><?= Html::e($row['new_products']) ?></td>
  <td><?= Html::statusBadge($row['status']) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php
Html::layout('Runs', ob_get_clean());
