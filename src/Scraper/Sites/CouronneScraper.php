<?php

declare(strict_types=1);

namespace WatchScraper\Scraper\Sites;

use WatchScraper\Scraper\AbstractScraper;

final class CouronneScraper extends AbstractScraper
{
    protected function cardSelectors(): array
    {
        return ['.item', '.item-list li', '[class*="product"]', '[class*="goods"]'];
    }
}
