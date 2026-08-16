<?php

declare(strict_types=1);

use WatchScraper\Database\Connection;
use WatchScraper\Repository\ProductRepository;
use WatchScraper\Repository\RunRepository;
use WatchScraper\View\Html;

require dirname(__DIR__) . '/bootstrap.php';

$pdo = Connection::pdo();
$runs = new RunRepository($pdo);
$products = new ProductRepository($pdo);
$last = $runs->lastRun();
$active = $products->countActive();
$statuses = $products->dashboardSourceStatuses();

ob_start();
?>
<section class="grid">
  <div class="metric">마지막 실행 시간<b><?= Html::e($last['started_at'] ?? '-') ?></b></div>
  <div class="metric">마지막 실행 상태<b><?= $last ? Html::statusBadge($last['status']) : '-' ?></b></div>
  <div class="metric">성공 사이트 수<b><?= Html::e($last['success_count'] ?? 0) ?></b></div>
  <div class="metric">실패 사이트 수<b><?= Html::e($last['failure_count'] ?? 0) ?></b></div>
  <div class="metric">현재 재고 수<b><?= Html::e($active) ?></b></div>
  <div class="metric">이번 실행 신규 수<b><?= Html::e($last['new_products'] ?? 0) ?></b></div>
</section>
<h2>사이트별 현재 상태</h2>
<div class="wide"><table>
  <thead><tr><th>사이트</th><th>우선순위</th><th>상태</th><th>HTTP</th><th>상품</th><th>신규</th><th>오류</th></tr></thead>
  <tbody>
  <?php foreach ($statuses as $row): ?>
    <tr>
      <td><?= Html::link($row['url'], $row['name']) ?></td>
      <td><?= Html::e($row['category']) ?></td>
      <td><?= Html::statusBadge($row['status']) ?></td>
      <td><?= Html::e($row['http_status'] ?? '-') ?></td>
      <td><?= Html::e($row['product_count'] ?? 0) ?></td>
      <td><?= Html::e($row['new_product_count'] ?? 0) ?></td>
      <td class="error"><?= Html::e($row['error_message'] ?? '') ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php
Html::layout('Dashboard', ob_get_clean());
