<?php

declare(strict_types=1);

namespace WatchScraper\Scraper\Sites;

use WatchScraper\Scraper\AbstractScraper;

final class RodeoDriveScraper extends AbstractScraper
{
    protected function cardSelectors(): array
    {
        return ['[itemtype*="Product"]', '[class*="goods"]', '[class*="item"]'];
    }
}
