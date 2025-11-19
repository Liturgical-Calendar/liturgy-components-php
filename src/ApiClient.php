<?php

namespace LiturgicalCalendar\Components;

use LiturgicalCalendar\Components\Http\HttpClientInterface;
use LiturgicalCalendar\Components\Http\HttpClientFactory;
use Psr\SimpleCache\CacheInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Centralized API client configuration for all API interactions
 *
 * Provides a single point of configuration for HTTP client, cache, logger,
 * and API URL. All components (MetadataProvider, CalendarRequest, etc.) can
 * pull dependencies from ApiClient for consistent configuration.
 *
 * Usage:
 * ```php
 * // Initialize once at application bootstrap
 * ApiClient::getInstance([
 *     'apiUrl' => 'https://litcal.johnromanodorazio.com/api/dev',
 *     'httpClient' => $httpClient,
 *     'cache' => $cache,
 *     'logger' => $logger,
 *     'cacheTtl' => 86400
 * ]);
 *
 * // All components use this configuration
 * $metadata = MetadataProvider::getInstance();
 * $request = ApiClient::createCalendarRequest();
 * ```
 */
class ApiClient
{
    private const DEFAULT_API_URL   = 'https://litcal.johnromanodorazio.com/api/dev';
    private const DEFAULT_CACHE_TTL = 86400; // 24 hours

    /** @var self|null Singleton instance */
    private static ?self $instance = null;

    /** @var string API base URL (immutable) */
    private string $apiUrl;

    /** @var HttpClientInterface HTTP client (immutable) */
    private HttpClientInterface $httpClient;

    /** @var CacheInterface|null Cache implementation (immutable) */
    private ?CacheInterface $cache;

    /** @var LoggerInterface Logger implementation (immutable) */
    private LoggerInterface $logger;

    /** @var int Default cache TTL in seconds (immutable) */
    private int $cacheTtl;

    /**
     * Private constructor - use getInstance() instead
     */
    private function __construct(
        string $apiUrl,
        ?HttpClientInterface $httpClient,
        ?CacheInterface $cache,
        ?LoggerInterface $logger,
        int $cacheTtl
    ) {
        $this->apiUrl   = rtrim($apiUrl, '/');
        $this->cache    = $cache;
        $this->logger   = $logger ?? new NullLogger();
        $this->cacheTtl = $cacheTtl;

        // Warn about potential double-wrapping if both client and decorators provided
        if ($httpClient !== null && ( $cache !== null || $logger !== null )) {
            trigger_error(
                'ApiClient::__construct() called with both httpClient and cache/logger parameters. ' .
                'If httpClient is already decorated (e.g., from HttpClientFactory::createProductionClient()), ' .
                'this will cause double-wrapping. Only pass httpClient OR cache/logger, not both.',
                E_USER_WARNING
            );
        }

        // If httpClient provided, use it; otherwise create one with optional cache/logger
        if ($httpClient !== null) {
            $this->httpClient = $httpClient;
        } else {
            $this->httpClient = HttpClientFactory::createProductionClient(
                cache: $this->cache,
                logger: $this->logger,
                cacheTtl: $this->cacheTtl
            );
        }
    }

    /**
     * Get singleton instance
     *
     * On first call, initializes with provided configuration.
     * Subsequent calls return the same instance and ignore parameters.
     *
     * @param array{
     *     apiUrl?: string,
     *     httpClient?: HttpClientInterface|null,
     *     cache?: CacheInterface|null,
     *     logger?: LoggerInterface|null,
     *     cacheTtl?: int
     * } $config Configuration array
     * @return self
     */
    public static function getInstance(array $config = []): self
    {
        if (self::$instance === null) {
            self::$instance = new self(
                apiUrl: $config['apiUrl'] ?? self::DEFAULT_API_URL,
                httpClient: $config['httpClient'] ?? null,
                cache: $config['cache'] ?? null,
                logger: $config['logger'] ?? null,
                cacheTtl: $config['cacheTtl'] ?? self::DEFAULT_CACHE_TTL
            );
        }

        return self::$instance;
    }

    /**
     * Check if ApiClient has been initialized
     */
    public static function isInitialized(): bool
    {
        return self::$instance !== null;
    }

    /**
     * Get configured HTTP client
     */
    public static function getHttpClient(): ?HttpClientInterface
    {
        return self::$instance?->httpClient;
    }

    /**
     * Get configured API URL
     */
    public static function getApiUrl(): ?string
    {
        return self::$instance?->apiUrl;
    }

    /**
     * Get configured cache
     */
    public static function getCache(): ?CacheInterface
    {
        return self::$instance?->cache;
    }

    /**
     * Get configured logger
     */
    public static function getLogger(): ?LoggerInterface
    {
        return self::$instance?->logger;
    }

    /**
     * Get configured cache TTL
     */
    public static function getCacheTtl(): ?int
    {
        return self::$instance?->cacheTtl;
    }

    /**
     * Create a CalendarRequest with shared configuration
     *
     * @return CalendarRequest
     */
    public static function createCalendarRequest(): CalendarRequest
    {
        if (self::$instance === null) {
            throw new \RuntimeException(
                'ApiClient must be initialized before creating CalendarRequest. ' .
                'Call ApiClient::getInstance() first.'
            );
        }

        return new CalendarRequest(
            httpClient: self::$instance->httpClient,
            logger: self::$instance->logger,
            cache: self::$instance->cache,
            apiUrl: self::$instance->apiUrl
        );
    }

    /**
     * Reset singleton instance (for testing only)
     *
     * @internal
     */
    public static function resetForTesting(): void
    {
        self::$instance = null;
    }
}
