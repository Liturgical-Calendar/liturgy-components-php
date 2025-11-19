<?php

namespace LiturgicalCalendar\Components;

use LiturgicalCalendar\Components\Http\HttpClientInterface;
use LiturgicalCalendar\Components\Http\HttpClientFactory;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;

/**
 * Calendar Request Component
 *
 * Fetches liturgical calendar data from the API using PSR-18 HTTP client.
 * Supports caching, logging, retry, and circuit breaker patterns.
 *
 * **Usage Examples:**
 *
 * ```php
 * // Simple request for General Roman Calendar
 * $request = new CalendarRequest();
 * $calendar = $request->year(2024)->locale('en')->get();
 *
 * // National calendar request
 * $calendar = $request->nation('US')->year(2024)->locale('en')->get();
 *
 * // Diocesan calendar
 * $calendar = $request->diocese('DIOCESE001')->year(2025)->locale('la')->get();
 *
 * // General Roman Calendar with custom options (only for general calendar)
 * $calendar = $request->year(2024)
 *     ->epiphany('SUNDAY_JAN2_JAN8')
 *     ->ascension('SUNDAY')
 *     ->corpusChristi('SUNDAY')
 *     ->eternalHighPriest(true)
 *     ->get();
 * ```
 *
 * **Important**: The epiphany, ascension, corpusChristi, eternalHighPriest, and
 * holydaysOfObligation parameters are ONLY applicable to General Roman Calendar
 * requests. National and diocesan calendars have these settings predefined by
 * the calendar itself and will ignore these parameters.
 */
class CalendarRequest
{
    private string $baseUrl          = 'https://litcal.johnromanodorazio.com/api/dev';
    private ?string $calendarType    = null;  // 'nation' or 'diocese'
    private ?string $calendarId      = null;
    private ?int $year               = null;
    private ?string $yearType        = null;
    private ?string $locale          = null;
    private ?string $returnType      = null;
    private ?string $epiphany        = null;
    private ?string $ascension       = null;
    private ?string $corpusChristi   = null;
    private ?bool $eternalHighPriest = null;
    /** @var array<string> */
    private array $holydaysOfObligation = [];
    /** @var array<string,string> */
    private array $customHeaders = [];

    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;

    /**
     * Create a new CalendarRequest instance
     *
     * Dependency resolution priority:
     * 1. Explicit constructor parameters (highest priority)
     * 2. ApiClient configuration (if initialized)
     * 3. Default values (fallback)
     *
     * Note: The $cache parameter exists for API consistency but is not used directly.
     * Caching is handled by the HttpClient middleware that's already configured.
     *
     * @param HttpClientInterface|null $httpClient HTTP client for requests
     * @param LoggerInterface|null $logger PSR-3 logger for request/response logging
     * @param CacheInterface|null $cache PSR-16 cache (for API consistency, not used directly)
     * @param string|null $apiUrl Base API URL
     */
    public function __construct(
        ?HttpClientInterface $httpClient = null,
        ?LoggerInterface $logger = null,
        ?CacheInterface $cache = null,
        ?string $apiUrl = null
    ) {
        unset($cache); // Intentionally unused - caching handled by HttpClient middleware
        // Priority: explicit params > ApiClient > defaults
        $this->baseUrl = $apiUrl
            ?? ApiClient::getApiUrl()
            ?? $this->baseUrl;

        $finalHttpClient = $httpClient
            ?? ApiClient::getHttpClient();

        $finalLogger = $logger
            ?? ApiClient::getLogger()
            ?? new NullLogger();

        $this->httpClient = $finalHttpClient ?? HttpClientFactory::create();
        $this->logger     = $finalLogger;
    }

    /**
     * Set base API URL
     *
     * @param string $url Base API URL (without trailing slash)
     * @return self
     */
    public function baseUrl(string $url): self
    {
        $this->baseUrl = rtrim($url, '/');
        return $this;
    }

    /**
     * Request national calendar
     *
     * @param string $nationCode ISO 3166-1 alpha-2 country code (e.g., 'US', 'IT', 'FR')
     * @return self
     */
    public function nation(string $nationCode): self
    {
        $this->calendarType = 'nation';
        $this->calendarId   = $nationCode;
        return $this;
    }

    /**
     * Request diocesan calendar
     *
     * @param string $dioceseId 9-character diocesan calendar ID
     * @return self
     */
    public function diocese(string $dioceseId): self
    {
        $this->calendarType = 'diocese';
        $this->calendarId   = $dioceseId;
        return $this;
    }

    /**
     * Set calendar year
     *
     * @param int $year Calendar year (1970-9999)
     * @return self
     * @throws \InvalidArgumentException If year is out of range
     */
    public function year(int $year): self
    {
        if ($year < 1970 || $year > 9999) {
            throw new \InvalidArgumentException("Year must be between 1970 and 9999, got {$year}");
        }
        $this->year = $year;
        return $this;
    }

    /**
     * Set year type (LITURGICAL or CIVIL)
     *
     * @param string $type Year type
     * @return self
     */
    public function yearType(string $type): self
    {
        $this->yearType = $type;
        return $this;
    }

