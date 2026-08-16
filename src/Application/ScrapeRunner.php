<?php

declare(strict_types=1);

namespace WatchScraper\Application;

use PDO;
use Throwable;
use WatchScraper\Http\HttpClient;
use WatchScraper\Repository\NotificationRepository;
use WatchScraper\Repository\ProductRepository;
use WatchScraper\Repository\RunRepository;
use WatchScraper\Repository\SourceRepository;
use WatchScraper\Scraper\HttpStatusException;
use WatchScraper\Scraper\ScraperInterface;
use WatchScraper\Slack\SlackNotifier;
use WatchScraper\Support\Env;
use WatchScraper\Support\Logger;

final class ScrapeRunner
{
    private SourceRepository $sources;
    private RunRepository $runs;
    private ProductRepository $products;

    public function __construct(
        private readonly PDO $pdo,
        private readonly array $siteConfigs,
        private readonly Logger $logger
    ) {
        $this->sources = new SourceRepository($pdo);
        $this->runs = new RunRepository($pdo);
        $this->products = new ProductRepository($pdo);
    }

    public function shouldSkipForInterval(): ?string
    {
        if (Env::bool('DEBUG_MODE', false)) {
            return null;
        }
        $last = $this->runs->lastRun();
        if ($last === null) {
            return null;
        }
        $minInterval = Env::int('SCRAPE_MIN_INTERVAL_MINUTES', 30);
        $elapsed = time() - strtotime($last['started_at']);
        if ($elapsed < $minInterval * 60) {
            return 'last scrape was less than ' . $minInterval . ' minutes ago';
        }
        return null;
    }

    public function run(string $triggerType = 'http'): array
    {
        $started = microtime(true);
        $enabledSources = $this->sources->enabledWithConfig($this->siteConfigs);
        $runId = $this->runs->createRun(count($enabledSources), $triggerType);
        $this->logger->info('scrape start', ['run_id' => $runId, 'source_count' => count($enabledSources)]);

        $success = 0;
        $failure = 0;
        $totalProducts = 0;
        $newProductIds = [];
        $http = new HttpClient();

        foreach ($enabledSources as $source) {
            $sourceStarted = microtime(true);
            $sourceRunId = $this->runs->createSourceRun($runId, (int) $source['id']);
            $this->logger->info('source start', ['run_id' => $runId, 'source' => $source['name']]);
            $httpStatus = null;

            try {
                $scraper = $this->makeScraper($source, $http);
                $result = $scraper->scrape();
                $httpStatus = $result->httpStatus;
                $applied = $this->applySuccessfulResult($runId, $sourceRunId, $source, $result->products);
                $duration = (int) round((microtime(true) - $sourceStarted) * 1000);
                $this->runs->finishSourceSuccess($sourceRunId, $httpStatus, count($result->products), count($applied['new_product_ids']), $duration);
                $success++;
                $totalProducts += count($result->products);
                $newProductIds = array_merge($newProductIds, $applied['new_product_ids']);
                $this->logger->info('source success', ['source' => $source['name'], 'products' => count($result->products), 'new' => count($applied['new_product_ids']), 'http_status' => $httpStatus]);
            } catch (Throwable $e) {
                if ($e instanceof HttpStatusException) {
                    $httpStatus = $e->statusCode;
                }
                $duration = (int) round((microtime(true) - $sourceStarted) * 1000);
                $this->runs->finishSourceFailure($sourceRunId, $httpStatus, basename(str_replace('\\', '/', $e::class)), $e->getMessage(), $duration);
                $failure++;
                $this->logger->error('source failure', ['source' => $source['name'], 'http_status' => $httpStatus, 'error' => $e->getMessage()]);
            }
        }

        $newProductIds = array_values(array_unique($newProductIds));
        $status = $failure === 0 ? 'SUCCESS' : ($success > 0 ? 'PARTIAL_SUCCESS' : 'FAILED');
        $duration = (int) round((microtime(true) - $started) * 1000);
        $this->runs->finishRun($runId, $status, $success, $failure, $totalProducts, count($newProductIds), $duration);

        $newProducts = $this->products->productsByIds($newProductIds);
        (new SlackNotifier(new NotificationRepository($this->pdo), $this->logger))->notifyNewProducts($runId, $newProducts);

        $this->logger->info('scrape finished', ['run_id' => $runId, 'status' => $status, 'new' => count($newProductIds)]);
        return [
            'status' => $status,
            'run_id' => $runId,
            'success_count' => $success,
            'failure_count' => $failure,
            'total_products' => $totalProducts,
            'new_products' => count($newProductIds),
            'duration_ms' => $duration,
        ];
    }

    /**
     * @param array<int,\WatchScraper\Scraper\Product> $scrapedProducts
     * @return array{new_product_ids: array<int,int>}
     */
    private function applySuccessfulResult(int $runId, int $sourceRunId, array $source, array $scrapedProducts): array
    {
        $now = date('Y-m-d H:i:s');
        $newProductIds = [];
        $this->pdo->beginTransaction();
        try {
            $previousRunId = $this->runs->previousSuccessfulSourceRunId((int) $source['id'], $sourceRunId);
            $previousKeys = $previousRunId === null ? [] : $this->products->previousIdentityKeys($previousRunId);
            $firstRunNotify = Env::bool('FIRST_RUN_NOTIFY', false);
            $isFirstSuccess = $previousRunId === null;
            $currentKeys = [];

            foreach ($scrapedProducts as $product) {
                $productId = $this->products->upsert($product, (int) $source['id'], $now);
                $this->products->insertSnapshotItem($product, $runId, $sourceRunId, $productId, (int) $source['id'], $now);
                $currentKeys[] = $product->identityKey;
                if ((!$isFirstSuccess || $firstRunNotify) && !isset($previousKeys[$product->identityKey])) {
                    $newProductIds[] = $productId;
                }
            }

            $this->products->deactivateMissing((int) $source['id'], $currentKeys, $now);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return ['new_product_ids' => $newProductIds];
    }

    private function makeScraper(array $source, HttpClient $http): ScraperInterface
    {
        $class = $source['scraper'];
        return new $class($source, $http);
    }
}
