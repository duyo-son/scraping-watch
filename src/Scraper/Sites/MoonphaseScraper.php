<?php

declare(strict_types=1);

namespace WatchScraper\Scraper\Sites;

use WatchScraper\Scraper\AbstractScraper;

final class MoonphaseScraper extends AbstractScraper
{
    protected function cardSelectors(): array
    {
        return ['[data-product-id]', '.product-card', '[class*="product"]', '[class*="item"]'];
    }
}
