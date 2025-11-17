# Feature Roadmap

This document outlines planned features and enhancements for the Liturgical Calendar Components library.

## Table of Contents

- [Current State Analysis](#current-state-analysis)
- [Feature: CalendarRequest Component](#feature-calendarrequest-component)
- [Feature: Calendar Response Caching](#feature-calendar-response-caching)
- [Feature: Batch Request Support](#feature-batch-request-support)
- [Additional Enhancements](#additional-enhancements)
- [Implementation Timeline](#implementation-timeline)

---

## Current State Analysis

### What We Have ✅

**Components:**

- `CalendarSelect` - Dropdown for selecting calendars (metadata requests only)
- `ApiOptions` - Form inputs for API parameters
- `WebCalendar` - Display component for calendar data

**HTTP Infrastructure:**

- PSR-7/17/18 compliant HTTP client
- PSR-3 logging support
- PSR-16 caching support
- Retry middleware
- Circuit breaker pattern
- Production-ready HTTP client factory

### What's Missing ❌

**Calendar Data Fetching:**

- No dedicated component for fetching calendar data from the API
- Examples use raw curl (see `examples/webcalendar/index.php:186-193`)
- POST request handling is manual and repetitive
- No built-in support for calendar request parameters
- No automatic response validation

**Current Manual Approach** (from examples/webcalendar/index.php):

```php
// Lines 186-193: Manual curl POST request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $requestUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($requestData));
$response = curl_exec($ch);
curl_close($ch);
```

**Problems with Current Approach:**

- ❌ Uses raw curl instead of PSR-18 HTTP client
- ❌ No caching of calendar data responses
- ❌ No retry or circuit breaker protection
- ❌ No logging of calendar requests
- ❌ Manual header construction
- ❌ Repetitive code in every implementation
- ❌ No response validation
- ❌ No type safety

---

## Feature: CalendarRequest Component

### Overview

Create a `CalendarRequest` component that encapsulates all calendar data fetching logic, leveraging our existing PSR-compliant HTTP infrastructure.

### Design Goals

1. **PSR-Compliant**: Use existing PSR-18 HTTP client infrastructure
1. **Fluent API**: Chainable methods for building requests
1. **Type-Safe**: Full PHPStan Level 10 compliance
1. **Cacheable**: Automatic caching of responses
1. **Observable**: Integrated logging
1. **Reliable**: Built-in retry and circuit breaker support
1. **Validated**: Automatic response validation

### Important API Parameter Restrictions

**Calendar-Specific Settings** - The following parameters are **only applicable to General Roman Calendar** requests:

- `epiphany` - Epiphany celebration setting
- `ascension` - Ascension celebration setting
- `corpus_christi` - Corpus Christi celebration setting
- `eternal_high_priest` - Eternal High Priest feast setting
- `holydays_of_obligation` - Holy days of obligation configuration

**Why?** National and diocesan calendars have these liturgical settings **predefined by the calendar itself** based on local church regulations and conferences of
bishops. These parameters are ignored by the API when requesting national (`/calendar/nation/{id}`) or diocesan (`/calendar/diocese/{id}`) calendars.

**Applicable Paths:**

- ✅ `/calendar` - General Roman Calendar (current year)
- ✅ `/calendar/{year}` - General Roman Calendar (specific year)
- ❌ `/calendar/nation/{id}` - National calendar (settings predefined)
- ❌ `/calendar/diocese/{id}` - Diocesan calendar (settings predefined)

### Proposed API

#### Basic Usage

```php
use LiturgicalCalendar\Components\CalendarRequest;

// Simple request for General Roman Calendar
$request = new CalendarRequest();
$calendar = $request->year(2024)
    ->locale('en')
    ->get();
```

#### Advanced Usage

```php
// National calendar request
$request = new CalendarRequest($httpClient, $logger, $cache);
$calendar = $request->nation('US')
    ->year(2024)
    ->locale('en')
    ->returnType('json') // or 'xml', 'yaml', 'ical'
    ->get();

// Note: epiphany, ascension, corpus_christi, eternal_high_priest, and
// holydays_of_obligation parameters are NOT applicable to national/diocesan
// calendars as these settings are defined by the calendar itself
```

#### Diocesan Calendar

```php
$calendar = $request->diocese('DIOCESE001')
    ->year(2025)
    ->locale('la')
    ->get();
```

#### General Roman Calendar with Custom Options

```php
// These parameters ONLY work with General Roman Calendar (no nation/diocese)
$calendar = $request->year(2024)
    ->yearType('LITURGICAL')
    ->epiphany('SUNDAY_JAN2_JAN8')
    ->ascension('SUNDAY')
    ->corpusChristi('SUNDAY')
    ->eternalHighPriest(true)
    ->holydaysOfObligation(['EPIPHANY', 'ASCENSION'])
    ->acceptLanguage('en-US,en;q=0.9')
    ->get();
```

**Important**: The `epiphany()`, `ascension()`, `corpusChristi()`, `eternalHighPriest()`, and `holydaysOfObligation()` methods are **only applicable to General Roman
Calendar** requests (bare `/calendar` or `/calendar/{year}` paths). National and diocesan calendars have these settings predefined and will ignore these parameters
if sent.

### Implementation Plan

#### Phase 1: Core CalendarRequest Component

**File:** `src/CalendarRequest.php`

```php
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
 */
class CalendarRequest
{
    private string $baseUrl = 'https://litcal.johnromanodorazio.com/api/dev';
    private ?string $calendarType = null;  // 'nation' or 'diocese'
    private ?string $calendarId = null;
    private ?int $year = null;
    private ?string $yearType = null;
    private ?string $locale = null;
    private ?string $returnType = null;
    private ?string $epiphany = null;
    private ?string $ascension = null;
    private ?string $corpusChristi = null;
    private ?bool $eternalHighPriest = null;
    private array $holydaysOfObligation = [];
    private array $customHeaders = [];

    public function __construct(
        private ?HttpClientInterface $httpClient = null,
        private ?LoggerInterface $logger = null,
        private ?CacheInterface $cache = null
    ) {
        $this->httpClient = $httpClient ?? HttpClientFactory::create();
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Set base API URL
     */
    public function baseUrl(string $url): self
    {
        $this->baseUrl = rtrim($url, '/');
        return $this;
    }

    /**
     * Request national calendar
     */
    public function nation(string $nationCode): self
    {
        $this->calendarType = 'nation';
        $this->calendarId = $nationCode;
        return $this;
    }

    /**
     * Request diocesan calendar
     */
    public function diocese(string $dioceseId): self
    {
        $this->calendarType = 'diocese';
        $this->calendarId = $dioceseId;
        return $this;
    }

    /**
     * Set calendar year
     */
    public function year(int $year): self
    {
        if ($year < 1970 || $year > 9999) {
            throw new \InvalidArgumentException("Year must be between 1970 and 9999");
        }
        $this->year = $year;
        return $this;
    }

    /**
     * Set year type (LITURGICAL or CIVIL)
     */
    public function yearType(string $type): self
    {
        $this->yearType = $type;
        return $this;
    }

    /**
     * Set locale
     */
    public function locale(string $locale): self
    {
        $this->locale = $locale;
        return $this;
    }

    /**
     * Set return type (json, xml, yaml, ical)
     */
    public function returnType(string $type): self
    {
        $this->returnType = $type;
        return $this;
    }

    /**
     * Set Epiphany setting
     *
     * NOTE: Only applicable to General Roman Calendar requests.
     * National and diocesan calendars have this predefined.
     */
    public function epiphany(string $setting): self
    {
        $this->epiphany = $setting;
        return $this;
    }

    /**
     * Set Ascension setting
     *
     * NOTE: Only applicable to General Roman Calendar requests.
     * National and diocesan calendars have this predefined.
     */
    public function ascension(string $setting): self
    {
        $this->ascension = $setting;
        return $this;
    }

    /**
     * Set Corpus Christi setting
     *
     * NOTE: Only applicable to General Roman Calendar requests.
     * National and diocesan calendars have this predefined.
     */
    public function corpusChristi(string $setting): self
    {
        $this->corpusChristi = $setting;
        return $this;
    }

    /**
     * Set Eternal High Priest setting
     *
     * NOTE: Only applicable to General Roman Calendar requests.
     * National and diocesan calendars have this predefined.
     */
    public function eternalHighPriest(bool $enabled): self
    {
        $this->eternalHighPriest = $enabled;
        return $this;
    }

    /**
     * Set holydays of obligation
     *
     * NOTE: Only applicable to General Roman Calendar requests.
     * National and diocesan calendars have this predefined.
     */
    public function holydaysOfObligation(array $holydays): self
    {
        $this->holydaysOfObligation = $holydays;
        return $this;
    }

    /**
     * Add custom header
     *
     * Note: The 'Accept' header will be overridden if returnType() is set,
     * as the API prioritizes the return_type parameter over the Accept header.
     * For other headers, custom values will be used as-is.
     *
     * Security: Header names and values are validated to prevent CRLF injection attacks.
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
                "Header names must contain only letters, digits, hyphens, and underscores."
            );
        }

        // Validate header value: reject CR/LF characters to prevent header injection
        if (str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new \InvalidArgumentException(
                "Invalid header value for '{$name}': " .
                "Header values cannot contain CR or LF characters (possible header injection attempt)."
            );
        }

        $this->customHeaders[$name] = $value;
        return $this;
    }

    /**
     * Set Accept-Language header
     */
    public function acceptLanguage(string $language): self
    {
        return $this->header('Accept-Language', $language);
    }
```

#### Header Validation Examples

```php
// ✅ Valid header names and values
$request->header('X-Custom-Header', 'value');
$request->header('Accept-Language', 'en-US');
$request->header('Authorization', 'Bearer token123');
$request->header('X_Custom_Header', 'value');  // Underscores allowed
$request->header('X-API-Version', '2.0');

// ❌ Invalid header names (throws InvalidArgumentException)
$request->header('X-Custom Header', 'value');  // Space not allowed
$request->header('X/Custom', 'value');          // Slash not allowed
$request->header('X:Custom', 'value');          // Colon not allowed
$request->header('', 'value');                  // Empty name not allowed

// ❌ Invalid header values (throws InvalidArgumentException - CRLF injection prevention)
$request->header('X-Custom', "value\r\nX-Injected: evil");  // CR/LF rejected
$request->header('X-Custom', "value\nX-Injected: evil");    // LF rejected
$request->header('X-Custom', "value\rX-Injected: evil");    // CR rejected

// 🔒 Security: Header injection attempts are blocked
try {
    $request->header('X-Custom', "innocent\r\nX-Injected-Header: evil\r\nX-Another: bad");
} catch (\InvalidArgumentException $e) {
    // Exception thrown: "Header values cannot contain CR or LF characters"
}
```

```php
    /**
     * Execute request and return calendar data
     *
     * @return \stdClass Calendar response object
     * @throws \Exception If request fails or response is invalid
     */
    public function get(): \stdClass
    {
        $url = $this->buildUrl();
        $headers = $this->buildHeaders();
        $body = $this->buildPostData();

        $this->logger->info('Fetching calendar data', [
            'url' => $url,
            'calendar_type' => $this->calendarType,
            'calendar_id' => $this->calendarId,
            'year' => $this->year,
        ]);

        try {
            $response = $this->httpClient->post($url, $body, $headers);

            if ($response->getStatusCode() !== 200) {
                throw new \Exception(
                    "Calendar API returned status {$response->getStatusCode()}"
                );
            }

            $responseBody = $response->getBody()->getContents();
            $calendar = json_decode(
                $responseBody,
                associative: false,
                flags: JSON_THROW_ON_ERROR
            );

            if (!is_object($calendar)) {
                throw new \Exception(
                    "Invalid JSON response: expected object, got " . gettype($calendar)
                );
            }

            $this->validateResponse($calendar);

            return $calendar;
        } catch (\Exception $e) {
            $this->logger->error('Calendar request failed', [
                'error' => $e->getMessage(),
                'url' => $url,
            ]);
            throw $e;
        }
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
            $pathSegments[] = rawurlencode((string)$this->year);
        }

        // Build the path from encoded segments
        $path = '/' . implode('/', $pathSegments);

        // Ensure baseUrl doesn't have trailing slash to avoid double slashes
        $baseUrl = rtrim($this->baseUrl, '/');

        return $baseUrl . $path;
    }
```

#### URL Building & Encoding Examples

```php
// ✅ Standard calendar requests - properly encoded
$request = new CalendarRequest();
$request->year(2024)->get();
// URL: https://litcal.johnromanodorazio.com/api/dev/calendar/2024

$request->nation('US')->year(2024)->get();
// URL: https://litcal.johnromanodorazio.com/api/dev/calendar/nation/US/2024

$request->diocese('DIOCESE001')->year(2025)->get();
// URL: https://litcal.johnromanodorazio.com/api/dev/calendar/diocese/DIOCESE001/2025

// 🔒 Security: Special characters are properly URL-encoded
$request->diocese('DIOCESE-01')->year(2024)->get();
// URL: https://litcal.../calendar/diocese/DIOCESE-01/2024 (hyphen is safe)

// 🔒 Security: Injection attempts are neutralized via encoding
// If someone tried to pass a malicious calendar ID:
$maliciousId = "../../../etc/passwd";
$request->diocese($maliciousId)->year(2024)->get();
// URL: https://litcal.../calendar/diocese/..%2F..%2F..%2Fetc%2Fpasswd/2024
// The "../" is encoded as "%2F", preventing path traversal

// 🔒 Security: URL injection attempts are encoded
$maliciousId = "evil/calendar/inject";
$request->nation($maliciousId)->year(2024)->get();
// URL: https://litcal.../calendar/nation/evil%2Fcalendar%2Finject/2024
// The "/" is encoded as "%2F", preventing path injection

// ✅ Base URL trailing slash handling (no double slashes)
$request = new CalendarRequest();
$request->baseUrl('https://api.example.com/');  // Has trailing slash
$request->year(2024)->get();
// URL: https://api.example.com/calendar/2024 (not //calendar)

$request->baseUrl('https://api.example.com');   // No trailing slash
$request->year(2024)->get();
// URL: https://api.example.com/calendar/2024 (same result)
```

```php
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
        $headers = [
            'Accept' => 'application/json',
        ];

        if ($this->locale) {
            $headers['Accept-Language'] = $this->locale;
        }

        // Merge custom headers (they can override defaults)
        $finalHeaders = array_merge($headers, $this->customHeaders);

        // returnType ALWAYS overrides Accept header (API prioritizes return_type parameter)
        if ($this->returnType) {
            $finalHeaders['Accept'] = match($this->returnType) {
                'xml' => 'application/xml',
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
     * NOTE: Parameters like epiphany, ascension, corpus_christi, eternal_high_priest,
     * and holydays_of_obligation are only meaningful for General Roman Calendar requests.
     * National and diocesan calendars ignore these as they have predefined settings.
     * These will be included in POST data regardless, but the API will ignore them
     * for national/diocesan calendar requests.
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
```

#### Phase 2: Response Models

**File:** `src/Models/CalendarResponse.php`

```php
<?php

namespace LiturgicalCalendar\Components\Models;

/**
 * Typed calendar response model
 */
class CalendarResponse
{
    public function __construct(
        public readonly array $litcal,
        public readonly CalendarSettings $settings,
        public readonly ?CalendarMetadata $metadata = null,
        public readonly ?array $messages = null
    ) {}

    public static function fromStdClass(\stdClass $data): self
    {
        return new self(
            litcal: (array) $data->litcal,
            settings: CalendarSettings::fromStdClass($data->settings),
            metadata: isset($data->metadata)
                ? CalendarMetadata::fromStdClass($data->metadata)
                : null,
            messages: isset($data->messages) ? (array) $data->messages : null
        );
    }

    /**
     * Get event by key
     */
    public function getEvent(string $key): ?object
    {
        return $this->litcal[$key] ?? null;
    }

    /**
     * Filter events by grade
     */
    public function eventsByGrade(int $grade): array
    {
        return array_filter(
            $this->litcal,
            fn($event) => $event->grade === $grade
        );
    }

    /**
     * Filter events by liturgical season
     */
    public function eventsBySeason(string $season): array
    {
        return array_filter(
            $this->litcal,
            fn($event) => $event->liturgical_season === $season
        );
    }

    /**
     * Get all solemnities (grade 6 and 7)
     */
    public function getSolemnities(): array
    {
        return array_filter(
            $this->litcal,
            fn($event) => $event->grade >= 6
        );
    }
}
```

#### Phase 3: Calendar Response Builder

**File:** `src/CalendarResponseBuilder.php`

```php
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
 */
class CalendarResponseBuilder
{
    /**
     * Quick request for General Roman Calendar
     *
     * @param int $year The liturgical year to fetch
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
        return (new CalendarRequest($httpClient, $logger, $cache))
            ->year($year)
            ->locale($locale)
            ->get();
    }

    /**
     * Quick request for National Calendar
     *
     * @param string $nation The national calendar ID (e.g., 'IT', 'US', 'FR')
     * @param int $year The liturgical year to fetch
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
        return (new CalendarRequest($httpClient, $logger, $cache))
            ->nation($nation)
            ->year($year)
            ->locale($locale)
            ->get();
    }

    /**
     * Quick request for Diocesan Calendar
     *
     * @param string $diocese The diocesan calendar ID (9-character format)
     * @param int $year The liturgical year to fetch
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
        return (new CalendarRequest($httpClient, $logger, $cache))
            ->diocese($diocese)
            ->year($year)
            ->locale($locale)
            ->get();
    }
}
```

#### CalendarResponseBuilder Usage Examples

```php
// Minimal usage - all dependencies auto-discovered
$calendar = CalendarResponseBuilder::generalCalendar(2024);

// With custom locale
$calendar = CalendarResponseBuilder::nationalCalendar('IT', 2024, 'it');

// With HTTP client for testing/mocking
$mockClient = new MockHttpClient();
$calendar = CalendarResponseBuilder::generalCalendar(2024, 'en', $mockClient);

// With logger for debugging
$logger = new Logger('calendar');
$calendar = CalendarResponseBuilder::nationalCalendar('US', 2024, 'en', null, $logger);

// With cache for performance
$cache = new FilesystemCache();
$calendar = CalendarResponseBuilder::generalCalendar(2024, 'en', null, null, $cache);

// Full configuration with all dependencies
$httpClient = HttpClientFactory::create();
$logger = new Logger('calendar');
$cache = new ArrayCache();
$calendar = CalendarResponseBuilder::diocesanCalendar(
    'DIOCESE001',
    2024,
    'en',
    $httpClient,
    $logger,
    $cache
);
```

### CalendarRequest Usage Examples

#### Before (Manual curl)

```php
// Current approach in examples/webcalendar/index.php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $requestUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($requestData));
$response = curl_exec($ch);
curl_close($ch);
$calendar = json_decode($response);
```

#### After (CalendarRequest Component)

```php
// Simple and clean
$request = new CalendarRequest($httpClient, $logger, $cache);
$calendar = $request->year(2024)
    ->locale('en')
    ->get();

// Or even simpler with static helper
$calendar = CalendarResponseBuilder::generalCalendar(2024, 'en', $httpClient, $logger, $cache);
```

### Benefits

1. **✅ PSR-Compliant**: Uses existing HTTP client infrastructure
1. **✅ Cached**: Automatic response caching (if cache provided)
1. **✅ Logged**: All requests logged (if logger provided)
1. **✅ Resilient**: Built-in retry and circuit breaker
1. **✅ Validated**: Automatic response validation
1. **✅ Type-Safe**: Full PHPStan compliance
1. **✅ Fluent**: Easy-to-use chainable API
1. **✅ Testable**: Easy to mock for unit tests

---

## Feature: Calendar Response Caching

### Caching Overview

Extend caching to include calendar data responses, not just metadata.

### Cache Strategy

**Cache Keys:**

Cache keys include all parameters that affect the calendar output:

```text
# Basic requests
calendar:general:2024:en
calendar:nation:US:2024:en
calendar:diocese:DIOCESE001:2025:la

# With year_type parameter (applies to all calendar types)
calendar:general:2024:en:yt:liturgical
calendar:nation:IT:2024:it:yt:civil

# General calendar with optional parameters (epiphany, ascension, etc.)
# Parameters are hashed to keep cache key manageable
calendar:general:2024:en:opts:a3f9c2e1
calendar:general:2024:en:yt:liturgical:opts:b7d4e9f2
```

**Cache Key Structure:**

- `calendar` - Fixed prefix
- `{type}` - Calendar type: 'general', 'nation', or 'diocese'
- `{id}` - Calendar ID (for national/diocesan calendars)
- `{year}` - Year or 'current'
- `{locale}` - Locale code (e.g., 'en', 'it', 'la')
- `yt:{year_type}` - Optional: year type parameter (if specified)
- `opts:{hash}` - Optional: 8-char MD5 hash of general calendar parameters (epiphany, ascension, corpus_christi, eternal_high_priest, holydays_of_obligation)

**Note:** National and diocesan calendars ignore optional parameters (they have predefined settings),
so the `opts` segment only appears for general calendar requests.

**Cache TTLs:**

Cache duration is based on the calendar year relative to the current UTC time:

- **Past years**: 30 days (2592000 seconds)
  - Liturgical data for past years is unchanging
  - Safe to cache for extended periods
- **Current year**: 24 hours (86400 seconds)
  - May be updated with new decrees during the year
  - Decrees won't have effect within the same day, so daily refresh is sufficient
- **Future years**: 7 days (604800 seconds)
  - Data may be refined as the year approaches
  - Weekly refresh balances freshness and performance

Note: All TTL calculations use UTC time to avoid timezone and DST edge cases,
ensuring consistent cache behavior regardless of server location or time changes.

**Implementation:**

```php
// Automatic caching in CalendarRequest
/**
 * Generate cache key for the calendar request.
 *
 * The cache key includes all parameters that affect the calendar output:
 * - Calendar type and ID (nation/diocese/general)
 * - Year and year_type (applies to all calendar types)
 * - Locale
 * - For general calendars: hash of optional parameters (epiphany, ascension, etc.)
 *
 * @return string Cache key
 */
private function getCacheKey(): string
{
    $parts = ['calendar'];

    // Calendar type and ID
    if ($this->calendarType && $this->calendarId) {
        $parts[] = $this->calendarType;
        $parts[] = $this->calendarId;
    } else {
        $parts[] = 'general';
    }

    // Year and locale
    $parts[] = $this->year ?? 'current';
    $parts[] = $this->locale ?? 'en';

    // year_type applies to all calendar types
    if ($this->yearType) {
        $parts[] = 'yt:' . $this->yearType;
    }

    // For general calendar, include optional parameters that affect output
    // These are ignored for national/diocesan calendars (they have predefined settings)
    if (!$this->calendarType) {
        $params = [];

        if ($this->epiphany !== null) {
            $params['epi'] = $this->epiphany;
        }
        if ($this->ascension !== null) {
            $params['asc'] = $this->ascension;
        }
        if ($this->corpusChristi !== null) {
            $params['cc'] = $this->corpusChristi;
        }
        if ($this->eternalHighPriest !== null) {
            $params['ehp'] = $this->eternalHighPriest ? '1' : '0';
        }
        if (!empty($this->holydaysOfObligation)) {
            // Sort for consistent hashing
            $holydays = $this->holydaysOfObligation;
            sort($holydays);
            $params['hdo'] = implode(',', $holydays);
        }

        // Hash the parameters to keep cache key manageable
        if (!empty($params)) {
            $parts[] = 'opts:' . substr(md5(json_encode($params)), 0, 8);
        }
    }

    return implode(':', $parts);
}

/**
 * Calculate cache TTL based on calendar year and current time.
 *
 * Uses UTC to avoid timezone/DST edge cases. Strategy:
 * - Past years: 30 days - liturgical data is unchanging
 * - Current year: 24 hours - decrees won't have effect within the same day
 * - Future years: 7 days - data may be refined as the year approaches
 *
 * Note: Uses UTC to ensure consistent behavior across timezones and
 * during DST transitions.
 *
 * @return int TTL in seconds
 */
private function getTtl(): int
{
    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    $currentYear = (int)$now->format('Y');

    // Past years are unchanging - cache for 30 days
    if ($this->year && $this->year < $currentYear) {
        return 2592000; // 30 days
    }

    // Current year - cache for 24 hours (decrees won't have effect within the same day)
    if (!$this->year || $this->year === $currentYear) {
        return 86400; // 24 hours
    }

    // Future years - cache for 7 days (data may be refined)
    return 604800; // 7 days
}
```

---

## Feature: Batch Request Support

### Batch Request Overview

Support fetching multiple calendars in a single operation.

### API Design

```php
use LiturgicalCalendar\Components\CalendarBatchRequest;

$batch = new CalendarBatchRequest($httpClient, $logger, $cache);

// Add multiple requests
$batch->add('us2024', fn($req) => $req->nation('US')->year(2024))
      ->add('it2024', fn($req) => $req->nation('IT')->year(2024))
      ->add('va2025', fn($req) => $req->nation('VA')->year(2025));

// Execute all requests (could be parallelized)
$results = $batch->execute();

// Access results
$usCalendar = $results['us2024'];
$itCalendar = $results['it2024'];
```

### Batch Request Benefits

- Fetch multiple calendars efficiently
- Parallel requests (if using async HTTP client)
- Single error handling point
- Batch caching optimization

---

## Additional Enhancements

### 1. Response Formatters

```php
// Export to different formats
$calendar = $request->year(2024)->get();

$icsExporter = new ICalendarExporter($calendar);
$icsFile = $icsExporter->export(); // .ics file for calendar apps

$pdfExporter = new PdfCalendarExporter($calendar);
$pdfFile = $pdfExporter->export(); // PDF calendar
```

### 2. Event Filtering

```php
// Filter events by criteria
$calendar = $request->year(2024)->get();

$solemnities = CalendarFilter::grade($calendar, 6, 7);
$sundaysOfAdvent = CalendarFilter::season($calendar, 'ADVENT')
    ->andGrade(6);
```

### 3. Comparison Tools

```php
// Compare calendars
$us2024 = CalendarRequest::nation('US')->year(2024)->get();
$it2024 = CalendarRequest::nation('IT')->year(2024)->get();

$comparison = CalendarComparison::compare($us2024, $it2024);
$differences = $comparison->getDifferences();
$commonEvents = $comparison->getCommonEvents();
```

---

## Implementation Timeline

### Phase 1: Core CalendarRequest (Week 1)

- [ ] Create `CalendarRequest` component
- [ ] Implement fluent API
- [ ] Add response validation
- [ ] Unit tests (20+ tests)
- [ ] PHPStan Level 10 validation
- [ ] Update examples to use CalendarRequest

### Phase 2: Response Models (Week 2)

- [ ] Create typed response models
- [ ] Add helper methods for common queries
- [ ] Add response filtering
- [ ] Unit tests for models

### Phase 3: Caching & Optimization (Week 3)

- [ ] Implement smart cache keys
- [ ] Add TTL logic based on year
- [ ] Add cache invalidation methods
- [ ] Performance benchmarking

### Phase 4: Advanced Features (Week 4)

- [ ] Batch request support
- [ ] Response formatters (iCal, PDF)
- [ ] Calendar comparison tools
- [ ] Documentation updates

### Phase 5: Polish & Release

- [ ] Update UPGRADE.md
- [ ] Create comprehensive examples
- [ ] Add to README
- [ ] Release notes

---

## Success Metrics

- [ ] Zero manual curl calls in examples
- [ ] All calendar requests use CalendarRequest component
- [ ] Cache hit rate > 70% on production sites
- [ ] PHPStan Level 10 maintained
- [ ] Test coverage > 85%
- [ ] Backward compatibility maintained

---

## Questions & Decisions

### 1. Should CalendarRequest extend a base Request class?

#### Option A: Standalone component

```php
class CalendarRequest { }
```

#### Option B: Extend base ApiRequest

```php
abstract class ApiRequest { }
class CalendarRequest extends ApiRequest { }
class MetadataRequest extends ApiRequest { }
```

**Recommendation**: Start with Option A (standalone), refactor to Option B if we add more request types.

### 2. Should we support async/parallel requests?

**Consideration**: Guzzle supports async requests, but adds complexity.

**Recommendation**: Start synchronous, add async in Phase 4 if needed.

### 3. Should responses be cached by default?

**Recommendation**: Yes, but only if cache is provided:

```php
// No cache provided - no caching
$req = new CalendarRequest();

// Cache provided - automatic caching
$req = new CalendarRequest($httpClient, $logger, $cache);
```

---

## Related Documentation

- **PSR_COMPATIBILITY.md** - PSR implementation details
- **UPGRADE.md** - Migration guide for PSR features
- **examples/webcalendar/index.php** - Current manual implementation (to be updated)

---

**Document Version**: 1.0
**Last Updated**: 2025-11-15
**Status**: Proposal - Awaiting Implementation
**Priority**: High

---

## Generated with Claude Code
