<?php

declare(strict_types=1);

namespace WatchScraper\Slack;

use GuzzleHttp\Client;
use Throwable;
use WatchScraper\Repository\NotificationRepository;
use WatchScraper\Support\Env;
use WatchScraper\Support\Logger;

final class SlackNotifier
{
    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly Logger $logger
    ) {
    }

    public function notifyNewProducts(int $runId, array $products): void
    {
        $count = count($products);
        if ($count === 0) {
            return;
        }

        $lines = ["Vacheron Constantin 신규 재고 {$count}개", ''];
        foreach (array_slice($products, 0, 5) as $product) {
            $label = trim(($product['source_name'] ?? '') . ' - ' . ($product['model_name'] ?? $product['product_name'] ?? '상품'));
            if (!empty($product['reference_number'])) {
                $label .= ' ' . $product['reference_number'];
            }
            $lines[] = '・' . $label;
        }
        if ($count > 5) {
            $lines[] = '';
            $lines[] = '외 ' . ($count - 5) . '개';
        }

        $payload = ['text' => implode("\n", $lines)];
        $webhook = Env::nullableString('SLACK_WEBHOOK_URL');
        if ($webhook === null) {
            $this->logger->info('Slack disabled', ['run_id' => $runId, 'item_count' => $count]);
            $this->notifications->create($runId, 'new_products', $count, $payload, 'DISABLED');
            return;
        }

        try {
            $response = (new Client(['timeout' => 10, 'http_errors' => false]))->post($webhook, ['json' => $payload]);
            $status = $response->getStatusCode();
            $ok = $status >= 200 && $status < 300;
            $this->notifications->create($runId, 'new_products', $count, $payload, $ok ? 'SENT' : 'FAILED', $status, $ok ? null : (string) $response->getBody());
            $this->logger->info('Slack send', ['run_id' => $runId, 'item_count' => $count, 'http_status' => $status]);
        } catch (Throwable $e) {
            $this->notifications->create($runId, 'new_products', $count, $payload, 'FAILED', null, $e->getMessage());
            $this->logger->error('Slack send failed', ['run_id' => $runId, 'error' => $e->getMessage()]);
        }
    }
}
