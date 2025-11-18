<?php

namespace LiturgicalCalendar\Components\Metadata;

use LiturgicalCalendar\Components\Models\Index\CalendarIndex;
use LiturgicalCalendar\Components\Http\HttpClientInterface;
use LiturgicalCalendar\Components\Http\HttpClientFactory;
use LiturgicalCalendar\Components\Http\CachingHttpClient;
use LiturgicalCalendar\Components\Http\LoggingHttpClient;
use Psr\SimpleCache\CacheInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Centralized metadata provider for Liturgical Calendar API
 *
 * This service provides a single point of access for calendar metadata from the
 * /calendars endpoint. It ensures that metadata is fetched once and shared across
 * all components that need it, reducing redundant HTTP requests.
 *
 * Features:
 * - Process-wide singleton metadata cache per API URL
 * - PSR-16 cache support for persistence across requests
 * - PSR-3 logging support
 * - Automatic HTTP client configuration with caching and logging
 *
 * Usage:
 * ```php
 * // Simple usage with defaults
 * $provider = MetadataProvider::getInstance();
 * $metadata = $provider->getMetadata($apiUrl);
 *
 * // With custom cache and logger
 * $provider = MetadataProvider::getInstance(
 *     cache: $cache,
 *     logger: $logger
 * );
 * $metadata = $provider->getMetadata($apiUrl);
 * ```
 */
class MetadataProvider
{
    /** @var array<string, CalendarIndex> Process-wide metadata cache keyed by API URL */
    private static array $metadataCache = [];

    /** @var array<string, self> Singleton instances keyed by configuration hash */
    private static array $instances = [];

    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;

