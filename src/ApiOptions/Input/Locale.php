<?php

namespace LiturgicalCalendar\Components\ApiOptions\Input;

use LiturgicalCalendar\Components\ApiOptions;
use LiturgicalCalendar\Components\ApiOptions\Input;
use LiturgicalCalendar\Components\Http\HttpClientInterface;
use LiturgicalCalendar\Components\Http\HttpClientFactory;
use LiturgicalCalendar\Components\Http\LoggingHttpClient;
use LiturgicalCalendar\Components\Http\CachingHttpClient;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;

/**
 * Class Locale
 *
 * Generates an HTML select element for selecting a locale in the Liturgical
 * Calendar API options form.
 *
 * Static Metadata Caching ("First Instance Wins"):
 * - Metadata (self::$metadata) is a process-wide static cache keyed by ApiOptions::getApiUrl()
 * - The FIRST instance to call setOptionsForCalendar() fetches metadata via its HTTP client
 * - Subsequent instances (even with different HTTP client configurations) reuse the cached metadata
 * - This means only the first instance's HTTP client, logger, and cache are used for the API request
 * - Each instance maintains its own HTTP client configuration, but only the first instance exercises it
 *
 * Implications for Testing:
 * - When testing with different HTTP client mocks, ensure tests run in isolation or reset static state
 * - The first Locale instance created in a test suite will determine the metadata for all subsequent instances
 *
 * @see LiturgicalCalendar\Components\ApiOptions
 * @see LiturgicalCalendar\Components\ApiOptions\Input
 */
class Locale extends Input
{
    /** @var \stdClass|null Process-wide metadata cache (first instance wins) */
    private static ?\stdClass $metadata = null;

    /** @var string[] */
    private static array $apiLocales = [];

    /** @var array<string,string[]> */
    private static array $apiLocalesDisplay = [];

    private HttpClientInterface $httpClient;

    /**
     * Create a new Locale input element.
     *
     * HTTP Client Configuration vs. Metadata Fetching:
     * - Each instance maintains its own HTTP client configuration (with optional caching and logging)
     * - However, API metadata is a process-wide static cache keyed by ApiOptions::getApiUrl()
     * - Only the FIRST instance will actually use its HTTP client to fetch metadata
     * - Subsequent instances will reuse the cached metadata, regardless of their HTTP client configuration
     * - This means the first instance's logger and cache will be used for the metadata request
     *
     * HTTP Client Decoration:
     * - If cache is provided: wraps client with CachingHttpClient
     * - If logger is provided: wraps client with LoggingHttpClient
     * - Decoration order: CachingHttpClient (inner) -> LoggingHttpClient (outer)
     *
     * WARNING: Avoid double-wrapping by choosing one approach:
     * 1. Pass a raw/base HTTP client + logger/cache parameters (recommended for most cases)
     * 2. Pass a pre-composed/decorated client with logger=null, cache=null (for advanced scenarios)
     *
     * Do NOT pass an already-decorated client along with logger/cache parameters,
     * as this will result in double-wrapping (e.g., logging the same request twice).
     *
     * Examples:
     * ```php
     * // ✅ RECOMMENDED: Pass raw client with logger/cache
     * $client = HttpClientFactory::create();
     * $logger = new Logger('locale');
     * $cache = new ArrayCache();
     * $locale = new Locale($client, $logger, $cache);
     *
     * // ✅ ALSO GOOD: Let constructor auto-discover client
     * $logger = new Logger('locale');
     * $cache = new ArrayCache();
     * $locale = new Locale(null, $logger, $cache);
     *
     * // ✅ ADVANCED: Pre-composed client, no additional decoration
     * $client = new LoggingHttpClient(
     *     new CachingHttpClient(HttpClientFactory::create(), $cache, 86400, $logger),
     *     $logger
     * );
     * $locale = new Locale($client, null, null);
     *
     * // ❌ WRONG: Pre-composed client with logger/cache (double-wrapping!)
     * $client = new LoggingHttpClient(HttpClientFactory::create(), $logger);
     * $locale = new Locale($client, $logger, $cache); // Will wrap logger TWICE
     * ```
     *
     * @param HttpClientInterface|null $httpClient Optional HTTP client for API requests. If null, uses auto-discovery.
     * @param LoggerInterface|null $logger Optional PSR-3 logger (only use if $httpClient is NOT already decorated).
     * @param CacheInterface|null $cache Optional PSR-16 cache (only use if $httpClient is NOT already decorated).
     * @param int $cacheTtl Cache TTL in seconds (default: 24 hours for locale data).
     */
    public function __construct(
        ?HttpClientInterface $httpClient = null,
        ?LoggerInterface $logger = null,
        ?CacheInterface $cache = null,
        int $cacheTtl = 86400
    ) {
        // Initialize instance HTTP client with provided configuration
        $baseClient = $httpClient ?? HttpClientFactory::create();

        // WARNING: The following wrapping only works correctly if $httpClient is NOT already decorated.
        // If you're using a pre-composed client with caching/logging already applied,
        // you should pass logger=null and cache=null to avoid double-wrapping.

        // Wrap with caching if cache provided
        if ($cache !== null) {
            $baseClient = new CachingHttpClient(
                $baseClient,
                $cache,
                $cacheTtl,
                $logger ?? new NullLogger()
            );
        }

        // Wrap with logging if logger provided
        if ($logger !== null) {
            $this->httpClient = new LoggingHttpClient($baseClient, $logger);
        } else {
            $this->httpClient = $baseClient;
        }

        $this->data(['param' => 'locale']);
        $this->setOptionsForCalendar(null, null);
        $this->name('locale');
        $this->id('locale');
    }

