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
 * Note: Metadata fetched from the API is cached statically across all instances
 * for performance. However, each instance maintains its own HTTP client configuration.
 *
 * @see LiturgicalCalendar\Components\ApiOptions
 * @see LiturgicalCalendar\Components\ApiOptions\Input
 */
class Locale extends Input
{
    private static ?\stdClass $metadata = null;

    /** @var string[] */
    private static array $apiLocales = [];

    /** @var array<string,string[]> */
    private static array $apiLocalesDisplay = [];

    private HttpClientInterface $httpClient;

    /**
     * Create a new Locale input element.
     *
     * Each instance maintains its own HTTP client configuration (with optional caching and logging).
     * However, API metadata is cached statically across all instances for performance optimization.
     *
     * @param HttpClientInterface|null $httpClient Optional HTTP client for API requests. If null, uses auto-discovery.
     * @param LoggerInterface|null $logger Optional PSR-3 logger for HTTP request/response logging.
     * @param CacheInterface|null $cache Optional PSR-16 cache for HTTP response caching.
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
     * Fetches metadata from the API on first call and caches it statically.
     * Subsequent calls (even from different instances) will use the cached metadata.
     * Only the first instance's HTTP client will be used for the API request.
     *
     * @param string|null $calendarType The type of calendar ('nation', 'diocese', or null for general)
     * @param string|null $calendarId The calendar ID (required if calendarType is specified)
     * @throws \Exception If there is an error fetching or parsing API data
     * @return void
     */
    public function setOptionsForCalendar(?string $calendarType, ?string $calendarId): void
    {
        $apiUrl = ApiOptions::getApiUrl();
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
            $localeDisplay    = array_reduce(self::$apiLocales, function (array $carry, string $item): array {
                $carry[$item] = \Locale::getDisplayName($item, ApiOptions::getLocale());
                return $carry;
            }, []);
            /** @var array<string> $localeDisplay */
            self::$apiLocalesDisplay[ApiOptions::getLocale()] = $localeDisplay;
            asort(self::$apiLocalesDisplay[ApiOptions::getLocale()]);
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
                    $localeDisplay    = array_reduce(self::$apiLocales, function (array $carry, string $item): array {
                        $carry[$item] = \Locale::getDisplayName($item, ApiOptions::getLocale());
                        return $carry;
                    }, []);
                    /** @var array<string> $localeDisplay */
                    self::$apiLocalesDisplay[ApiOptions::getLocale()] = $localeDisplay;
                    asort(self::$apiLocalesDisplay[ApiOptions::getLocale()]);
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
                    $localeDisplay    = array_reduce(self::$apiLocales, function (array $carry, string $item): array {
                        $carry[$item] = \Locale::getDisplayName($item, ApiOptions::getLocale());
                        return $carry;
                    }, []);
                    /** @var array<string> $localeDisplay */
                    self::$apiLocalesDisplay[ApiOptions::getLocale()] = $localeDisplay;
                    asort(self::$apiLocalesDisplay[ApiOptions::getLocale()]);
                    break;
                default:
                    throw new \Exception("Invalid calendarType: {$calendarType}");
            }
        }
    }
}
