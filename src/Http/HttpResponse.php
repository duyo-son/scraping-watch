<?php

declare(strict_types=1);

namespace WatchScraper\Http;

final class HttpResponse
{
    public function __construct(
        public readonly int $statusCode,
        public readonly array $headers,
        public readonly string $body,
        public readonly string $effectiveUrl
    ) {
    }
}
