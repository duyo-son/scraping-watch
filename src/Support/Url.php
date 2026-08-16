<?php

declare(strict_types=1);

namespace WatchScraper\Support;

final class Url
{
    public static function absolute(?string $url, string $baseUrl): ?string
    {
        $url = trim((string) $url);
        if ($url === '' || str_starts_with($url, 'data:') || str_starts_with($url, 'javascript:')) {
            return null;
        }
        if (str_starts_with($url, '//')) {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
            return $scheme . ':' . $url;
        }
        if (preg_match('#^https?://#i', $url)) {
            return self::safe($url);
        }

        $parts = parse_url($baseUrl);
        if (!$parts || empty($parts['host'])) {
            return null;
        }
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'];
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        if (str_starts_with($url, '/')) {
            return self::safe($scheme . '://' . $host . $port . $url);
        }
        $path = $parts['path'] ?? '/';
        $dir = rtrim(str_replace('\\', '/', dirname($path)), '/');
        return self::safe($scheme . '://' . $host . $port . ($dir === '' ? '' : $dir) . '/' . $url);
    }

    public static function normalize(?string $url): ?string
    {
        $url = self::safe($url);
        if ($url === null) {
            return null;
        }
        $parts = parse_url($url);
        if (!$parts || empty($parts['host'])) {
            return null;
        }
        $scheme = strtolower($parts['scheme'] ?? 'https');
        $host = strtolower($parts['host']);
        $path = $parts['path'] ?? '/';
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            foreach (array_keys($query) as $key) {
                if (preg_match('/^(utm_|fbclid|gclid|yclid|_pos|_sid|_ss)/i', (string) $key)) {
                    unset($query[$key]);
                }
            }
            ksort($query);
        }
        $queryString = http_build_query($query);
        return $scheme . '://' . $host . rtrim($path, '/') . ($queryString === '' ? '' : '?' . $queryString);
    }

    public static function safe(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return null;
        }
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }
}
