<?php

declare(strict_types=1);

namespace WatchScraper\Scraper\Sites;

use WatchScraper\Scraper\AbstractScraper;

final class GinzaRasinScraper extends AbstractScraper
{
    protected function cardSelectors(): array
    {
        return ['.item', '.item_box', '.prd_lst_unit', 'li[class*="item"]', '[class*="product"]'];
    }
}
