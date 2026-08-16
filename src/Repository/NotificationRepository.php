<?php

declare(strict_types=1);

namespace WatchScraper\Repository;

use PDO;

final class NotificationRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(int $runId, string $type, int $itemCount, array $payload, string $status, ?int $httpStatus = null, ?string $error = null): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO notifications (scrape_run_id, type, item_count, payload, status, http_status, error_message, created_at)
             VALUES (:scrape_run_id, :type, :item_count, :payload, :status, :http_status, :error_message, :created_at)'
        );
        $stmt->execute([
            'scrape_run_id' => $runId,
            'type' => $type,
            'item_count' => $itemCount,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => $status,
            'http_status' => $httpStatus,
            'error_message' => $error,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
