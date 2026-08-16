<?php

declare(strict_types=1);

namespace WatchScraper\Scraper;

final class ScrapeResult
{
    /**
     * @param Product[] $products
     */
    public function __construct(
        public readonly array $products,
        public readonly ?int $httpStatus,
        public readonly array $visitedUrls = []
    ) {
    }
}
