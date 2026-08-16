<?php

declare(strict_types=1);

use WatchScraper\Database\Connection;
use WatchScraper\Repository\ProductRepository;
use WatchScraper\Repository\SourceRepository;
use WatchScraper\View\Html;

require dirname(__DIR__) . '/bootstrap.php';

$pdo = Connection::pdo();
$repo = new ProductRepository($pdo);
$sources = (new SourceRepository($pdo))->all();
$filters = [
    'source_id' => $_GET['source_id'] ?? '',
    'category' => $_GET['category'] ?? '',
    'model' => $_GET['model'] ?? '',
    'reference' => $_GET['reference'] ?? '',
    'price' => $_GET['price'] ?? '',
];
$sort = $_GET['sort'] ?? 'priority_new';
$rows = $repo->current($filters, $sort);
ob_start();
?>
<h2>현재 재고</h2>
<form class="filters" method="get">
  <select name="source_id"><option value="">전체 사이트</option><?php foreach ($sources as $s): ?><option value="<?= Html::e($s['id']) ?>" <?= (string)$filters['source_id'] === (string)$s['id'] ? 'selected' : '' ?>><?= Html::e($s['name']) ?></option><?php endforeach; ?></select>
  <select name="category"><option value="">전체 우선순위</option><option <?= $filters['category']==='최우선'?'selected':'' ?>>최우선</option><option <?= $filters['category']==='우선'?'selected':'' ?>>우선</option></select>
  <input name="model" placeholder="모델명" value="<?= Html::e($filters['model']) ?>">
  <input name="reference" placeholder="Reference" value="<?= Html::e($filters['reference']) ?>">
  <input name="price" placeholder="가격" value="<?= Html::e($filters['price']) ?>">
  <select name="sort"><option value="priority_new" <?= $sort==='priority_new'?'selected':'' ?>>신규 우선 + 우선도순</option><option value="priority" <?= $sort==='priority'?'selected':'' ?>>우선도순</option><option value="newest" <?= $sort==='newest'?'selected':'' ?>>신규순</option><option value="price_asc" <?= $sort==='price_asc'?'selected':'' ?>>가격 낮은순</option><option value="price_desc" <?= $sort==='price_desc'?'selected':'' ?>>가격 높은순</option><option value="source" <?= $sort==='source'?'selected':'' ?>>판매 사이트</option></select>
  <button>필터</button>
</form>
<div class="wide"><table>
<thead><tr><th>이미지</th><th>판매 사이트</th><th>우선순위</th><th>상품명</th><th>모델명</th><th>Reference</th><th>가격</th><th>최초 발견</th><th>마지막 확인</th></tr></thead>
<tbody>
<?php foreach ($rows as $row): ?>
<tr>
  <td><?= Html::linkRaw($row['product_url'], Html::img($row['image_url'])) ?></td>
  <td><?= Html::e($row['source_name']) ?></td><td><?= Html::e($row['source_category']) ?></td>
  <td><?php if ((int)($row['is_new_from_latest_run'] ?? 0) === 1): ?><span class="badge success">NEW</span> <?php endif; ?><?= Html::link($row['product_url'], $row['product_name'] ?: '-') ?></td>
  <td><?= Html::e($row['model_name'] ?? '-') ?></td><td><?= Html::e($row['reference_number'] ?? '-') ?></td>
  <td><?= Html::e($row['price_text'] ?? '-') ?></td><td><?= Html::e($row['first_seen_at']) ?></td><td><?= Html::e($row['last_seen_at']) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php
Html::layout('Watches', ob_get_clean());