    /**
     * Set locale for localized content
     *
     * **Security Note**: The locale value is validated to prevent header injection attacks.
     * Locale values containing CR or LF characters are rejected.
     *
     * @param string $locale IETF language code (e.g., 'en', 'it', 'la', 'es')
     * @return self
     * @throws \InvalidArgumentException If locale contains CR/LF characters
     */
    public function locale(string $locale): self
    {
        // Validate locale: reject CR/LF characters to prevent header injection
        // (locale is used in Accept-Language header)
        if (str_contains($locale, "\r") || str_contains($locale, "\n")) {
            throw new \InvalidArgumentException(
                'Invalid locale value: ' .
                'Locale cannot contain CR or LF characters (possible header injection attempt).'
            );
        }

        $this->locale = $locale;
        return $this;
    }

    /**
     * Set return type (json, xml, yaml, ical)
     *
     * @param string $type Response format type
     * @return self
     */
    public function returnType(string $type): self
    {
        $this->returnType = $type;
        return $this;
    }

    /**
     * Set Epiphany setting
     *
     * **NOTE**: Only applicable to General Roman Calendar requests.
     * National and diocesan calendars have this predefined by the calendar.
     * This parameter will be ignored for national/diocesan calendar requests.
     *
     * @param string $setting Epiphany celebration setting
     * @return self
     */
    public function epiphany(string $setting): self
    {
        $this->epiphany = $setting;
        return $this;
    }

    /**
     * Set Ascension setting
     *
     * **NOTE**: Only applicable to General Roman Calendar requests.
     * National and diocesan calendars have this predefined by the calendar.
     * This parameter will be ignored for national/diocesan calendar requests.
     *
     * @param string $setting Ascension celebration setting
     * @return self
     */
    public function ascension(string $setting): self
    {
        $this->ascension = $setting;
        return $this;
    }

    /**
     * Set Corpus Christi setting
     *
     * **NOTE**: Only applicable to General Roman Calendar requests.
     * National and diocesan calendars have this predefined by the calendar.
     * This parameter will be ignored for national/diocesan calendar requests.
     *
     * @param string $setting Corpus Christi celebration setting
     * @return self
     */
    public function corpusChristi(string $setting): self
    {
        $this->corpusChristi = $setting;
        return $this;
    }

    /**
     * Set Eternal High Priest setting
     *
     * **NOTE**: Only applicable to General Roman Calendar requests.
     * National and diocesan calendars have this predefined by the calendar.
     * This parameter will be ignored for national/diocesan calendar requests.
     *
     * @param bool $enabled Whether to include Eternal High Priest feast
     * @return self
     */
    public function eternalHighPriest(bool $enabled): self
    {
        $this->eternalHighPriest = $enabled;
        return $this;
    }

    /**
     * Set holydays of obligation
     *
     * **NOTE**: Only applicable to General Roman Calendar requests.
     * National and diocesan calendars have this predefined by the calendar.
     * This parameter will be ignored for national/diocesan calendar requests.
     *
     * @param array<string> $holydays Array of holyday identifiers
     * @return self
     */
    public function holydaysOfObligation(array $holydays): self
    {
        $this->holydaysOfObligation = $holydays;
        return $this;
    }

