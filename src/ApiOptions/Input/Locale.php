<?php

namespace LiturgicalCalendar\Components\ApiOptions\Input;

use LiturgicalCalendar\Components\ApiOptions;
use LiturgicalCalendar\Components\ApiOptions\Input;
use LiturgicalCalendar\Components\Http\HttpClientInterface;
use LiturgicalCalendar\Components\Http\HttpClientFactory;
use LiturgicalCalendar\Components\Http\LoggingHttpClient;
use LiturgicalCalendar\Components\Http\CachingHttpClient;
use LiturgicalCalendar\Components\Metadata\MetadataProvider;
use LiturgicalCalendar\Components\Models\Index\CalendarIndex;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;

/**
 * Class Locale
 *
 * Generates an HTML select element for selecting a locale in the Liturgical
 * Calendar API options form.
 *
 * Metadata Caching:
 * - Uses centralized MetadataProvider for fetching and caching calendar metadata
 * - Metadata is cached per API URL for the current process
 * - Shared across all components that use the same MetadataProvider instance
 *
 * @see LiturgicalCalendar\Components\ApiOptions
 * @see LiturgicalCalendar\Components\ApiOptions\Input
 */
class Locale extends Input
{
    /** @var string[] */
    private static array $apiLocales = [];

    /** @var array<string,string[]> */
    private static array $apiLocalesDisplay = [];

    private MetadataProvider $metadataProvider;

    /**
     * Create a new Locale input element.
     *
     * HTTP Client Configuration:
     * - Uses MetadataProvider for centralized metadata fetching and caching
     * - If cache is provided: MetadataProvider uses it for caching metadata
     * - If logger is provided: MetadataProvider uses it for logging
     *
     * WARNING: Avoid double-wrapping by choosing one approach:
     * 1. Pass a raw/base HTTP client + logger/cache parameters (recommended for most cases)
     * 2. Pass a pre-composed/decorated client with logger=null, cache=null (for advanced scenarios)
     *
     * Examples:
     * ```php
     * // ✅ RECOMMENDED: Let constructor auto-discover client
     * $logger = new Logger('locale');
     * $cache = new ArrayCache();
     * $locale = new Locale(null, $logger, $cache);
     *
     * // ✅ ADVANCED: Pre-composed client, no additional decoration
     * $client = HttpClientFactory::createProductionClient($cache, $logger);
     * $locale = new Locale($client, null, null);
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
        // Initialize MetadataProvider with provided dependencies
        $this->metadataProvider = MetadataProvider::getInstance(
            $httpClient,
            $cache,
            $logger,
            $cacheTtl
        );

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
     * Metadata Caching:
     * - Uses MetadataProvider for centralized metadata fetching and caching
     * - Metadata is cached per API URL for the current process
     * - Multiple calls with the same API URL will use cached metadata
     *
     * @param string|null $calendarType The type of calendar ('nation', 'diocese', or null for general)
     * @param string|null $calendarId The calendar ID (required if calendarType is specified)
     * @throws \Exception If there is an error fetching or parsing API data
     * @return void
     */
    public function setOptionsForCalendar(?string $calendarType, ?string $calendarId): void
    {
        $apiUrl   = ApiOptions::getApiUrl();
        $metadata = $this->metadataProvider->getMetadata($apiUrl);

        if (null === $calendarType && null === $calendarId) {
            $locales = $metadata->locales;
            /** @var array<string> $locales */
            self::$apiLocales = $locales;
            $this->generateLocaleDisplay();
        } elseif (null === $calendarId || null === $calendarType) {
            throw new \Exception('Invalid calendarType or calendarId');
        } else {
            switch ($calendarType) {
                case 'nation':
                    $calendarMetadata = array_values(array_filter(
                        $metadata->nationalCalendars,
                        fn ($calendar) => $calendar->calendarId === $calendarId
                    ));
                    if (empty($calendarMetadata)) {
                        throw new \Exception("Invalid calendarId: {$calendarId}");
                    }
                    $calendar = $calendarMetadata[0];
                    $locales  = $calendar->locales;
                    /** @var array<string> $locales */
                    self::$apiLocales = $locales;
                    $this->generateLocaleDisplay();
                    break;
                case 'diocese':
                    $calendarMetadata = array_values(array_filter(
                        $metadata->diocesanCalendars,
                        fn ($calendar) => $calendar->calendarId === $calendarId
                    ));
                    if (empty($calendarMetadata)) {
                        throw new \Exception("Invalid calendarId: {$calendarId}");
                    }
                    $calendar = $calendarMetadata[0];
                    $locales  = $calendar->locales;
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
