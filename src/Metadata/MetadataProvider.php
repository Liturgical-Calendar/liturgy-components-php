<?php

namespace LiturgicalCalendar\Components\Metadata;

use LiturgicalCalendar\Components\ApiClient;
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
 * **IMPORTANT: Caching Behavior**
 *
 * This provider uses a two-tier caching strategy:
 *
 * 1. **Process-wide cache** (self::$metadataCache): Once metadata is fetched for an
 *    API URL, it is cached for the lifetime of the PHP process. This cache takes
 *    precedence and is never automatically invalidated based on TTL.
 *
 * 2. **PSR-16 cache** (via CachingHttpClient): Used for HTTP response caching across
 *    requests/processes. The TTL only applies to the initial HTTP fetch - once in
 *    the process-wide cache, the TTL is no longer consulted.
 *
 * **For typical web requests**: This is optimal - metadata is fetched once per request
 * and reused across components.
 *
 * **For long-running processes** (workers, daemons, CLI scripts): You must explicitly
 * call `MetadataProvider::clearCache()` if you need to refresh metadata during the
 * process lifetime. The PSR-16 TTL will not trigger automatic refreshes.
 *
 * Usage:
 * ```php
 * // Simple usage with defaults
 * $provider = MetadataProvider::getInstance(
 *     apiUrl: 'https://litcal.johnromanodorazio.com/api/dev'
 * );
 * $metadata = $provider->getMetadata();
 *
 * // With custom cache and logger
 * $provider = MetadataProvider::getInstance(
 *     apiUrl: 'https://litcal.johnromanodorazio.com/api/dev',
 *     cache: $cache,
 *     logger: $logger
 * );
 * $metadata = $provider->getMetadata();
 *
 * // In long-running processes, refresh metadata when needed
 * MetadataProvider::clearCache();
 * $freshMetadata = $provider->getMetadata();
 * ```
 */
class MetadataProvider
{
    private const DEFAULT_API_URL = 'https://litcal.johnromanodorazio.com/api/dev';

    /** @var array<string, CalendarIndex> Process-wide metadata cache keyed by API URL */
    private static array $metadataCache = [];

    /** @var self|null Singleton instance */
    private static ?self $instance = null;

    /** @var string|null Global API URL (immutable after first initialization) */
    private static ?string $globalApiUrl = null;

    /** @var HttpClientInterface|null Global HTTP client (immutable after first initialization) */
    private static ?HttpClientInterface $globalHttpClient = null;

    /** @var LoggerInterface|null Global logger (immutable after first initialization) */
    private static ?LoggerInterface $globalLogger = null;

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
     * Get or create the MetadataProvider singleton instance
     *
     * Uses shared configuration from ApiClient singleton. If ApiClient is not yet
     * initialized, it will be auto-initialized with default values.
     *
     * Configuration is set once on first call and becomes immutable thereafter.
     *
     * **Usage:**
     * ```php
     * // Initialize ApiClient once at application bootstrap (recommended)
     * ApiClient::getInstance([
     *     'apiUrl' => 'https://litcal.johnromanodorazio.com/api/dev',
     *     'httpClient' => $httpClient,  // Already decorated with cache/logger
     *     'cache' => $cache,
     *     'logger' => $logger
     * ]);
     *
     * // Use anywhere without parameters - pulls config from ApiClient
     * $provider = MetadataProvider::getInstance();
     * $metadata = $provider->getMetadata();
     * ```
     *
     * **Automatic Initialization:**
     * If ApiClient is not initialized, MetadataProvider will auto-initialize it
     * with default values (default API URL, basic HTTP client, null logger).
     *
     * @return self Singleton instance
     */
    public static function getInstance(): self
    {
        // Return existing instance if already initialized
        if (self::$instance !== null) {
            return self::$instance;
        }

        // Ensure ApiClient is initialized (auto-initialize with defaults if needed)
        if (!ApiClient::isInitialized()) {
            ApiClient::getInstance(); // Uses default values
        }

        // First initialization - pull all configuration from ApiClient
        self::$globalApiUrl     = ApiClient::getApiUrl() ?? self::DEFAULT_API_URL;
        self::$globalHttpClient = ApiClient::getHttpClient();
        self::$globalLogger     = ApiClient::getLogger();

        // Use HttpClient from ApiClient (already decorated) or create default
        $baseClient  = self::$globalHttpClient ?? HttpClientFactory::create();
        $finalLogger = self::$globalLogger ?? new NullLogger();

        self::$instance = new self($baseClient, $finalLogger);

        return self::$instance;
    }

