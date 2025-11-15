<?php

namespace LiturgicalCalendar\Components\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;
use LiturgicalCalendar\Components\Cache\ArrayCache;
use Nyholm\Psr7\Factory\Psr17Factory;

/**
 * HTTP Client Factory
 *
 * Creates HTTP client instances with auto-discovery or explicit configuration.
 * Supports PSR-18 clients with fallback to file_get_contents.
 */
class HttpClientFactory
{
    /**
     * Create HTTP client with auto-discovery
     *
     * Attempts to discover available PSR-18 HTTP client and PSR-17 factories.
     * Falls back to file_get_contents implementation if none available.
     *
     * @param ClientInterface|null $httpClient Optional PSR-18 client
     * @param RequestFactoryInterface|null $requestFactory Optional PSR-17 request factory
     * @param StreamFactoryInterface|null $streamFactory Optional PSR-17 stream factory
     * @return HttpClientInterface
     */
    public static function create(
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null
    ): HttpClientInterface {
        // If all PSR dependencies are provided, use PSR client
        if ($httpClient !== null && $requestFactory !== null && $streamFactory !== null) {
            return new PsrHttpClient($httpClient, $requestFactory, $streamFactory);
        }

        // Try to auto-discover PSR implementations using php-http/discovery
        if (
            class_exists('\Http\Discovery\Psr18ClientDiscovery') &&
            class_exists('\Http\Discovery\Psr17FactoryDiscovery')
        ) {
            try {
                /** @var ClientInterface $discoveredClient */
                $discoveredClient = \Http\Discovery\Psr18ClientDiscovery::find();
                /** @var RequestFactoryInterface $discoveredRequestFactory */
                $discoveredRequestFactory = \Http\Discovery\Psr17FactoryDiscovery::findRequestFactory();
                /** @var StreamFactoryInterface $discoveredStreamFactory */
                $discoveredStreamFactory = \Http\Discovery\Psr17FactoryDiscovery::findStreamFactory();

                return new PsrHttpClient(
                    $discoveredClient,
                    $discoveredRequestFactory,
                    $discoveredStreamFactory
                );
            } catch (\Exception $e) {
                // Discovery failed or not found, fall through to fallback
            }
        }

        // Fallback to file_get_contents implementation
        return new FileGetContentsClient();
    }

    /**
     * Create HTTP client with Guzzle and Nyholm PSR-7
     *
     * Recommended configuration using Guzzle for PSR-18 and Nyholm PSR-7 for factories.
     *
     * @return HttpClientInterface
     * @throws \RuntimeException If Guzzle is not installed
     */
    public static function createWithGuzzle(): HttpClientInterface
    {
        if (!class_exists('\GuzzleHttp\Client')) {
            throw new \RuntimeException(
                'Guzzle HTTP client not found. Install with: composer require guzzlehttp/guzzle'
            );
        }

        // Guzzle implements PSR-18 ClientInterface
        $guzzle = new \GuzzleHttp\Client([
            'timeout'         => 30,
            'connect_timeout' => 10,
            'http_errors'     => true,
        ]);

        // Verify Guzzle implements ClientInterface
        if (!$guzzle instanceof ClientInterface) {
            throw new \RuntimeException(
                'Guzzle client does not implement PSR-18 ClientInterface. ' .
                'Please ensure guzzlehttp/guzzle version 7.0 or higher is installed.'
            );
        }

        $psr17Factory = new Psr17Factory();

        return new PsrHttpClient($guzzle, $psr17Factory, $psr17Factory);
    }

    /**
     * Create HTTP client with file_get_contents fallback
     *
     * Uses native PHP file_get_contents() for HTTP operations.
     * Useful for testing or environments without PSR-18 clients.
     *
     * @return HttpClientInterface
     */
    public static function createFallback(): HttpClientInterface
    {
        return new FileGetContentsClient();
    }

