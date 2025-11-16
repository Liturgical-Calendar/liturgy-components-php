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
2. **Fluent API**: Chainable methods for building requests
3. **Type-Safe**: Full PHPStan Level 10 compliance
4. **Cacheable**: Automatic caching of responses
5. **Observable**: Integrated logging
6. **Reliable**: Built-in retry and circuit breaker support
7. **Validated**: Automatic response validation

### Important API Parameter Restrictions

**Calendar-Specific Settings** - The following parameters are **only applicable to General Roman Calendar** requests:
- `epiphany` - Epiphany celebration setting
- `ascension` - Ascension celebration setting
- `corpus_christi` - Corpus Christi celebration setting
- `eternal_high_priest` - Eternal High Priest feast setting
- `holydays_of_obligation` - Holy days of obligation configuration

**Why?** National and diocesan calendars have these liturgical settings **predefined by the calendar itself** based on local church regulations and conferences of bishops. These parameters are ignored by the API when requesting national (`/calendar/nation/{id}`) or diocesan (`/calendar/diocese/{id}`) calendars.

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

**Important**: The `epiphany()`, `ascension()`, `corpusChristi()`, `eternalHighPriest()`, and `holydaysOfObligation()` methods are **only applicable to General Roman Calendar** requests (bare `/calendar` or `/calendar/{year}` paths). National and diocesan calendars have these settings predefined and will ignore these parameters if sent.

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
     */
    public function header(string $name, string $value): self
    {
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
            $calendar = json_decode($responseBody);

            if (JSON_ERROR_NONE !== json_last_error()) {
                throw new \Exception(
                    "Invalid JSON response: " . json_last_error_msg()
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
     */
    private function buildUrl(): string
    {
        $path = '/calendar';

        if ($this->calendarType && $this->calendarId) {
            $path .= "/{$this->calendarType}/{$this->calendarId}";
        }

        if ($this->year) {
            $path .= "/{$this->year}";
        }

        return $this->baseUrl . $path;
    }

    /**
     * Build request headers
     */
    private function buildHeaders(): array
    {
        $headers = [
            'Accept' => 'application/json',
        ];

        if ($this->locale) {
            $headers['Accept-Language'] = $this->locale;
        }

        if ($this->returnType) {
            $headers['Accept'] = match($this->returnType) {
                'xml' => 'application/xml',
                'yaml' => 'application/yaml',
                'ical' => 'text/calendar',
                default => 'application/json',
            };
        }

        return array_merge($headers, $this->customHeaders);
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

/**
 * Builder for creating CalendarRequest instances with common configurations
 */
class CalendarResponseBuilder
{
    /**
     * Quick request for General Roman Calendar
     */
    public static function generalCalendar(
        int $year,
        string $locale = 'en',
        ?HttpClientInterface $httpClient = null
    ): \stdClass {
        return (new CalendarRequest($httpClient))
            ->year($year)
            ->locale($locale)
            ->get();
    }

    /**
     * Quick request for National Calendar
     */
    public static function nationalCalendar(
        string $nation,
        int $year,
        string $locale = 'en',
        ?HttpClientInterface $httpClient = null
    ): \stdClass {
        return (new CalendarRequest($httpClient))
            ->nation($nation)
            ->year($year)
            ->locale($locale)
            ->get();
    }

    /**
     * Quick request for Diocesan Calendar
     */
    public static function diocesanCalendar(
        string $diocese,
        int $year,
        string $locale = 'en',
        ?HttpClientInterface $httpClient = null
    ): \stdClass {
        return (new CalendarRequest($httpClient))
            ->diocese($diocese)
            ->year($year)
            ->locale($locale)
            ->get();
    }
}
```

### Usage Examples

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
$calendar = CalendarResponseBuilder::generalCalendar(2024, 'en', $httpClient);
```

### Benefits

1. **✅ PSR-Compliant**: Uses existing HTTP client infrastructure
2. **✅ Cached**: Automatic response caching (if cache provided)
3. **✅ Logged**: All requests logged (if logger provided)
4. **✅ Resilient**: Built-in retry and circuit breaker
5. **✅ Validated**: Automatic response validation
6. **✅ Type-Safe**: Full PHPStan compliance
7. **✅ Fluent**: Easy-to-use chainable API
8. **✅ Testable**: Easy to mock for unit tests

---

## Feature: Calendar Response Caching

### Overview

Extend caching to include calendar data responses, not just metadata.

### Cache Strategy

**Cache Keys:**
```
calendar:general:2024:en
calendar:nation:US:2024:en
calendar:diocese:DIOCESE001:2025:la
```

**Cache TTLs:**
- General Calendar: 24 hours (stable)
- National Calendar: 12 hours (occasional updates)
- Diocesan Calendar: 6 hours (more frequent updates)
- Current year: 1 hour (may change during year)
- Past years: 7 days (unchanging)

**Implementation:**
```php
// Automatic caching in CalendarRequest
private function getCacheKey(): string
{
    $parts = ['calendar'];

    if ($this->calendarType && $this->calendarId) {
        $parts[] = $this->calendarType;
        $parts[] = $this->calendarId;
    } else {
        $parts[] = 'general';
    }

    $parts[] = $this->year ?? 'current';
    $parts[] = $this->locale ?? 'en';

    return implode(':', $parts);
}

private function getTtl(): int
{
    // Past years are unchanging - cache longer
    if ($this->year && $this->year < date('Y')) {
        return 604800; // 7 days
    }

    // Current year - cache shorter
    if (!$this->year || $this->year === (int)date('Y')) {
        return 3600; // 1 hour
    }

    // Future years - medium cache
    return 43200; // 12 hours
}
```

---

## Feature: Batch Request Support

### Overview

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

### Benefits

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

**Option A**: Standalone component
```php
class CalendarRequest { }
```

**Option B**: Extend base ApiRequest
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

**Generated with Claude Code**
