<?php

declare(strict_types=1);

namespace WatchScraper\Scraper;

use Symfony\Component\DomCrawler\Crawler;
use WatchScraper\Http\HttpClient;
use WatchScraper\Support\Env;
use WatchScraper\Support\Text;
use WatchScraper\Support\Url;

abstract class AbstractScraper implements ScraperInterface
{
    /** @var array<string,mixed> */
    protected array $source;

    public function __construct(array $source, protected readonly HttpClient $http)
    {
        $this->source = $source;
    }

    public function scrape(): ScrapeResult
    {
        $visited = [];
        $products = [];
        $nextUrl = $this->source['url'];
        $httpStatus = null;
        $maxPages = Env::int('SCRAPER_MAX_PAGES', 8);

        for ($page = 1; $page <= $maxPages && $nextUrl !== null; $page++) {
            if (isset($visited[$nextUrl])) {
                break;
            }
            $visited[$nextUrl] = true;
            $response = $this->http->get($nextUrl);
            $httpStatus = $response->statusCode;
            if ($response->statusCode < 200 || $response->statusCode >= 300) {
                throw new HttpStatusException($response->statusCode, 'HTTP ' . $response->statusCode . ' while fetching ' . $nextUrl);
            }

            $pageProducts = $this->parseProducts($response->body, $nextUrl);
            foreach ($pageProducts as $product) {
                $products[$product->identityKey] = $product;
            }

            $nextUrl = $this->nextPageUrl($response->body, $nextUrl, $page);
        }

        if ($products === [] && !$this->allowEmptyResult()) {
            throw new ParseException('HTTP 200 but parser returned unexpected empty list');
        }

        return new ScrapeResult(array_values($products), $httpStatus, array_keys($visited));
    }

    /**
     * @return Product[]
     */
    public function parseProducts(string $html, string $pageUrl): array
    {
        $products = [];
        foreach ($this->extractJsonLd($html, $pageUrl) as $product) {
            $products[$product->identityKey] = $product;
        }

        foreach ($this->extractEmbeddedShopifyJson($html, $pageUrl) as $product) {
            $products[$product->identityKey] = $product;
        }

        foreach ($this->extractDomCards($html, $pageUrl) as $product) {
            $products[$product->identityKey] = $product;
        }

        return array_values($products);
    }

    protected function allowEmptyResult(): bool
    {
        return false;
    }

    /**
     * @return string[]
     */
    protected function cardSelectors(): array
    {
        return [
            '[data-product-id]',
            '[data-product]',
            '[class*="product-card"]',
            '[class*="product_item"]',
            '[class*="product-item"]',
            '[class*="goods"]',
            '[class*="item"]',
            'li',
        ];
    }

    /**
     * @return string[]
     */
    protected function nameSelectors(): array
    {
        return ['[itemprop="name"]', '.product-title', '.product_name', '.item-name', '.name', 'h2', 'h3', 'h4', 'a'];
    }

    /**
     * @return string[]
     */
    protected function priceSelectors(): array
    {
        return ['[itemprop="price"]', '[class*="price"]', '[class*="Price"]', '.price', '.selling_price'];
    }

    protected function nextPageUrl(string $html, string $pageUrl, int $page): ?string
    {
        $crawler = new Crawler($html, $pageUrl);
        $selectors = [
            'a[rel="next"]',
            'link[rel="next"]',
            '.pagination a[rel="next"]',
            '.pager a[rel="next"]',
            'a.next',
            'a[aria-label*="Next"]',
            'a[aria-label*="次"]',
        ];
        foreach ($selectors as $selector) {
            $node = $crawler->filter($selector);
            if ($node->count() > 0) {
                $href = $node->first()->attr('href');
                return Url::absolute($href, $pageUrl);
            }
        }
        $links = $crawler->filter('a')->reduce(function (Crawler $node): bool {
            $text = Text::clean($node->text('')) ?? '';
            return in_array($text, ['次へ', '次', 'Next', '>', '›'], true);
        });
        if ($links->count() > 0) {
            return Url::absolute($links->first()->attr('href'), $pageUrl);
        }
        return null;
    }

    /**
     * @return Product[]
     */
    private function extractJsonLd(string $html, string $pageUrl): array
    {
        $crawler = new Crawler($html, $pageUrl);
        $products = [];
        $crawler->filter('script[type="application/ld+json"]')->each(function (Crawler $node) use (&$products, $pageUrl): void {
            $domNode = $node->getNode(0);
            $json = trim($domNode?->textContent ?? '');
            $data = json_decode($json, true);
            if (!is_array($data)) {
                return;
            }
            foreach ($this->flattenStructuredData($data) as $item) {
                if (!is_array($item) || !$this->isProductData($item)) {
                    continue;
                }
                $product = $this->productFromArray($item, $pageUrl);
                if ($product !== null) {
                    $products[$product->identityKey] = $product;
                }
            }
        });
        return array_values($products);
    }

