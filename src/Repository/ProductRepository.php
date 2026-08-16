<?php

declare(strict_types=1);

namespace WatchScraper\Repository;

use PDO;
use WatchScraper\Scraper\Product;

final class ProductRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function previousIdentityKeys(int $sourceRunId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.identity_key FROM scrape_items si JOIN products p ON p.id = si.product_id
             WHERE si.scrape_source_run_id = :id'
        );
        $stmt->execute(['id' => $sourceRunId]);
        return array_fill_keys(array_column($stmt->fetchAll(), 'identity_key'), true);
    }

    public function upsert(Product $product, int $sourceId, string $now): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO products (
                source_id, external_id, identity_key, product_name, model_name, reference_number,
                price_text, price_jpy, product_url, image_url, is_active,
                first_seen_at, last_seen_at, created_at, updated_at
             ) VALUES (
                :source_id, :external_id, :identity_key, :product_name, :model_name, :reference_number,
                :price_text, :price_jpy, :product_url, :image_url, 1,
                :first_seen_at, :last_seen_at, :created_at, :updated_at
             )
             ON CONFLICT(source_id, identity_key) DO UPDATE SET
                external_id = excluded.external_id,
                product_name = excluded.product_name,
                model_name = excluded.model_name,
                reference_number = excluded.reference_number,
                price_text = excluded.price_text,
                price_jpy = excluded.price_jpy,
                product_url = excluded.product_url,
                image_url = excluded.image_url,
                is_active = 1,
                last_seen_at = excluded.last_seen_at,
                updated_at = excluded.updated_at'
        );
        $stmt->execute([
            'source_id' => $sourceId,
            'external_id' => $product->externalId,
            'identity_key' => $product->identityKey,
            'product_name' => $product->productName,
            'model_name' => $product->modelName,
            'reference_number' => $product->referenceNumber,
            'price_text' => $product->priceText,
            'price_jpy' => $product->priceJpy,
            'product_url' => $product->productUrl,
            'image_url' => $product->imageUrl,
            'first_seen_at' => $now,
            'last_seen_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $select = $this->pdo->prepare('SELECT id FROM products WHERE source_id = :source_id AND identity_key = :identity_key');
        $select->execute(['source_id' => $sourceId, 'identity_key' => $product->identityKey]);
        return (int) $select->fetchColumn();
    }

    public function insertSnapshotItem(Product $product, int $runId, int $sourceRunId, int $productId, int $sourceId, string $now): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO scrape_items (
                scrape_run_id, scrape_source_run_id, product_id, source_id, product_name, model_name,
                reference_number, price_text, price_jpy, product_url, image_url, created_at
             ) VALUES (
                :scrape_run_id, :scrape_source_run_id, :product_id, :source_id, :product_name, :model_name,
                :reference_number, :price_text, :price_jpy, :product_url, :image_url, :created_at
             )'
        );
        $stmt->execute([
            'scrape_run_id' => $runId,
            'scrape_source_run_id' => $sourceRunId,
            'product_id' => $productId,
            'source_id' => $sourceId,
            'product_name' => $product->productName,
            'model_name' => $product->modelName,
            'reference_number' => $product->referenceNumber,
            'price_text' => $product->priceText,
            'price_jpy' => $product->priceJpy,
            'product_url' => $product->productUrl,
            'image_url' => $product->imageUrl,
            'created_at' => $now,
        ]);
    }

    public function deactivateMissing(int $sourceId, array $currentIdentityKeys, string $now): void
    {
        if ($currentIdentityKeys === []) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($currentIdentityKeys), '?'));
        $sql = "UPDATE products SET is_active = 0, updated_at = ? WHERE source_id = ? AND is_active = 1 AND identity_key NOT IN ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$now, $sourceId], array_values($currentIdentityKeys)));
    }

    public function current(array $filters = [], string $sort = 'priority_new'): array
    {
        $where = ['p.is_active = 1'];
        $params = [];
        foreach (['source_id' => 'p.source_id', 'category' => 's.category'] as $key => $column) {
            if (($filters[$key] ?? '') !== '') {
                $where[] = "$column = :$key";
                $params[$key] = $filters[$key];
            }
        }
        foreach (['model' => 'p.model_name', 'reference' => 'p.reference_number', 'price' => 'p.price_text'] as $key => $column) {
            if (($filters[$key] ?? '') !== '') {
                $where[] = "$column LIKE :$key";
                $params[$key] = '%' . $filters[$key] . '%';
            }
        }
        $order = match ($sort) {
            'priority_new' => 'CASE WHEN lr.started_at IS NOT NULL AND p.first_seen_at >= lr.started_at THEN 0 ELSE 1 END ASC, CASE s.category WHEN "최우선" THEN 0 WHEN "우선" THEN 1 ELSE 2 END ASC, p.first_seen_at DESC, p.last_seen_at DESC, s.name ASC',
            'priority' => 'CASE s.category WHEN "최우선" THEN 0 WHEN "우선" THEN 1 ELSE 2 END ASC, p.first_seen_at DESC, p.last_seen_at DESC, s.name ASC',
            'newest' => 'p.first_seen_at DESC, p.last_seen_at DESC, p.id DESC',
            'price_asc' => 'p.price_jpy IS NULL, p.price_jpy ASC',
            'price_desc' => 'p.price_jpy IS NULL, p.price_jpy DESC',
            'source' => 's.name ASC, p.last_seen_at DESC',
            default => 'CASE WHEN lr.started_at IS NOT NULL AND p.first_seen_at >= lr.started_at THEN 0 ELSE 1 END ASC, CASE s.category WHEN "최우선" THEN 0 WHEN "우선" THEN 1 ELSE 2 END ASC, p.first_seen_at DESC, p.last_seen_at DESC, s.name ASC',
        };
        $stmt = $this->pdo->prepare(
            'SELECT p.*, s.name source_name, s.category source_category,
                    CASE WHEN lr.started_at IS NOT NULL AND p.first_seen_at >= lr.started_at THEN 1 ELSE 0 END AS is_new_from_latest_run
             FROM products p JOIN sources s ON s.id = p.source_id
             LEFT JOIN (
                SELECT started_at FROM scrape_runs
                WHERE status IN ("SUCCESS", "PARTIAL_SUCCESS")
                ORDER BY started_at DESC, id DESC
                LIMIT 1
             ) lr ON 1 = 1
             WHERE ' . implode(' AND ', $where) . " ORDER BY $order LIMIT 500"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function snapshotItems(int $sourceRunId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT si.*, s.name source_name, s.category source_category
             FROM scrape_items si JOIN sources s ON s.id = si.source_id
             WHERE si.scrape_source_run_id = :id ORDER BY si.id'
        );
        $stmt->execute(['id' => $sourceRunId]);
        return $stmt->fetchAll();
    }

    public function countActive(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn();
    }

    public function dashboardSourceStatuses(): array
    {
        return $this->pdo->query(
            'SELECT s.name, s.category, s.url, latest.status, latest.http_status, latest.product_count, latest.new_product_count, latest.error_message
             FROM sources s
             LEFT JOIN scrape_source_runs latest ON latest.id = (
                SELECT id FROM scrape_source_runs x WHERE x.source_id = s.id ORDER BY x.started_at DESC, x.id DESC LIMIT 1
             )
             ORDER BY s.category, s.name'
        )->fetchAll();
    }

    public function failures(int $limit = 100): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ssr.*, s.name source_name, s.url source_url, r.started_at run_started_at
             FROM scrape_source_runs ssr
             JOIN sources s ON s.id = ssr.source_id
             JOIN scrape_runs r ON r.id = ssr.scrape_run_id
             WHERE ssr.status = "FAILED"
             ORDER BY ssr.started_at DESC, ssr.id DESC LIMIT :limit'
        );
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function productsByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT p.*, s.name source_name, s.category source_category
             FROM products p JOIN sources s ON s.id = p.source_id
             WHERE p.id IN ($placeholders) ORDER BY p.last_seen_at DESC"
        );
        $stmt->execute(array_values($ids));
        return $stmt->fetchAll();
    }
}
