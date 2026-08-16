<?php

declare(strict_types=1);

namespace WatchScraper\Support;

final class Text
{
    public static function clean(?string $value): ?string
    {
        $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';
        return $value === '' ? null : $value;
    }

    public static function priceJpy(?string $priceText): ?int
    {
        if ($priceText === null) {
            return null;
        }
        if (!preg_match('/([0-9][0-9,\s]*)\s*円/u', $priceText, $match) && !preg_match('/¥\s*([0-9][0-9,\s]*)/u', $priceText, $match)) {
            return null;
        }
        $number = preg_replace('/[^\d]/', '', $match[1]);
        return $number === '' ? null : (int) $number;
    }

    public static function reference(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }
        if (preg_match('/\b[0-9A-Z]{3,8}[A-Z]?\/[0-9A-Z]{3,8}(?:-[0-9A-Z]{2,8})?\b/u', $text, $match)) {
            return $match[0];
        }
        if (preg_match('/(?:型番|品番|Ref\.?|REF)\s*[:：]?\s*([0-9A-Z][0-9A-Z\/.-]{3,30})/iu', $text, $match)) {
            return trim($match[1]);
        }
        return null;
    }
}
