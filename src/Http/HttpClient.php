<?php

declare(strict_types=1);

namespace WatchScraper\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use WatchScraper\Support\Env;

final class HttpClient
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'allow_redirects' => true,
            'connect_timeout' => Env::int('HTTP_CONNECT_TIMEOUT', 10),
            'timeout' => Env::int('HTTP_TIMEOUT', 30),
            'verify' => true,
            'headers' => [
                'User-Agent' => Env::string('HTTP_USER_AGENT', 'WatchInventoryMonitor/1.0'),
                'Accept-Language' => 'ja-JP,ja;q=0.9,en;q=0.4',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ],
            'http_errors' => false,
        ]);
    }

    /**
     * @throws GuzzleException
     */
    public function get(string $url): HttpResponse
    {
        $response = $this->client->get($url);
        $status = $response->getStatusCode();
        if ($status === 429 && $response->hasHeader('Retry-After')) {
            $retry = min(30, max(1, (int) $response->getHeaderLine('Retry-After')));
            sleep($retry);
            $response = $this->client->get($url);
            $status = $response->getStatusCode();
        }

        return new HttpResponse(
            $status,
            $response->getHeaders(),
            (string) $response->getBody(),
            $url
        );
    }
}