    /**
     * Create HTTP client with logging support
     *
     * Wraps the base HTTP client with a logging decorator.
     * Uses Guzzle if available, otherwise falls back to file_get_contents.
     *
     * @param LoggerInterface|null $logger PSR-3 logger instance (uses NullLogger if not provided)
     * @param ClientInterface|null $httpClient Optional PSR-18 client
     * @param RequestFactoryInterface|null $requestFactory Optional PSR-17 request factory
     * @param StreamFactoryInterface|null $streamFactory Optional PSR-17 stream factory
     * @return HttpClientInterface HTTP client with logging
     */
    public static function createWithLogging(
        ?LoggerInterface $logger = null,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null
    ): HttpClientInterface {
        $baseClient = self::create($httpClient, $requestFactory, $streamFactory);
        $logger     = $logger ?? new NullLogger();

        return new LoggingHttpClient($baseClient, $logger);
    }

    /**
     * Create HTTP client with caching support
     *
     * Wraps the base HTTP client with caching and optional logging decorators.
     * Caches successful GET responses to reduce network requests.
     *
     * @param CacheInterface|null $cache PSR-16 cache instance (uses ArrayCache if not provided)
     * @param int $ttl Cache TTL in seconds (default: 3600 = 1 hour)
     * @param LoggerInterface|null $logger Optional PSR-3 logger for cache hit/miss logging
     * @param ClientInterface|null $httpClient Optional PSR-18 client
     * @param RequestFactoryInterface|null $requestFactory Optional PSR-17 request factory
     * @param StreamFactoryInterface|null $streamFactory Optional PSR-17 stream factory
     * @return HttpClientInterface HTTP client with caching and logging
     */
    public static function createWithCaching(
        ?CacheInterface $cache = null,
        int $ttl = 3600,
        ?LoggerInterface $logger = null,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null
    ): HttpClientInterface {
        $baseClient = self::create($httpClient, $requestFactory, $streamFactory);
        $cache      = $cache ?? new ArrayCache();
        $logger     = $logger ?? new NullLogger();

        // Wrap with caching first, then logging (so we log cache hits/misses)
        $cachedClient = new CachingHttpClient($baseClient, $cache, $ttl, $logger);
        return new LoggingHttpClient($cachedClient, $logger);
    }

    /**
     * Create HTTP client with retry support
     *
     * Wraps the base HTTP client with retry logic for failed requests.
     * Implements exponential backoff by default.
     *
     * @param int $maxRetries Maximum number of retry attempts (default: 3)
     * @param int $retryDelay Initial retry delay in milliseconds (default: 1000)
     * @param bool $useExponentialBackoff Whether to use exponential backoff (default: true)
     * @param array<int> $retryStatusCodes HTTP status codes to retry (default: [408, 429, 500, 502, 503, 504])
     * @param LoggerInterface|null $logger Optional PSR-3 logger for retry events
     * @param ClientInterface|null $httpClient Optional PSR-18 client
     * @param RequestFactoryInterface|null $requestFactory Optional PSR-17 request factory
     * @param StreamFactoryInterface|null $streamFactory Optional PSR-17 stream factory
     * @return HttpClientInterface HTTP client with retry and logging
     */
    public static function createWithRetry(
        int $maxRetries = 3,
        int $retryDelay = 1000,
        bool $useExponentialBackoff = true,
        array $retryStatusCodes = [408, 429, 500, 502, 503, 504],
        ?LoggerInterface $logger = null,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null
    ): HttpClientInterface {
        $baseClient = self::create($httpClient, $requestFactory, $streamFactory);
        $logger     = $logger ?? new NullLogger();

        $retryClient = new RetryHttpClient(
            $baseClient,
            $maxRetries,
            $retryDelay,
            $useExponentialBackoff,
            $retryStatusCodes,
            $logger
        );

        return new LoggingHttpClient($retryClient, $logger);
    }

