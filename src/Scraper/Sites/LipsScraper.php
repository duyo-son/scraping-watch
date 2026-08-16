<?php

declare(strict_types=1);

namespace WatchScraper\Scraper\Sites;

use WatchScraper\Scraper\AbstractScraper;

final class LipsScraper extends AbstractScraper
{
    protected function cardSelectors(): array
    {
        return ['.product_item', '.ec-shelfGrid__item', '[class*="product"]', '[class*="item"]'];
    }
}
