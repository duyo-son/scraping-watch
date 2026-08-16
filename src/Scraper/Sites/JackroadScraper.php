<?php

declare(strict_types=1);

namespace WatchScraper\Scraper\Sites;

use WatchScraper\Scraper\AbstractScraper;

final class JackroadScraper extends AbstractScraper
{
    protected function cardSelectors(): array
    {
        return ['[itemtype*="Product"]', '.goods_', '.item_', 'li[class*="item"]'];
    }
}