    /**
     * Generates and returns an HTML string for a locale select input.
     *
     * This method creates an HTML string representing a select input element
     * for locales, wrapped in a specified HTML element if applicable.
     * It uses class attributes for styling the label, input, and wrapper.
     * The options for the select input are generated based on available locales,
     * with the default selection set to Latin ('la').
     *
     * @return string The HTML string for the locale select input.
     */
    public function get(): string
    {
        $html = '';

        $labelClass = $this->labelClass !== null
            ? " class=\"{$this->labelClass}\""
            : ( self::$globalLabelClass !== null
                ? ' class="' . self::$globalLabelClass . '"'
                : '' );
        $labelAfter = $this->labelAfter !== null ? ' ' . $this->labelAfter : '';

        $inputClass = $this->inputClass !== null
            ? " class=\"{$this->inputClass}\""
            : ( self::$globalInputClass !== null
                ? ' class="' . self::$globalInputClass . '"'
                : '' );

        $wrapperClass = $this->wrapperClass !== null
            ? " class=\"{$this->wrapperClass}\""
            : ( self::$globalWrapperClass !== null
                ? ' class="' . self::$globalWrapperClass . '"'
                : '' );
        $wrapper      = $this->wrapper !== null
            ? $this->wrapper
            : ( self::$globalWrapper !== null
                ? self::$globalWrapper
                : null );

        $disabled = $this->disabled ? ' disabled' : '';

        if (is_string($this->selectedValue) && $this->selectedValue !== '' && !array_key_exists($this->selectedValue, self::$apiLocalesDisplay[ApiOptions::getLocale()])) {
            $baseLocale = \Locale::getPrimaryLanguage($this->selectedValue);
            if ($baseLocale !== null && array_key_exists($baseLocale, self::$apiLocalesDisplay[ApiOptions::getLocale()])) {
                $this->selectedValue = $baseLocale;
            } else {
                $this->selectedValue = array_keys(self::$apiLocalesDisplay[ApiOptions::getLocale()])[0];
            }
        }

        $options     = array_map(
            fn (string $k, string $v): string => "<option value=\"{$k}\"" . ( $k === $this->selectedValue ? ' selected' : '' ) . ">{$v}</option>",
            array_keys(self::$apiLocalesDisplay[ApiOptions::getLocale()]),
            array_values(self::$apiLocalesDisplay[ApiOptions::getLocale()])
        );
        $optionsHtml = implode('', $options);

        $data = $this->getData();
        $for  = $this->id !== '' ? " for=\"{$this->id}\"" : '';
        $id   = $this->id !== '' ? " id=\"{$this->id}\"" : '';
        $name = $this->name !== '' ? " name=\"{$this->name}\"" : '';

        $html .= $wrapper !== null ? "<{$wrapper}{$wrapperClass}>" : '';
        $html .= "<label{$labelClass}{$for}>locale{$labelAfter}</label>";
        $html .= "<select{$id}{$name}{$inputClass}{$data}{$disabled}>{$optionsHtml}</select>";
        $html .= $wrapper !== null ? "</{$wrapper}>" : '';
        return $html;
    }

