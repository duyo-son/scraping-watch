<?php

declare(strict_types=1);

namespace WatchScraper\Scraper\Sites;

use WatchScraper\Scraper\AbstractScraper;

final class KameKichiScraper extends AbstractScraper
{
    protected function cardSelectors(): array
    {
        return ['[class*="Product"]', '[class*="product"]', '[class*="item"]'];
    }

    protected function enableLinkFallback(): bool
    {
        return true;
    }
}