    /**
     * Get calendar metadata from the API
     *
     * Fetches metadata from the /calendars endpoint and caches it for the
     * current process. Subsequent calls will return the cached metadata
     * without making additional HTTP requests.
     *
     * **IMPORTANT**: The process-wide cache takes precedence over PSR-16 cache TTL.
     * Once metadata is cached in the current process, it will NOT be refreshed even
     * if the PSR-16 cache expires. For long-running processes, call clearCache()
     * explicitly when you need fresh metadata.
     *
     * Uses the globally configured API URL set during getInstance() initialization.
     *
     * @return CalendarIndex The calendar metadata
     * @throws \Exception If there is an error fetching or parsing metadata
     */
    public function getMetadata(): CalendarIndex
    {
        if (self::$globalApiUrl === null) {
            throw new \Exception(
                'MetadataProvider API URL not configured. ' .
                'Initialize via ApiClient::getInstance([\'apiUrl\' => ...]) (recommended) ' .
                'or call MetadataProvider::getInstance(apiUrl: ...) directly.'
            );
        }

        $apiUrl = rtrim(self::$globalApiUrl, '/');

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
     * Clears all cached metadata, forcing the next getMetadata() call to fetch
     * fresh data from the API (or PSR-16 cache if still valid).
     *
     * **IMPORTANT**: This method only clears the metadata cache (self::$metadataCache).
     * It does NOT clear the singleton instance (self::$instance). The singleton
     * instance is preserved to maintain its HTTP client configuration.
     *
     * **When to use this:**
     * - In long-running processes (workers, daemons, CLI scripts) when you need
     *   to refresh metadata during the process lifetime
     * - When you know the API metadata has changed and need to force a refresh
     *
     * **Not needed for:**
     * - Typical web requests (each request is a new process)
     * - Relying on PSR-16 TTL for automatic refresh (process cache takes precedence)
     * - Testing (use resetForTesting() instead)
     *
     * @return void
     */
    public static function clearCache(): void
    {
        self::$metadataCache = [];
    }

    /**
     * Reset the singleton instance and cache for testing purposes
     *
     * **WARNING**: This method is intended for testing only. It completely
     * resets the MetadataProvider singleton, allowing tests to create fresh
     * instances with different configurations.
     *
     * **DO NOT use in production code.** In production, the singleton should
     * be initialized once and reused throughout the application lifecycle.
     *
     * @internal For testing purposes only
     * @return void
     */
    public static function resetForTesting(): void
    {
        self::$instance         = null;
        self::$metadataCache    = [];
        self::$globalApiUrl     = null;
        self::$globalHttpClient = null;
        self::$globalLogger     = null;
    }

    /**
     * Check if metadata is cached
     *
     * Uses the globally configured API URL.
     *
     * @return bool True if metadata is cached, false otherwise
     */
    public static function isCached(): bool
    {
        if (self::$globalApiUrl === null) {
            return false;
        }
        $apiUrl = rtrim(self::$globalApiUrl, '/');
        return isset(self::$metadataCache[$apiUrl]);
    }

    /**
     * Validates if a diocese is valid for a given nation
     *
     * This static method provides a centralized way to check if a diocese ID
     * belongs to a specific nation's calendar. It uses the globally configured
     * MetadataProvider instance to fetch and validate metadata.
     *
     * **Usage:**
     * ```php
     * // First, initialize the MetadataProvider (typically in bootstrap)
     * MetadataProvider::getInstance(
     *     apiUrl: 'https://litcal.johnromanodorazio.com/api/dev',
     *     httpClient: $httpClient,
     *     cache: $cache,
     *     logger: $logger
     * );
     *
     * // Then use validation anywhere without parameters
     * $isValid = MetadataProvider::isValidDioceseForNation('boston_us', 'US');
     * ```
     *
     * @param string $dioceseId The diocese calendar ID to check
     * @param string $nation The nation calendar ID (ISO 3166-1 alpha-2 code)
     * @return bool True if the diocese is valid for the nation, false otherwise
     * @throws \Exception If MetadataProvider has not been initialized
     */
    public static function isValidDioceseForNation(string $dioceseId, string $nation): bool
    {
        if (self::$instance === null) {
            throw new \Exception(
                'MetadataProvider must be initialized before calling validation methods. ' .
                'Call MetadataProvider::getInstance() first.'
            );
        }

        $metadata = self::$instance->getMetadata();

        // Find the national calendar for the given nation
        $nationalCalendarMetadata = array_values(array_filter(
            $metadata->nationalCalendars,
            fn(\LiturgicalCalendar\Components\Models\Index\NationalCalendar $item) => $item->calendarId === $nation
        ));

        if (count($nationalCalendarMetadata) === 0) {
            return false;
        }

        $nationalCalendar = $nationalCalendarMetadata[0];

        // Check if nation has dioceses property
        if ($nationalCalendar->dioceses === null) {
            return false;
        }

        // Check if diocese_id is in the dioceses array
        return in_array($dioceseId, $nationalCalendar->dioceses);
    }

    /**
     * Get the configured global API URL
     *
     * @return string|null The API URL or null if not yet configured
     */
    public static function getApiUrl(): ?string
    {
        return self::$globalApiUrl;
    }

    /**
     * Get the metadata endpoint URL (API URL + /calendars)
     *
     * @return string|null The metadata URL or null if not yet configured
     */
    public static function getMetadataUrl(): ?string
    {
        if (self::$globalApiUrl === null) {
            return null;
        }
        return rtrim(self::$globalApiUrl, '/') . '/calendars';
    }
}