    /**
     * @return Product[]
     */
    private function extractEmbeddedShopifyJson(string $html, string $pageUrl): array
    {
        $products = [];
        if (!preg_match_all('/"product"\s*:\s*(\{.*?\})\s*,\s*"variant"/s', $html, $matches)) {
            return [];
        }
        foreach ($matches[1] as $json) {
            $data = json_decode($json, true);
            if (!is_array($data)) {
                continue;
            }
            $product = $this->productFromArray($data, $pageUrl);
            if ($product !== null) {
                $products[$product->identityKey] = $product;
            }
        }
        return array_values($products);
    }

    /**
     * @return Product[]
     */
    private function extractDomCards(string $html, string $pageUrl): array
    {
        $crawler = new Crawler($html, $pageUrl);
        $products = [];
        foreach ($this->cardSelectors() as $selector) {
            $crawler->filter($selector)->each(function (Crawler $card) use (&$products, $pageUrl): void {
                $product = $this->productFromCard($card, $pageUrl);
                if ($product !== null) {
                    $products[$product->identityKey] = $product;
                }
            });
            if (count($products) >= 2) {
                break;
            }
        }
        if ($this->enableLinkFallback()) {
            $crawler->filter('a[href]')->each(function (Crawler $link) use (&$products, $pageUrl): void {
                $product = $this->productFromLink($link, $pageUrl);
                if ($product !== null) {
                    $products[$product->identityKey] = $product;
                }
            });
        }
        return array_values($products);
    }

    protected function enableLinkFallback(): bool
    {
        return false;
    }

    private function productFromLink(Crawler $link, string $pageUrl): ?Product
    {
        $href = $link->attr('href');
        $productUrl = Url::absolute($href, $pageUrl);
        if ($productUrl === null || !$this->looksLikeProductUrl($productUrl)) {
            return null;
        }

        $text = Text::clean($link->text('')) ?? '';
        $img = $link->filter('img')->first();
        $imageUrl = null;
        $imgAlt = null;
        if ($img->count() > 0) {
            $imgAlt = Text::clean($img->attr('alt'));
            foreach (['src', 'data-src', 'data-original', 'data-lazy', 'data-srcset'] as $attr) {
                $imageUrl = Url::absolute((string) $img->attr($attr), $pageUrl);
                if ($imageUrl !== null) {
                    break;
                }
            }
        }

        $name = Text::clean($link->attr('title') ?: $link->attr('aria-label') ?: $text ?: $imgAlt);
        $combined = trim(($name ?? '') . ' ' . $text . ' ' . $productUrl);
        if ($name === null || !$this->containsBrandHint($combined)) {
            return null;
        }

        return $this->makeProduct([
            'name' => $name,
            'model_name' => $name,
            'reference_number' => Text::reference($combined),
            'price_text' => $this->priceFromText($text),
            'url' => $productUrl,
            'image' => $imageUrl,
            'external_id' => null,
            'stock_status' => str_contains($text, '売り切れ') || str_contains(strtolower($text), 'sold out') ? 'sold_out' : 'in_stock',
        ], $pageUrl);
    }

    private function productFromCard(Crawler $card, string $pageUrl): ?Product
    {
        $text = Text::clean($card->text('')) ?? '';
        if ($text === '' || strlen($text) > 5000) {
            return null;
        }
        $link = $card->filter('a[href]')->first();
        if ($link->count() === 0) {
            return null;
        }
        $productUrl = Url::absolute($link->attr('href'), $pageUrl);
        if ($productUrl === null || !$this->looksLikeProductUrl($productUrl)) {
            return null;
        }

        $name = null;
        foreach ($this->nameSelectors() as $selector) {
            $nodes = $card->filter($selector);
            if ($nodes->count() > 0) {
                $name = Text::clean($nodes->first()->attr('title') ?: $nodes->first()->text(''));
                if ($name !== null && strlen($name) >= 3) {
                    break;
                }
            }
        }
        $name ??= Text::clean($link->attr('title') ?: $link->text(''));
        $name ??= substr($text, 0, 140);

        $priceText = null;
        foreach ($this->priceSelectors() as $selector) {
            $nodes = $card->filter($selector);
            if ($nodes->count() > 0) {
                $priceText = Text::clean($nodes->first()->text(''));
                if (Text::priceJpy($priceText) !== null) {
                    break;
                }
            }
        }
        $priceText ??= $this->priceFromText($text);

        if ($priceText === null && !$this->containsBrandHint($text . ' ' . $productUrl)) {
            return null;
        }

        $imageUrl = null;
        $img = $card->filter('img')->first();
        if ($img->count() > 0) {
            foreach (['src', 'data-src', 'data-original', 'data-lazy', 'data-srcset'] as $attr) {
                $imageUrl = Url::absolute((string) $img->attr($attr), $pageUrl);
                if ($imageUrl !== null) {
                    break;
                }
            }
        }

        return $this->makeProduct([
            'name' => $name,
            'model_name' => $name,
            'reference_number' => Text::reference($text),
            'price_text' => $priceText,
            'url' => $productUrl,
            'image' => $imageUrl,
            'external_id' => $card->attr('data-product-id') ?: null,
            'stock_status' => str_contains($text, '売り切れ') || str_contains(strtolower($text), 'sold out') ? 'sold_out' : 'in_stock',
        ], $pageUrl);
    }

