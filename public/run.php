<?php

declare(strict_types=1);

use WatchScraper\Database\Connection;
use WatchScraper\Repository\ProductRepository;
use WatchScraper\Repository\RunRepository;
use WatchScraper\View\Html;

require dirname(__DIR__) . '/bootstrap.php';

$id = max(1, (int) ($_GET['id'] ?? 0));
$pdo = Connection::pdo();
$runs = new RunRepository($pdo);
$products = new ProductRepository($pdo);
$run = $runs->run($id);
if (!$run) {
    http_response_code(404);
    Html::layout('Not found', '<div class="panel">Run not found.</div>');
    exit;
}
$sourceRuns = $runs->sourceRuns($id);
ob_start();
?>
<h2>Run #<?= Html::e($run['id']) ?></h2>
<div class="panel small"><?= Html::e($run['started_at']) ?> / <?= Html::statusBadge($run['status']) ?> / 신규 <?= Html::e($run['new_products']) ?>개</div>
<?php foreach ($sourceRuns as $sourceRun): $items = $products->snapshotItems((int) $sourceRun['id']); ?>
<section class="panel">
  <h3><?= Html::e($sourceRun['source_name']) ?> <?= Html::statusBadge($sourceRun['status']) ?></h3>
  <p class="small">HTTP <?= Html::e($sourceRun['http_status'] ?? '-') ?> / 상품 <?= Html::e($sourceRun['product_count']) ?>개 / 신규 <?= Html::e($sourceRun['new_product_count']) ?>개 / <?= Html::e($sourceRun['duration_ms']) ?> ms</p>
  <?php if ($sourceRun['error_message']): ?><p class="error"><?= Html::e($sourceRun['error_type'] . ': ' . $sourceRun['error_message']) ?></p><?php endif; ?>
  <div class="items">
    <?php foreach ($items as $item): ?>
      <div class="item"><?= Html::img($item['image_url']) ?><div>
        <b><?= Html::link($item['product_url'], $item['product_name'] ?: '-') ?></b>
        <div><?= Html::e($item['model_name'] ?? '-') ?> / Ref <?= Html::e($item['reference_number'] ?? '-') ?></div>
        <div><?= Html::e($item['price_text'] ?? '-') ?></div>
      </div></div>
    <?php endforeach; ?>
  </div>
</section>
<?php endforeach; ?>
<?php
Html::layout('Run detail', ob_get_clean());
