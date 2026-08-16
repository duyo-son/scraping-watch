<?php

declare(strict_types=1);

namespace WatchScraper\View;

use WatchScraper\Support\Env;

final class Html
{
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function layout(string $title, string $content): void
    {
        $nav = [
            '/' => 'Dashboard',
            '/watches.php' => '현재 재고',
            '/runs.php' => '스크레이핑 실행 기록',
            '/failures.php' => '실패 기록',
            '/scrape.php' => '수동 실행',
            '/diagnostics.php' => '진단',
        ];
        echo '<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>' . self::e($title) . '</title><link rel="stylesheet" href="' . self::e(self::appUrl('/assets.css')) . '"></head><body>';
        echo '<header><h1>Vacheron 재고 감시</h1><nav>';
        foreach ($nav as $href => $label) {
            echo '<a href="' . self::e(self::appUrl($href)) . '">' . self::e($label) . '</a>';
        }
        echo '</nav></header><main>' . $content . '</main></body></html>';
    }

    public static function appUrl(string $path): string
    {
        $configuredBase = Env::string('APP_BASE_PATH', '');
        $base = $configuredBase !== '' ? $configuredBase : self::detectBasePath();
        $base = '/' . trim($base, '/');
        $base = $base === '/' ? '' : $base;
        return $base . '/' . ltrim($path, '/');
    }

    private static function detectBasePath(): string
    {
        $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        if ($scriptName === '') {
            return '';
        }

        $dir = str_replace('\\', '/', dirname($scriptName));
        if ($dir === '/' || $dir === '.') {
            return '';
        }

        if (str_ends_with($dir, '/public')) {
            $dir = substr($dir, 0, -7);
        }

        return $dir === '/' ? '' : $dir;
    }

    public static function statusBadge(?string $status): string
    {
        $status = $status ?: 'NO DATA';
        $class = strtolower(str_replace('_', '-', $status));
        return '<span class="badge ' . self::e($class) . '">' . self::e($status) . '</span>';
    }

    public static function img(?string $url): string
    {
        if ($url === null || $url === '') {
            return '<div class="thumb placeholder">No image</div>';
        }
        return '<img class="thumb" src="' . self::e($url) . '" loading="lazy" alt="" onerror="this.replaceWith(Object.assign(document.createElement(\'div\'),{className:\'thumb placeholder\',textContent:\'No image\'}))">';
    }

    public static function link(?string $url, string $label): string
    {
        if ($url === null || $url === '' || !preg_match('#^https?://#i', $url)) {
            return self::e($label);
        }
        return '<a href="' . self::e($url) . '" target="_blank" rel="noopener noreferrer">' . self::e($label) . '</a>';
    }

    public static function linkRaw(?string $url, string $html): string
    {
        if ($url === null || $url === '' || !preg_match('#^https?://#i', $url)) {
            return $html;
        }
        return '<a href="' . self::e($url) . '" target="_blank" rel="noopener noreferrer">' . $html . '</a>';
    }
}