    private function productFromArray(array $data, string $pageUrl): ?Product
    {
        $offers = $data['offers'] ?? [];
        if (is_array($offers) && array_is_list($offers)) {
            $offers = $offers[0] ?? [];
        }
        $url = $data['url'] ?? ($offers['url'] ?? null);
        $image = $data['image'] ?? null;
        if (is_array($image)) {
            $image = $image[0] ?? null;
        }
        $price = $offers['price'] ?? $data['price'] ?? null;
        $priceText = null;
        if ($price !== null && $price !== '') {
            $priceText = is_numeric($price) ? number_format((int) $price) . '円' : (string) $price;
        }

        return $this->makeProduct([
            'name' => $data['name'] ?? $data['title'] ?? null,
            'model_name' => $data['model'] ?? $data['name'] ?? null,
            'reference_number' => $data['mpn'] ?? Text::reference((string) ($data['name'] ?? '')) ?? $data['sku'] ?? Text::reference(json_encode($data, JSON_UNESCAPED_UNICODE) ?: null),
            'price_text' => $priceText,
            'url' => $url,
            'image' => $image,
            'external_id' => $data['sku'] ?? $data['id'] ?? null,
            'stock_status' => $offers['availability'] ?? null,
        ], $pageUrl);
    }

    private function makeProduct(array $data, string $pageUrl): ?Product
    {
        $productUrl = Url::absolute(is_array($data['url'] ?? null) ? null : ($data['url'] ?? null), $pageUrl);
        $normalizedUrl = Url::normalize($productUrl);
        $externalId = Text::clean(isset($data['external_id']) ? (string) $data['external_id'] : null);
        $identity = $externalId ?: $normalizedUrl;
        if ($identity === null || $identity === '') {
            return null;
        }

        $name = Text::clean(is_array($data['name'] ?? null) ? null : ($data['name'] ?? null));
        if ($name === null && $productUrl === null) {
            return null;
        }
        $priceText = Text::clean($data['price_text'] ?? null);

        return new Product(
            $this->source['name'],
            $this->source['category'],
            $externalId,
            hash('sha256', (string) $identity),
            $name,
            Text::clean($data['model_name'] ?? $name),
            Text::clean($data['reference_number'] ?? Text::reference($name)),
            $priceText,
            Text::priceJpy($priceText),
            $productUrl,
            Url::absolute(is_array($data['image'] ?? null) ? null : ($data['image'] ?? null), $pageUrl),
            Text::clean($data['stock_status'] ?? 'in_stock')
        );
    }

    /**
     * @return array<int,mixed>
     */
    private function flattenStructuredData(array $data): array
    {
        if (array_is_list($data)) {
            $items = [];
            foreach ($data as $child) {
                if (is_array($child)) {
                    $items = array_merge($items, $this->flattenStructuredData($child));
                }
            }
            return $items;
        }

        $items = [$data];
        foreach (['@graph', 'itemListElement', 'mainEntity', 'offers'] as $key) {
            if (!isset($data[$key])) {
                continue;
            }
            $value = $data[$key];
            if (is_array($value) && array_is_list($value)) {
                foreach ($value as $child) {
                    if (is_array($child)) {
                        $items = array_merge($items, $this->flattenStructuredData($child));
                    }
                }
            } elseif (is_array($value)) {
                $items = array_merge($items, $this->flattenStructuredData($value));
            }
        }
        if (isset($data['item']) && is_array($data['item'])) {
            $items = array_merge($items, $this->flattenStructuredData($data['item']));
        }
        return $items;
    }

    private function isProductData(array $item): bool
    {
        $type = $item['@type'] ?? $item['type'] ?? null;
        if (is_array($type)) {
            $type = implode(',', $type);
        }
        return is_string($type) && str_contains(strtolower($type), 'product');
    }

    private function priceFromText(string $text): ?string
    {
        if (preg_match('/(?:¥|￥)\s*[0-9][0-9,\s]*|[0-9][0-9,\s]*\s*円/u', $text, $match)) {
            return Text::clean($match[0]);
        }
        return null;
    }

    private function looksLikeProductUrl(string $url): bool
    {
        return (bool) preg_match('#/(products|product|shop|goods|item|SHOP|view/item|collections)/#i', $url)
            || $this->containsBrandHint($url);
    }

    private function containsBrandHint(string $text): bool
    {
        return (bool) preg_match('/vacheron|constantin|ヴァシュロン|バシュロン|ヴァシェロン/iu', $text);
    }
}
