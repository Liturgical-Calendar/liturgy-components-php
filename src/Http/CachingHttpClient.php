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
 * Cache keys are generated based on URL and semantically relevant headers only.
 * Headers like User-Agent, X-Request-ID, etc. are excluded to maximize cache hits.
 */
class CachingHttpClient implements HttpClientInterface
{
    /**
     * Headers that affect the API response and should be part of the cache key.
     *
     * For Liturgical Calendar API:
     * - Accept-Language: Determines the language/locale of the response
     * - Accept: Determines the response format (json, xml, yaml, icalendar)
     *
     * Other headers (User-Agent, X-Request-ID, etc.) don't affect the response
     * content and are excluded to improve cache hit rates.
     *
     * @var string[]
     */
    private const CACHE_RELEVANT_HEADERS = [
        'accept-language',
        'accept',
    ];

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
     * Generate unique cache key based on URL and relevant headers only
     *
     * Only includes headers that affect the response content (Accept-Language, Accept).
     * Other headers like User-Agent, X-Request-ID, etc. are excluded to improve cache hit rates.
     *
     * @param string $url The request URL (includes path and query parameters)
     * @param array<string, string> $headers Request headers
     * @return string SHA-256 hash of URL and relevant headers
     */
    private function getCacheKey(string $url, array $headers): string
    {
        // Filter headers to only include cache-relevant ones
        $relevantHeaders = [];
        foreach ($headers as $name => $value) {
            if (in_array(strtolower($name), self::CACHE_RELEVANT_HEADERS, true)) {
                $relevantHeaders[strtolower($name)] = $value;
            }
        }

        // Sort headers by key for consistent cache keys
        ksort($relevantHeaders);

        $keyData = [
            'url'     => $url,
            'headers' => $relevantHeaders,
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
