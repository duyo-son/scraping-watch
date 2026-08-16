<?php

declare(strict_types=1);

namespace WatchScraper\Scraper;

interface ScraperInterface
{
    public function scrape(): ScrapeResult;
}
