<?php

declare(strict_types=1);

namespace WatchScraper\Scraper\Sites;

use WatchScraper\Scraper\AbstractScraper;

final class GmtScraper extends AbstractScraper
{
    protected function cardSelectors(): array
    {
        return ['.itemList li', '.searchResult li', '[class*="item"]', '[class*="goods"]'];
    }
}
