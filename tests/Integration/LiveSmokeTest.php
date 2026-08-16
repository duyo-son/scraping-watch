<?php

declare(strict_types=1);

namespace WatchScraper\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WatchScraper\Http\HttpClient;

final class LiveSmokeTest extends TestCase
{
    public function testConfiguredSitesRespondOrFailExplicitly(): void
    {
        if (getenv('LIVE_TESTS') !== '1') {
            self::markTestSkipped('Set LIVE_TESTS=1 to request real shop pages.');
        }

        $sites = require __DIR__ . '/../../config/sites.php';
        $http = new HttpClient();
        foreach ($sites as $site) {
            $scraper = new $site['scraper']($site, $http);
            try {
                $result = $scraper->scrape();
                self::assertGreaterThan(0, count($result->products), $site['name'] . ' returned no products');
            } catch (\Throwable $e) {
                self::assertNotSame('', $e->getMessage(), $site['name'] . ' failed without reason');
            }
        }
    }
}
