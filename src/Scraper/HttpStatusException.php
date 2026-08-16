<?php

declare(strict_types=1);

namespace WatchScraper\Scraper;

use RuntimeException;

final class HttpStatusException extends RuntimeException
{
    public function __construct(public readonly int $statusCode, string $message)
    {
        parent::__construct($message);
    }
}
