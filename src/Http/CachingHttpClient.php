<?php

namespace LiturgicalCalendar\Components\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Nyholm\Psr7\Response;

/**
 * HTTP Client decorator that caches GET responses
 *
 * Uses PSR-16 Simple Cache interface to cache successful HTTP GET responses.
 * POST requests are not cached.
 *
 * Cache keys are generated based on URL and headers to ensure uniqueness.
 */
class CachingHttpClient implements HttpClientInterface
{
    public function __construct(
        private HttpClientInterface $client,
        private CacheInterface $cache,
        private int $defaultTtl = 3600,
        private LoggerInterface $logger = new NullLogger()
    ) {
    }

    /**
     * @param string $url
     * @param array<string, string> $headers
     * @return ResponseInterface
     * @throws HttpException
     */
    public function get(string $url, array $headers = []): ResponseInterface
    {
        $cacheKey = $this->getCacheKey($url, $headers);

        // Try to get from cache
        $cachedData = $this->cache->get($cacheKey);

        if ($cachedData !== null) {
            $this->logger->debug('Cache hit', ['url' => $url, 'key' => $cacheKey]);

            /** @var array{status: int, headers: array<string, string|string[]>, body: string} $cachedData */
            return new Response(
                $cachedData['status'],
                $cachedData['headers'],
                $cachedData['body']
            );
        }

        $this->logger->debug('Cache miss', ['url' => $url, 'key' => $cacheKey]);

        // Fetch from HTTP client
        $response = $this->client->get($url, $headers);

        // Cache successful responses (2xx)
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $this->cacheResponse($cacheKey, $response);
        }

        return $response;
    }

    /**
     * @param string $url
     * @param array<string, mixed>|string $body
     * @param array<string, string> $headers
     * @return ResponseInterface
     * @throws HttpException
     */
    public function post(string $url, array|string $body, array $headers = []): ResponseInterface
    {
        // POST requests are not cached
        return $this->client->post($url, $body, $headers);
    }

    /**
     * Generate unique cache key based on URL and headers
     *
     * @param string $url
     * @param array<string, string> $headers
     * @return string
     */
    private function getCacheKey(string $url, array $headers): string
    {
        // Create unique cache key based on URL and headers
        $keyData = [
            'url'     => $url,
            'headers' => $headers,
        ];

        return 'http_' . hash('sha256', serialize($keyData));
    }

    /**
     * Cache the HTTP response
     *
     * @param string $cacheKey
     * @param ResponseInterface $response
     * @return void
     */
    private function cacheResponse(string $cacheKey, ResponseInterface $response): void
    {
        $body = $response->getBody()->getContents();

        // Reset stream position after reading
        $response->getBody()->rewind();

        $cacheData = [
            'status'  => $response->getStatusCode(),
            'headers' => $response->getHeaders(),
            'body'    => $body,
        ];

        $this->cache->set($cacheKey, $cacheData, $this->defaultTtl);

        $this->logger->debug('Response cached', [
            'key'  => $cacheKey,
            'ttl'  => $this->defaultTtl,
            'size' => strlen($body),
        ]);
    }
}
