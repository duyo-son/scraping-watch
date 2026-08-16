<?php

declare(strict_types=1);

namespace WatchScraper\Scraper\Sites;

use WatchScraper\Scraper\AbstractScraper;

final class CommitGinzaScraper extends AbstractScraper
{
    protected function cardSelectors(): array
    {
        return ['product-card', '.product-card', '[class*="product-card"]', '[data-product-id]'];
    }

    protected function enableLinkFallback(): bool
    {
        return true;
    }
}
