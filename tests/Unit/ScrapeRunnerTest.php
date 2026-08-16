<?php

declare(strict_types=1);

namespace WatchScraper\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WatchScraper\Application\ScrapeRunner;
use WatchScraper\Database\Connection;
use WatchScraper\Http\HttpClient;
use WatchScraper\Repository\ProductRepository;
use WatchScraper\Scraper\Product;
use WatchScraper\Scraper\ScrapeResult;
use WatchScraper\Scraper\ScraperInterface;
use WatchScraper\Support\Logger;

final class ScrapeRunnerTest extends TestCase
{
    private string $dbPath;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/watch-test-' . bin2hex(random_bytes(4)) . '.sqlite';
        $_ENV['DB_PATH'] = $this->dbPath;
        $_ENV['DEBUG_MODE'] = 'true';
        $_ENV['FIRST_RUN_NOTIFY'] = 'false';
        $_ENV['SLACK_WEBHOOK_URL'] = '';
        Connection::reset();
        Connection::migrate();
        SequenceScraper::$queue = [];
        FailingScraper::$message = 'HTTP 500';
    }

    protected function tearDown(): void
    {
        Connection::reset();
        @unlink($this->dbPath);
    }

    public function testNewProductsCompareAgainstPreviousSuccessfulSnapshot(): void
    {
        SequenceScraper::$queue = [
            ['A', 'B', 'C'],
            ['A', 'B', 'C', 'D'],
        ];

        $first = $this->runner(SequenceScraper::class)->run('test');
        $second = $this->runner(SequenceScraper::class)->run('test');

        self::assertSame(0, $first['new_products']);
        self::assertSame(1, $second['new_products']);
    }

    public function testMissingProductBecomesInactiveOnSuccess(): void
    {
        SequenceScraper::$queue = [
            ['A', 'B', 'C'],
            ['A', 'C'],
        ];

        $this->runner(SequenceScraper::class)->run('test');
        $this->runner(SequenceScraper::class)->run('test');

        $rows = Connection::pdo()->query('SELECT external_id, is_active FROM products ORDER BY external_id')->fetchAll();
        self::assertSame([['external_id' => 'A', 'is_active' => 1], ['external_id' => 'B', 'is_active' => 0], ['external_id' => 'C', 'is_active' => 1]], $rows);
    }

    public function testFailureDoesNotDeactivateExistingProducts(): void
    {
        SequenceScraper::$queue = [['A', 'B', 'C']];
        $this->runner(SequenceScraper::class)->run('test');
        $this->runner(FailingScraper::class)->run('test');

        self::assertSame(3, (new ProductRepository(Connection::pdo()))->countActive());
    }

    public function testFailureBetweenSuccessesIsIgnoredForNewComparison(): void
    {
        SequenceScraper::$queue = [['A', 'B', 'C']];
        $this->runner(SequenceScraper::class)->run('test');
        $this->runner(FailingScraper::class)->run('test');
        SequenceScraper::$queue = [['A', 'B', 'C', 'D']];
        $third = $this->runner(SequenceScraper::class)->run('test');

        self::assertSame(1, $third['new_products']);
    }

    private function runner(string $scraperClass): ScrapeRunner
    {
        return new ScrapeRunner(Connection::pdo(), [[
            'name' => 'TEST',
            'url' => 'https://example.test/vacheron',
            'category' => 'test',
            'enabled' => true,
            'scraper' => $scraperClass,
        ]], new Logger(sys_get_temp_dir() . '/watch-test.log'));
    }
}

final class SequenceScraper implements ScraperInterface
{
    public static array $queue = [];

    public function __construct(array $source, HttpClient $http)
    {
    }

    public function scrape(): ScrapeResult
    {
        $ids = array_shift(self::$queue) ?? [];
        $products = array_map(fn (string $id): Product => new Product(
            'TEST',
            'test',
            $id,
            hash('sha256', $id),
            'Watch ' . $id,
            'Watch ' . $id,
            null,
            '100円',
            100,
            'https://example.test/products/' . strtolower($id),
            null,
            'in_stock'
        ), $ids);
        return new ScrapeResult($products, 200, ['https://example.test']);
    }
}

final class FailingScraper implements ScraperInterface
{
    public static string $message = 'failed';

    public function __construct(array $source, HttpClient $http)
    {
    }

    public function scrape(): ScrapeResult
    {
        throw new RuntimeException(self::$message);
    }
}
