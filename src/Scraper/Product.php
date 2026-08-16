<?php

declare(strict_types=1);

namespace WatchScraper\Scraper;

final class Product
{
    public function __construct(
        public readonly string $source,
        public readonly string $sourceCategory,
        public readonly ?string $externalId,
        public readonly string $identityKey,
        public readonly ?string $productName,
        public readonly ?string $modelName,
        public readonly ?string $referenceNumber,
        public readonly ?string $priceText,
        public readonly ?int $priceJpy,
        public readonly ?string $productUrl,
        public readonly ?string $imageUrl,
        public readonly ?string $stockStatus
    ) {
    }
}
