<?php

namespace LiturgicalCalendar\Components;

use LiturgicalCalendar\Components\Http\HttpClientInterface;
use LiturgicalCalendar\Components\Http\HttpClientFactory;
use LiturgicalCalendar\Components\Metadata\MetadataProvider;
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
 * **Default Cache TTL**: The default cache TTL is exposed as a public constant
 * `ApiClient::DEFAULT_CACHE_TTL` (86400 seconds / 24 hours) for use by other
 * components that need a consistent default caching duration.
 *
 * Usage:
 * ```php
 * // Initialize once at application bootstrap
 * $apiClient = ApiClient::getInstance([
 *     'apiUrl' => 'https://litcal.johnromanodorazio.com/api/dev',
 *     'cache' => $cache,
 *     'logger' => $logger,
 *     'cacheTtl' => ApiClient::DEFAULT_CACHE_TTL  // Or custom value
 * ]);
 *
 * // Use factory methods for API requests (recommended)
 * $calendar = $apiClient->calendar()->nation('IT')->year(2024)->get();
 * $metadata = $apiClient->metadata()->getMetadata();
 * ```
 */
class ApiClient
{
    private const DEFAULT_API_URL  = 'https://litcal.johnromanodorazio.com/api/dev';
    public const DEFAULT_CACHE_TTL = 86400; // 24 hours

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

        // Warn about ambiguous configuration if both client and decorators are provided
        if ($httpClient !== null && ( $cache !== null || $logger !== null )) {
            trigger_error(
                'ApiClient::__construct() called with both httpClient and cache/logger parameters. ' .
                'Since a custom httpClient is provided, cache/logger configuration will be ignored. ' .
                'Pass either httpClient OR cache/logger, not both.',
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
     * Create a new calendar data request
     *
     * Factory method for creating CalendarRequest instances that automatically
     * use the ApiClient's shared configuration.
     *
     * @return CalendarRequest Fresh CalendarRequest instance
     */
    public function calendar(): CalendarRequest
    {
        return new CalendarRequest();
    }

    /**
     * Access the metadata provider
     *
     * Returns the MetadataProvider singleton which automatically uses the
     * ApiClient's shared configuration.
     *
     * @return MetadataProvider MetadataProvider singleton instance
     */
    public function metadata(): MetadataProvider
    {
        return MetadataProvider::getInstance();
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
