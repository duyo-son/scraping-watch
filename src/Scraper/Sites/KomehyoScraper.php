<?php

declare(strict_types=1);

namespace WatchScraper\Scraper\Sites;

use WatchScraper\Scraper\AbstractScraper;

final class KomehyoScraper extends AbstractScraper
{
    protected function cardSelectors(): array
    {
        return ['[class*="product"]', '[class*="item"]', '[class*="goods"]'];
    }
}
