<?php

declare(strict_types=1);

namespace WatchScraper\Scraper\Sites;

use WatchScraper\Scraper\AbstractScraper;

final class AlluScraper extends AbstractScraper
{
    protected function cardSelectors(): array
    {
        return ['[class*="product"]', '[class*="Product"]', '[class*="item"]'];
    }
}