    /**
     * Set locale options based on calendar type and ID.
     *
     * Static Metadata Caching Behavior:
     * - Metadata is cached in a single process-wide static variable (self::$metadata)
     * - The cache is NOT keyed by API URL - it is global for the entire PHP process
     * - The FIRST call to this method will fetch metadata from ApiOptions::getApiUrl()
     * - All subsequent calls (across all instances) will reuse the cached metadata
     * - No additional HTTP requests will be made after the initial fetch
     *
     * Important Limitations:
     * - The API base URL (ApiOptions::getApiUrl()) must NOT change after the first metadata fetch
     * - Only the first instance's HTTP client configuration (logger, cache, timeouts) will be used
     * - Subsequent instances will use cached data regardless of their HTTP client configuration
     * - If you need to support multiple API URLs in one process, the cache implementation
     *   would need to be refactored to key by URL (see code comments for details)
     *
     * @param string|null $calendarType The type of calendar ('nation', 'diocese', or null for general)
     * @param string|null $calendarId The calendar ID (required if calendarType is specified)
     * @throws \Exception If there is an error fetching or parsing API data
     * @return void
     */
    public function setOptionsForCalendar(?string $calendarType, ?string $calendarId): void
    {
        $apiUrl = ApiOptions::getApiUrl();
        // Static cache check: only the first call will proceed to fetch metadata
        if (self::$metadata === null) {
            $url = "{$apiUrl}/calendars";

            $response = $this->httpClient->get($url);

            if ($response->getStatusCode() !== 200) {
                throw new \Exception(
                    "Failed to fetch locales from {$url}. " .
                    "HTTP Status: {$response->getStatusCode()}"
                );
            }

            $metadataRaw  = $response->getBody()->getContents();
            $metadataJson = json_decode($metadataRaw);
            if (JSON_ERROR_NONE !== json_last_error()) {
                throw new \Exception("Failed to decode locales from {$apiUrl}/calendars");
            }
            if (!is_object($metadataJson) || false === property_exists($metadataJson, 'litcal_metadata') || false === $metadataJson->litcal_metadata instanceof \stdClass) {
                throw new \Exception("Invalid `litcal_metadata` property from {$apiUrl}/calendars, should exist and should be object");
            }
            self::$metadata = $metadataJson->litcal_metadata;
        }

        if (null === $calendarType && null === $calendarId) {
            if (
                false === property_exists(self::$metadata, 'locales')
                || false === is_array(self::$metadata->locales)
            ) {
                throw new \Exception("Invalid `litcal_metadata.locales` property from {$apiUrl}/calendars, should exist and should be array");
            }

            $locales = self::$metadata->locales;
            /** @var array<string> $locales */
            self::$apiLocales = $locales;
            $this->generateLocaleDisplay();
        } elseif (null === $calendarId || null === $calendarType) {
            throw new \Exception('Invalid calendarType or calendarId');
        } else {
            switch ($calendarType) {
                case 'nation':
                    if (false === property_exists(self::$metadata, 'national_calendars') || false === is_array(self::$metadata->national_calendars)) {
                        throw new \Exception("Invalid `litcal_metadata.national_calendars` property from {$apiUrl}/calendars, should exist and should be array");
                    }
                    $calendarMetadata = array_values(array_filter(self::$metadata->national_calendars, function (mixed $calendar) use ($calendarId) {
                        if (!is_object($calendar) || !property_exists($calendar, 'calendar_id')) {
                            return false;
                        }
                        return $calendar->calendar_id === $calendarId;
                    }));
                    if (empty($calendarMetadata)) {
                        throw new \Exception("Invalid calendarId: {$calendarId}");
                    }
                    $calendar = $calendarMetadata[0];
                    /** @var object{calendar_id: string, locales: array<string>} $calendar */
                    if (false === property_exists($calendar, 'locales') || false === is_array($calendar->locales)) {
                        throw new \Exception("Invalid `litcal_metadata.national_calendars[calendar_id={$calendarId}].locales` property from {$apiUrl}/calendars, should exist and should be array");
                    }
                    $locales = $calendar->locales;
                    /** @var array<string> $locales */
                    self::$apiLocales = $locales;
                    $this->generateLocaleDisplay();
                    break;
                case 'diocese':
                    if (false === property_exists(self::$metadata, 'diocesan_calendars') || false === is_array(self::$metadata->diocesan_calendars)) {
                        throw new \Exception("Invalid `litcal_metadata.diocesan_calendars` property from {$apiUrl}/calendars, should exist and should be array");
                    }
                    $calendarMetadata = array_values(array_filter(self::$metadata->diocesan_calendars, function (mixed $calendar) use ($calendarId) {
                        if (!is_object($calendar) || !property_exists($calendar, 'calendar_id')) {
                            return false;
                        }
                        return $calendar->calendar_id === $calendarId;
                    }));
                    if (empty($calendarMetadata)) {
                        throw new \Exception("Invalid calendarId: {$calendarId}");
                    }
                    $calendar = $calendarMetadata[0];
                    /** @var object{calendar_id: string, locales: array<string>} $calendar */
                    if (false === property_exists($calendar, 'locales') || false === is_array($calendar->locales)) {
                        throw new \Exception("Invalid `litcal_metadata.diocesan_calendars[calendar_id={$calendarId}].locales` property from {$apiUrl}/calendars, should exist and should be array");
                    }
                    $locales = $calendar->locales;
                    /** @var array<string> $locales */
                    self::$apiLocales = $locales;
                    $this->generateLocaleDisplay();
                    break;
                default:
                    throw new \Exception("Invalid calendarType: {$calendarType}");
            }
        }
    }

    /**
     * Generate locale display names for the current locale.
     *
     * Takes the locales stored in self::$apiLocales (populated by setOptionsForCalendar),
     * generates display names using the current ApiOptions::getLocale(), and stores
     * the sorted result in self::$apiLocalesDisplay.
     *
     * Note: self::$apiLocalesDisplay is keyed by locale to support different display
     * languages, but self::$apiLocales and self::$metadata are global (not keyed by API URL).
     * This means changing ApiOptions::getApiUrl() after the first metadata fetch is not supported.
     *
     * @return void
     */
    private function generateLocaleDisplay(): void
    {
        $localeDisplay = array_reduce(self::$apiLocales, function (array $carry, string $item): array {
            $carry[$item] = \Locale::getDisplayName($item, ApiOptions::getLocale());
            return $carry;
        }, []);
        /** @var array<string> $localeDisplay */
        self::$apiLocalesDisplay[ApiOptions::getLocale()] = $localeDisplay;
        asort(self::$apiLocalesDisplay[ApiOptions::getLocale()]);
    }
}
