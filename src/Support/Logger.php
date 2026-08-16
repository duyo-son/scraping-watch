<?php

declare(strict_types=1);

namespace WatchScraper\Support;

final class Logger
{
    public function __construct(private readonly string $path)
    {
    }

    public static function app(): self
    {
        return new self(dirname(__DIR__, 2) . '/storage/logs/app.log');
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        $safeContext = $this->redact($context);
        $line = sprintf(
            "[%s] %s %s %s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            $safeContext === [] ? '' : json_encode($safeContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        @file_put_contents($this->path, $line, FILE_APPEND | LOCK_EX);
    }

    private function redact(array $context): array
    {
        foreach ($context as $key => $value) {
            if (preg_match('/token|webhook|secret|password/i', (string) $key)) {
                $context[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $context[$key] = $this->redact($value);
            }
        }
        return $context;
    }
}
