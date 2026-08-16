<?php

declare(strict_types=1);

namespace WatchScraper\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WatchScraper\Http\HttpClient;
use WatchScraper\Scraper\Sites\AlluScraper;
use WatchScraper\Scraper\Sites\BestVintageScraper;
use WatchScraper\Scraper\Sites\BluekScraper;
use WatchScraper\Scraper\Sites\CommitGinzaScraper;
use WatchScraper\Scraper\Sites\CouronneScraper;
use WatchScraper\Scraper\Sites\GinzaRasinScraper;
use WatchScraper\Scraper\Sites\GmtScraper;
use WatchScraper\Scraper\Sites\HousekihirobaScraper;
use WatchScraper\Scraper\Sites\JackroadScraper;
use WatchScraper\Scraper\Sites\KameKichiScraper;
use WatchScraper\Scraper\Sites\KomehyoScraper;
use WatchScraper\Scraper\Sites\LipsScraper;
use WatchScraper\Scraper\Sites\MoonphaseScraper;
use WatchScraper\Scraper\Sites\OkuraScraper;
use WatchScraper\Scraper\Sites\RodeoDriveScraper;

final class ScraperFixtureTest extends TestCase
{
    /**
     * @dataProvider scraperProvider
     */
    public function testScraperExtractsFixtureProducts(string $class, string $fixture): void
    {
        $html = file_get_contents(__DIR__ . '/../Fixtures/' . $fixture);
        $scraper = new $class([
            'name' => basename($fixture, '.html'),
            'category' => 'test',
            'url' => 'https://example.test/collections/vacheron',
        ], new HttpClient());

        $products = $scraper->parseProducts((string) $html, 'https://example.test/collections/vacheron');

        self::assertCount(2, $products);
        self::assertSame('Vacheron Constantin Overseas 4500V/110A-B128', $products[0]->productName);
        self::assertSame('4500V/110A-B128', $products[0]->referenceNumber);
        self::assertSame(3580000, $products[0]->priceJpy);
        self::assertSame('https://example.test/products/overseas-4500v', $products[0]->productUrl);
        self::assertSame('https://example.test/images/overseas.jpg', $products[0]->imageUrl);
    }

    public static function scraperProvider(): array
    {
        return [
            [CommitGinzaScraper::class, 'commit_ginza.html'],
            [GmtScraper::class, 'gmt.html'],
            [GinzaRasinScraper::class, 'ginza_rasin.html'],
            [JackroadScraper::class, 'jackroad.html'],
            [KameKichiScraper::class, 'kame_kichi.html'],
            [LipsScraper::class, 'lips.html'],
            [BestVintageScraper::class, 'best_vintage.html'],
            [AlluScraper::class, 'allu.html'],
            [OkuraScraper::class, 'okura.html'],
            [HousekihirobaScraper::class, 'housekihiroba.html'],
            [KomehyoScraper::class, 'komehyo.html'],
            [RodeoDriveScraper::class, 'rodeo_drive.html'],
            [CouronneScraper::class, 'couronne.html'],
            [MoonphaseScraper::class, 'moonphase.html'],
            [BluekScraper::class, 'bluek.html'],
        ];
    }
}