    /**
     * Private constructor - use getInstance() instead
     *
     * @param HttpClientInterface $httpClient HTTP client for API requests
     * @param LoggerInterface $logger PSR-3 logger
     */
    private function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger
    ) {
        $this->httpClient = $httpClient;
        $this->logger     = $logger;
    }

    /**
     * Get or create a MetadataProvider instance
     *
     * Returns a singleton instance configured with the provided dependencies.
     * If cache or logger are provided, the HTTP client will be wrapped with
     * appropriate decorators.
     *
     * **Important: HTTP Client Decorator Behavior**
     *
     * If `$cache` or `$logger` are provided, the constructor will automatically
     * wrap the HTTP client with decorators:
     * - If `$cache` is provided: wraps with CachingHttpClient
     * - If `$logger` is provided: wraps with LoggingHttpClient
     *
     * **Warning:** If you provide a pre-decorated client (e.g., from
     * HttpClientFactory::createProductionClient), DO NOT also pass `$cache` or
     * `$logger` parameters, as this will cause double-wrapping.
     *
     * @param HttpClientInterface|null $httpClient Optional HTTP client. If null, uses auto-discovery.
     * @param CacheInterface|null $cache Optional PSR-16 cache (only use if $httpClient is NOT already decorated).
     * @param LoggerInterface|null $logger Optional PSR-3 logger (only use if $httpClient is NOT already decorated).
     * @param int $cacheTtl Cache TTL in seconds (default: 86400 = 24 hours).
     * @return self Singleton instance
     */
    public static function getInstance(
        ?HttpClientInterface $httpClient = null,
        ?CacheInterface $cache = null,
        ?LoggerInterface $logger = null,
        int $cacheTtl = 86400
    ): self {
        // Generate stable instance key based on configuration
        // Use 'none' for null dependencies to ensure true singleton for default config
        // Include TTL in key so different TTLs create different instances
        $httpClientKey = $httpClient !== null ? spl_object_hash($httpClient) : 'none';
        $cacheKey      = $cache !== null ? spl_object_hash($cache) : 'none';
        $loggerKey     = $logger !== null ? spl_object_hash($logger) : 'none';
        $instanceKey   = "{$httpClientKey}_{$cacheKey}_{$loggerKey}_{$cacheTtl}";

        if (isset(self::$instances[$instanceKey])) {
            return self::$instances[$instanceKey];
        }

        // Initialize HTTP client with auto-discovery if not provided
        $baseClient = $httpClient ?? HttpClientFactory::create();
        $logger     = $logger ?? new NullLogger();

        // Wrap with caching if cache provided
        if ($cache !== null) {
            $baseClient = new CachingHttpClient(
                $baseClient,
                $cache,
                $cacheTtl,
                $logger
            );
        }

        // Wrap with logging if logger provided (and not NullLogger)
        if (!( $logger instanceof NullLogger )) {
            $baseClient = new LoggingHttpClient($baseClient, $logger);
        }

        $instance                      = new self($baseClient, $logger);
        self::$instances[$instanceKey] = $instance;

        return $instance;
    }

    /**
     * Get calendar metadata from the API
     *
     * Fetches metadata from the /calendars endpoint and caches it for the
     * current process. Subsequent calls with the same API URL will return
     * the cached metadata without making additional HTTP requests.
     *
     * @param string $apiUrl The base API URL (e.g., 'https://litcal.johnromanodorazio.com/api/dev')
     * @return CalendarIndex The calendar metadata
     * @throws \Exception If there is an error fetching or parsing metadata
     */
    public function getMetadata(string $apiUrl): CalendarIndex
    {
        $apiUrl = rtrim($apiUrl, '/');

        // Check process-wide cache
        if (isset(self::$metadataCache[$apiUrl])) {
            $this->logger->debug('Metadata cache hit', ['url' => $apiUrl]);
            return self::$metadataCache[$apiUrl];
        }

        $this->logger->info('Fetching metadata from API', ['url' => $apiUrl]);

        // Fetch from API
        $calendarsUrl = $apiUrl . '/calendars';
        $response     = $this->httpClient->get($calendarsUrl);

        if ($response->getStatusCode() !== 200) {
            throw new \Exception(
                "Failed to fetch metadata from {$calendarsUrl}. " .
                "HTTP Status: {$response->getStatusCode()}"
            );
        }

        $metadataRaw  = $response->getBody()->getContents();
        $metadataJSON = json_decode($metadataRaw, true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new \Exception(
                "Failed to decode metadata from {$calendarsUrl}: " . json_last_error_msg()
            );
        }

        if (!is_array($metadataJSON)) {
            throw new \Exception("Invalid metadata from {$calendarsUrl}: expected array");
        }

        if (!array_key_exists('litcal_metadata', $metadataJSON)) {
            throw new \Exception("Missing 'litcal_metadata' in metadata from {$calendarsUrl}");
        }

        $litcalMetadata = $metadataJSON['litcal_metadata'];
        if (!is_array($litcalMetadata)) {
            throw new \Exception("'litcal_metadata' must be an array in metadata from {$calendarsUrl}");
        }

        // Validate required fields
        $requiredFields = ['diocesan_calendars', 'national_calendars', 'locales'];
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $litcalMetadata)) {
                throw new \Exception("Missing '{$field}' in metadata from {$calendarsUrl}");
            }
        }

        /** @var array<string,mixed> $litcalMetadata */
        $calendarIndex = CalendarIndex::fromArray($litcalMetadata);

        // Cache for process lifetime
        self::$metadataCache[$apiUrl] = $calendarIndex;

        $this->logger->info('Metadata cached successfully', [
            'url'                => $apiUrl,
            'national_calendars' => count($calendarIndex->nationalCalendars),
            'diocesan_calendars' => count($calendarIndex->diocesanCalendars),
            'locales'            => count($calendarIndex->locales)
        ]);

        return $calendarIndex;
    }

    /**
     * Clear the process-wide metadata cache
     *
     * Useful for testing or when you need to force a refresh of metadata.
     *
     * @return void
     */
    public static function clearCache(): void
    {
        self::$metadataCache = [];
    }

    /**
     * Check if metadata is cached for the given API URL
     *
     * @param string $apiUrl The base API URL
     * @return bool True if metadata is cached, false otherwise
     */
    public static function isCached(string $apiUrl): bool
    {
        $apiUrl = rtrim($apiUrl, '/');
        return isset(self::$metadataCache[$apiUrl]);
    }
}