    /**
     * Add custom header
     *
     * **Note**: The 'Accept' header will be overridden if returnType() is set,
     * as the API prioritizes the return_type parameter over the Accept header.
     * For other headers, custom values will be used as-is.
     *
     * **Security**: Header names and values are validated to prevent CRLF injection attacks.
     * Only alphanumeric characters, hyphens, and underscores are allowed in header names.
     * Header values cannot contain CR or LF characters.
     *
     * @param string $name Header name (alphanumeric, hyphen, underscore only)
     * @param string $value Header value (no CR/LF characters)
     * @return self
     * @throws \InvalidArgumentException If header name or value is invalid
     */
    public function header(string $name, string $value): self
    {
        // Validate header name: only letters, digits, hyphen, and underscore
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $name)) {
            throw new \InvalidArgumentException(
                "Invalid header name: '{$name}'. " .
                'Header names must contain only letters, digits, hyphens, and underscores.'
            );
        }

        // Validate header value: reject CR/LF characters to prevent header injection
        if (str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new \InvalidArgumentException(
                "Invalid header value for '{$name}': " .
                'Header values cannot contain CR or LF characters (possible header injection attempt).'
            );
        }

        $this->customHeaders[$name] = $value;
        return $this;
    }

    /**
     * Set Accept-Language header
     *
     * @param string $language Accept-Language header value (e.g., 'en-US,en;q=0.9')
     * @return self
     */
    public function acceptLanguage(string $language): self
    {
        return $this->header('Accept-Language', $language);
    }

    /**
     * Execute request and return calendar data
     *
     * @return \stdClass Calendar response object
     * @throws \Exception If request fails or response is invalid
     */
    public function get(): \stdClass
    {
        $url     = $this->buildUrl();
        $headers = $this->buildHeaders();
        $body    = $this->buildPostData();

        $this->logger->info('Fetching calendar data', [
            'url'           => $url,
            'calendar_type' => $this->calendarType,
            'calendar_id'   => $this->calendarId,
            'year'          => $this->year,
        ]);

        try {
            $response = $this->httpClient->post($url, $body, $headers);

            if ($response->getStatusCode() !== 200) {
                throw new \Exception(
                    "Calendar API returned status {$response->getStatusCode()}"
                );
            }

            $responseBody = $response->getBody()->getContents();
            $calendar     = json_decode(
                $responseBody,
                associative: false,
                flags: JSON_THROW_ON_ERROR
            );

            if (!( $calendar instanceof \stdClass )) {
                throw new \Exception(
                    'Invalid JSON response: expected stdClass object, got ' . gettype($calendar)
                );
            }

            $this->validateResponse($calendar);

            return $calendar;
        } catch (\Exception $e) {
            $this->logger->error('Calendar request failed', [
                'error' => $e->getMessage(),
                'url'   => $url,
            ]);
            throw $e;
        }
    }

    /**
     * Get the request URL that will be used for the API call
     *
     * This method is useful for debugging or displaying the API endpoint
     * that will be requested based on the current configuration.
     *
     * @return string The complete API endpoint URL
     */
    public function getRequestUrl(): string
    {
        return $this->buildUrl();
    }

    /**
     * Build request URL
     *
     * Constructs the API endpoint URL from base URL and path segments.
     * All path segments are properly URL-encoded to prevent injection attacks
     * and handle special characters correctly.
     *
     * @return string The complete API endpoint URL
     */
    private function buildUrl(): string
    {
        // Start with the calendar endpoint
        $pathSegments = ['calendar'];

        // Add calendar type and ID if specified (both are required together)
        if ($this->calendarType && $this->calendarId) {
            $pathSegments[] = rawurlencode($this->calendarType);
            $pathSegments[] = rawurlencode($this->calendarId);
        }

        // Add year if specified
        if ($this->year !== null) {
            $pathSegments[] = rawurlencode((string) $this->year);
        }

        // Build the path from encoded segments
        $path = '/' . implode('/', $pathSegments);

        // Ensure baseUrl doesn't have trailing slash to avoid double slashes
        $baseUrl = rtrim($this->baseUrl, '/');

        return $baseUrl . $path;
    }

    /**
     * Build request headers
     *
     * Generates HTTP headers for the API request. Header precedence:
     * - The Accept header is ALWAYS set from returnType() if specified, as the API
     *   prioritizes the return_type parameter over the Accept header
     * - Other custom headers (set via header() method) take precedence over defaults
     * - Default headers are used if not overridden
     *
     * Note: Any custom 'Accept' header will be overridden if returnType is set,
     * because the API ignores the Accept header when return_type parameter is present.
     *
     * @return array<string,string> Associative array of header name => value
     */
    private function buildHeaders(): array
    {
        $headers = ['Accept' => 'application/json'];

        if ($this->locale) {
            $headers['Accept-Language'] = $this->locale;
        }

        // Merge custom headers (they can override defaults)
        $finalHeaders = array_merge($headers, $this->customHeaders);

        // returnType ALWAYS overrides Accept header (API prioritizes return_type parameter)
        if ($this->returnType) {
            $finalHeaders['Accept'] = match ($this->returnType) {
                'xml'  => 'application/xml',
                'yaml' => 'application/yaml',
                'ical' => 'text/calendar',
                default => 'application/json',
            };
        }

        return $finalHeaders;
    }

    /**
     * Build POST data
     *
     * **NOTE**: Parameters like epiphany, ascension, corpus_christi, eternal_high_priest,
     * and holydays_of_obligation are only meaningful for General Roman Calendar requests.
     * National and diocesan calendars ignore these as they have predefined settings.
     * These will be included in POST data regardless, but the API will ignore them
     * for national/diocesan calendar requests.
     *
     * @return array<string,mixed> Associative array of POST parameters
     */
    private function buildPostData(): array
    {
        $data = [];

        if ($this->yearType) {
            $data['year_type'] = $this->yearType;
        }

        if ($this->epiphany) {
            $data['epiphany'] = $this->epiphany;
        }

        if ($this->ascension) {
            $data['ascension'] = $this->ascension;
        }

        if ($this->corpusChristi) {
            $data['corpus_christi'] = $this->corpusChristi;
        }

        if ($this->eternalHighPriest !== null) {
            $data['eternal_high_priest'] = $this->eternalHighPriest;
        }

        if (!empty($this->holydaysOfObligation)) {
            $data['holydays_of_obligation'] = $this->holydaysOfObligation;
        }

        return $data;
    }

    /**
     * Validate API response
     *
     * @param \stdClass $calendar The calendar response object
     * @return void
     * @throws \Exception If response is invalid
     */
    private function validateResponse(\stdClass $calendar): void
    {
        if (!property_exists($calendar, 'litcal')) {
            throw new \Exception('Invalid calendar response: missing litcal property');
        }

        if (!property_exists($calendar, 'settings')) {
            throw new \Exception('Invalid calendar response: missing settings property');
        }

        if (!is_array($calendar->litcal)) {
            throw new \Exception('Invalid calendar response: litcal must be array');
        }
    }
}
