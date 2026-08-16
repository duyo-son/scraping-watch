<?php

declare(strict_types=1);

namespace WatchScraper\Scraper\Sites;

use WatchScraper\Scraper\AbstractScraper;

final class BluekScraper extends AbstractScraper
{
    protected function cardSelectors(): array
    {
        return ['[data-product-id]', '[class*="fs-c-productList__list__item"]', '[class*="product"]'];
    }
}