    /**
     * Create HTTP client with circuit breaker support
     *
     * Wraps the base HTTP client with circuit breaker pattern to prevent cascading failures.
     * Implements three states: CLOSED (normal), OPEN (failing fast), HALF_OPEN (testing recovery).
     *
     * @param int $failureThreshold Number of failures before opening circuit (default: 5)
     * @param int $recoveryTimeout Time in seconds before attempting recovery (default: 60)
     * @param int $successThreshold Number of successes in HALF_OPEN before closing circuit (default: 2)
     * @param LoggerInterface|null $logger Optional PSR-3 logger for circuit breaker events
     * @param ClientInterface|null $httpClient Optional PSR-18 client
     * @param RequestFactoryInterface|null $requestFactory Optional PSR-17 request factory
     * @param StreamFactoryInterface|null $streamFactory Optional PSR-17 stream factory
     * @return HttpClientInterface HTTP client with circuit breaker and logging
     */
    public static function createWithCircuitBreaker(
        int $failureThreshold = 5,
        int $recoveryTimeout = 60,
        int $successThreshold = 2,
        ?LoggerInterface $logger = null,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null
    ): HttpClientInterface {
        $baseClient = self::create($httpClient, $requestFactory, $streamFactory);
        $logger     = $logger ?? new NullLogger();

        $circuitBreakerClient = new CircuitBreakerHttpClient(
            $baseClient,
            $failureThreshold,
            $recoveryTimeout,
            $successThreshold,
            $logger
        );

        return new LoggingHttpClient($circuitBreakerClient, $logger);
    }

    /**
     * Create production-ready HTTP client with full middleware stack
     *
     * Combines retry, circuit breaker, caching, and logging for a robust HTTP client.
     * Recommended for production use with external APIs.
     *
     * Middleware stack (innermost to outermost):
     * 1. Base HTTP client (Guzzle or file_get_contents)
     * 2. Circuit Breaker (prevents cascading failures)
     * 3. Retry (retries failed requests)
     * 4. Caching (caches successful responses)
     * 5. Logging (logs all operations)
     *
     * @param CacheInterface|null $cache PSR-16 cache instance
     * @param LoggerInterface|null $logger PSR-3 logger instance
     * @param int $cacheTtl Cache TTL in seconds (default: 3600)
     * @param int $maxRetries Maximum retry attempts (default: 3)
     * @param int $failureThreshold Circuit breaker failure threshold (default: 5)
     * @param ClientInterface|null $httpClient Optional PSR-18 client
     * @param RequestFactoryInterface|null $requestFactory Optional PSR-17 request factory
     * @param StreamFactoryInterface|null $streamFactory Optional PSR-17 stream factory
     * @return HttpClientInterface Fully configured production HTTP client
     */
    public static function createProductionClient(
        ?CacheInterface $cache = null,
        ?LoggerInterface $logger = null,
        int $cacheTtl = 3600,
        int $maxRetries = 3,
        int $failureThreshold = 5,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null
    ): HttpClientInterface {
        $baseClient = self::create($httpClient, $requestFactory, $streamFactory);
        $cache      = $cache ?? new ArrayCache();
        $logger     = $logger ?? new NullLogger();

        // Layer 1: Circuit Breaker (innermost - protects base client)
        $circuitBreakerClient = new CircuitBreakerHttpClient(
            $baseClient,
            $failureThreshold,
            60, // recovery timeout
            2,  // success threshold
            $logger
        );

        // Layer 2: Retry (retries if circuit breaker allows)
        $retryClient = new RetryHttpClient(
            $circuitBreakerClient,
            $maxRetries,
            1000, // initial delay
            true, // exponential backoff
            [408, 429, 500, 502, 503, 504],
            $logger
        );

        // Layer 3: Caching (caches successful responses after retries)
        $cachedClient = new CachingHttpClient($retryClient, $cache, $cacheTtl, $logger);

        // Layer 4: Logging (outermost - logs everything)
        return new LoggingHttpClient($cachedClient, $logger);
    }
}
