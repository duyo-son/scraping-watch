<?php

declare(strict_types=1);

namespace WatchScraper\Repository;

use PDO;

final class RunRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function lastRun(): ?array
    {
        $stmt = $this->pdo->query('SELECT * FROM scrape_runs ORDER BY started_at DESC, id DESC LIMIT 1');
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function recoverRunningRuns(int $olderThanMinutes, string $message): int
    {
        $threshold = date('Y-m-d H:i:s', time() - max(0, $olderThanMinutes) * 60);
        $stmt = $this->pdo->prepare(
            'SELECT id FROM scrape_runs
             WHERE status = "RUNNING" AND started_at <= :threshold
             ORDER BY started_at, id'
        );
        $stmt->execute(['threshold' => $threshold]);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        foreach ($ids as $id) {
            $this->markInterrupted($id, 'StaleRunningRun', $message);
        }

        return count($ids);
    }

    public function createRun(int $sourceCount, string $triggerType): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO scrape_runs (started_at, status, source_count, trigger_type)
             VALUES (:started_at, :status, :source_count, :trigger_type)'
        );
        $stmt->execute([
            'started_at' => date('Y-m-d H:i:s'),
            'status' => 'RUNNING',
            'source_count' => $sourceCount,
            'trigger_type' => $triggerType,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function finishRun(int $runId, string $status, int $success, int $failure, int $total, int $new, int $durationMs): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE scrape_runs
             SET finished_at = :finished_at, status = :status, success_count = :success_count,
                 failure_count = :failure_count, total_products = :total_products,
                 new_products = :new_products, duration_ms = :duration_ms
             WHERE id = :id'
        );
        $stmt->execute([
            'finished_at' => date('Y-m-d H:i:s'),
            'status' => $status,
            'success_count' => $success,
            'failure_count' => $failure,
            'total_products' => $total,
            'new_products' => $new,
            'duration_ms' => $durationMs,
            'id' => $runId,
        ]);
    }

    public function createSourceRun(int $runId, int $sourceId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO scrape_source_runs (scrape_run_id, source_id, started_at, status)
             VALUES (:scrape_run_id, :source_id, :started_at, :status)'
        );
        $stmt->execute([
            'scrape_run_id' => $runId,
            'source_id' => $sourceId,
            'started_at' => date('Y-m-d H:i:s'),
            'status' => 'RUNNING',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function finishSourceSuccess(int $sourceRunId, ?int $httpStatus, int $productCount, int $newCount, int $durationMs): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE scrape_source_runs
             SET finished_at = :finished_at, status = :status, http_status = :http_status,
                 product_count = :product_count, new_product_count = :new_product_count,
                 duration_ms = :duration_ms
             WHERE id = :id'
        );
        $stmt->execute([
            'finished_at' => date('Y-m-d H:i:s'),
            'status' => 'SUCCESS',
            'http_status' => $httpStatus,
            'product_count' => $productCount,
            'new_product_count' => $newCount,
            'duration_ms' => $durationMs,
            'id' => $sourceRunId,
        ]);
    }

    public function finishSourceFailure(int $sourceRunId, ?int $httpStatus, string $type, string $message, int $durationMs): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE scrape_source_runs
             SET finished_at = :finished_at, status = :status, http_status = :http_status,
                 duration_ms = :duration_ms, error_type = :error_type, error_message = :error_message
             WHERE id = :id'
        );
        $stmt->execute([
            'finished_at' => date('Y-m-d H:i:s'),
            'status' => 'FAILED',
            'http_status' => $httpStatus,
            'duration_ms' => $durationMs,
            'error_type' => $type,
            'error_message' => substr($message, 0, 1000),
            'id' => $sourceRunId,
        ]);
    }

    public function markInterrupted(int $runId, string $type, string $message): void
    {
        $now = date('Y-m-d H:i:s');
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE scrape_source_runs
                 SET finished_at = :finished_at,
                     status = "FAILED",
                     duration_ms = CAST((strftime("%s", :finished_at) - strftime("%s", started_at)) * 1000 AS INTEGER),
                     error_type = :error_type,
                     error_message = :error_message
                 WHERE scrape_run_id = :run_id AND status = "RUNNING"'
            );
            $stmt->execute([
                'finished_at' => $now,
                'error_type' => $type,
                'error_message' => substr($message, 0, 1000),
                'run_id' => $runId,
            ]);

            $counts = $this->sourceRunCounts($runId);
            $success = (int) ($counts['success_count'] ?? 0);
            $failure = (int) ($counts['failure_count'] ?? 0);
            $status = $success > 0 && $failure > 0 ? 'PARTIAL_SUCCESS' : ($success > 0 ? 'SUCCESS' : 'FAILED');

            $stmt = $this->pdo->prepare(
                'UPDATE scrape_runs
                 SET finished_at = :finished_at,
                     status = :status,
                     success_count = :success_count,
                     failure_count = :failure_count,
                     total_products = :total_products,
                     new_products = :new_products,
                     duration_ms = CAST((strftime("%s", :finished_at) - strftime("%s", started_at)) * 1000 AS INTEGER)
                 WHERE id = :id AND status = "RUNNING"'
            );
            $stmt->execute([
                'finished_at' => $now,
                'status' => $status,
                'success_count' => $success,
                'failure_count' => $failure,
                'total_products' => (int) ($counts['total_products'] ?? 0),
                'new_products' => (int) ($counts['new_products'] ?? 0),
                'id' => $runId,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function previousSuccessfulSourceRunId(int $sourceId, int $currentSourceRunId): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM scrape_source_runs
             WHERE source_id = :source_id AND status = "SUCCESS" AND id != :current_id
             ORDER BY finished_at DESC, id DESC LIMIT 1'
        );
        $stmt->execute(['source_id' => $sourceId, 'current_id' => $currentSourceRunId]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public function recentRuns(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM scrape_runs ORDER BY started_at DESC, id DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function run(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM scrape_runs WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function sourceRuns(int $runId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ssr.*, s.name source_name, s.url source_url, s.category source_category
             FROM scrape_source_runs ssr JOIN sources s ON s.id = ssr.source_id
             WHERE ssr.scrape_run_id = :run_id ORDER BY ssr.id'
        );
        $stmt->execute(['run_id' => $runId]);
        return $stmt->fetchAll();
    }

    public function recentSourceRuns(int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ssr.*, sr.status run_status, s.name source_name
             FROM scrape_source_runs ssr
             JOIN scrape_runs sr ON sr.id = ssr.scrape_run_id
             JOIN sources s ON s.id = ssr.source_id
             ORDER BY ssr.started_at DESC, ssr.id DESC LIMIT :limit'
        );
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function sourceRunCounts(int $runId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                SUM(CASE WHEN status = "SUCCESS" THEN 1 ELSE 0 END) success_count,
                SUM(CASE WHEN status = "FAILED" THEN 1 ELSE 0 END) failure_count,
                COALESCE(SUM(product_count), 0) total_products,
                COALESCE(SUM(new_product_count), 0) new_products
             FROM scrape_source_runs
             WHERE scrape_run_id = :run_id'
        );
        $stmt->execute(['run_id' => $runId]);
        return $stmt->fetch() ?: [];
    }
}
