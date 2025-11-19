<?php

namespace LiturgicalCalendar\Components;

use LiturgicalCalendar\Components\Http\HttpClientInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Builder for creating CalendarRequest instances with common configurations
 *
 * Provides convenient static methods for fetching calendar data without manually
 * constructing CalendarRequest objects. All methods accept optional HTTP client,
 * logger, and cache dependencies for full control over the request configuration.
 *
 * **Usage Examples:**
 *
 * ```php
 * // Minimal usage - all dependencies auto-discovered from ApiClient
 * $calendar = CalendarResponseBuilder::generalCalendar(2024);
 *
 * // With custom locale
 * $calendar = CalendarResponseBuilder::nationalCalendar('IT', 2024, 'it');
 *
 * // With HTTP client for testing/mocking
 * $mockClient = new MockHttpClient();
 * $calendar = CalendarResponseBuilder::generalCalendar(2024, 'en', $mockClient);
 *
 * // With logger for debugging
 * $logger = new Logger('calendar');
 * $calendar = CalendarResponseBuilder::nationalCalendar('US', 2024, 'en', null, $logger);
 *
 * // With cache for performance
 * $cache = new FilesystemCache();
 * $calendar = CalendarResponseBuilder::generalCalendar(2024, 'en', null, null, $cache);
 *
 * // Full configuration with all dependencies
 * $httpClient = HttpClientFactory::create();
 * $logger = new Logger('calendar');
 * $cache = new ArrayCache();
 * $calendar = CalendarResponseBuilder::diocesanCalendar(
 *     'DIOCESE001',
 *     2024,
 *     'en',
 *     $httpClient,
 *     $logger,
 *     $cache
 * );
 * ```
 */
class CalendarResponseBuilder
{
    /**
     * Quick request for General Roman Calendar
     *
     * Fetches the General Roman Calendar for a specific year with optional locale.
     * Dependencies are automatically resolved from ApiClient if not provided explicitly.
     *
     * @param int $year The liturgical year to fetch (1970-9999)
     * @param string $locale The locale for localized content (default: 'en')
     * @param HttpClientInterface|null $httpClient Optional HTTP client for requests
     * @param LoggerInterface|null $logger Optional PSR-3 logger for request/response logging
     * @param CacheInterface|null $cache Optional PSR-16 cache for HTTP response caching
     * @return \stdClass Calendar response object
     * @throws \Exception If request fails or response is invalid
     */
    public static function generalCalendar(
        int $year,
        string $locale = 'en',
        ?HttpClientInterface $httpClient = null,
        ?LoggerInterface $logger = null,
        ?CacheInterface $cache = null
    ): \stdClass {
        return ( new CalendarRequest($httpClient, $logger, $cache) )
            ->year($year)
            ->locale($locale)
            ->get();
    }

    /**
     * Quick request for National Calendar
     *
     * Fetches a national calendar (e.g., US, IT, FR) for a specific year with optional locale.
     * Dependencies are automatically resolved from ApiClient if not provided explicitly.
     *
     * @param string $nation The national calendar ID (ISO 3166-1 alpha-2 code)
     * @param int $year The liturgical year to fetch (1970-9999)
     * @param string $locale The locale for localized content (default: 'en')
     * @param HttpClientInterface|null $httpClient Optional HTTP client for requests
     * @param LoggerInterface|null $logger Optional PSR-3 logger for request/response logging
     * @param CacheInterface|null $cache Optional PSR-16 cache for HTTP response caching
     * @return \stdClass Calendar response object
     * @throws \Exception If request fails or response is invalid
     */
    public static function nationalCalendar(
        string $nation,
        int $year,
        string $locale = 'en',
        ?HttpClientInterface $httpClient = null,
        ?LoggerInterface $logger = null,
        ?CacheInterface $cache = null
    ): \stdClass {
        return ( new CalendarRequest($httpClient, $logger, $cache) )
            ->nation($nation)
            ->year($year)
            ->locale($locale)
            ->get();
    }

    /**
     * Quick request for Diocesan Calendar
     *
     * Fetches a diocesan calendar for a specific year with optional locale.
     * Dependencies are automatically resolved from ApiClient if not provided explicitly.
     *
     * @param string $diocese The diocesan calendar ID (9-character format)
     * @param int $year The liturgical year to fetch (1970-9999)
     * @param string $locale The locale for localized content (default: 'en')
     * @param HttpClientInterface|null $httpClient Optional HTTP client for requests
     * @param LoggerInterface|null $logger Optional PSR-3 logger for request/response logging
     * @param CacheInterface|null $cache Optional PSR-16 cache for HTTP response caching
     * @return \stdClass Calendar response object
     * @throws \Exception If request fails or response is invalid
     */
    public static function diocesanCalendar(
        string $diocese,
        int $year,
        string $locale = 'en',
        ?HttpClientInterface $httpClient = null,
        ?LoggerInterface $logger = null,
        ?CacheInterface $cache = null
    ): \stdClass {
        return ( new CalendarRequest($httpClient, $logger, $cache) )
            ->diocese($diocese)
            ->year($year)
            ->locale($locale)
            ->get();
    }
}
