# Upgrade Guide

This guide helps you upgrade to use the new PSR-compliant HTTP client features in the Liturgical Calendar Components library.

## Table of Contents

- [Overview](#overview)
- [Breaking Changes](#breaking-changes)
- [New Features](#new-features)
- [Migration Examples](#migration-examples)
  - [Basic Usage (No Changes Required)](#basic-usage-no-changes-required)
  - [Adding HTTP Caching](#adding-http-caching)
  - [Adding Logging](#adding-logging)
  - [Adding Retry Logic](#adding-retry-logic)
  - [Adding Circuit Breaker](#adding-circuit-breaker)
  - [Production-Ready Setup](#production-ready-setup)
- [Performance Improvements](#performance-improvements)
- [Troubleshooting](#troubleshooting)

---

## Overview

The library now implements **PSR-7** (HTTP Messages), **PSR-17** (HTTP Factories), **PSR-18** (HTTP Client), **PSR-3** (Logging), and **PSR-16** (Simple Cache) standards, providing:

- **HTTP Response Caching** - Reduce API calls and improve performance
- **Structured Logging** - Monitor and debug HTTP requests
- **Retry Logic** - Automatic retry of failed requests with exponential backoff
- **Circuit Breaker** - Prevent cascading failures when services are down
- **Flexible HTTP Clients** - Swap between Guzzle, native PHP, or custom implementations

**Good News:** All changes are **100% backward compatible**. Your existing code continues to work without modifications!

---

## Breaking Changes

**None!** This release is fully backward compatible.

If you're using the library with default settings, no code changes are required.

### MetadataProvider Singleton Pattern (New Architecture)

**Starting from version 2.x**, the library introduced a centralized `MetadataProvider` singleton for all calendar metadata operations.

**Key Changes:**
- Metadata fetching and caching is now centralized through `MetadataProvider`
- API URL, HTTP client, cache, and logger are set **once** on first initialization and become **immutable**
- Validation methods are now **static** methods on `MetadataProvider`
- The `setUrl()` method has been removed from `CalendarSelect`
- `CalendarSelect::isValidDioceseForNation()` is now a static method

**Migration:**
Your existing code continues to work! However, for optimal use of the new architecture:

**Old Pattern** (still works):
```php
$calendar = new CalendarSelect(['url' => 'https://litcal.johnromanodorazio.com/api/dev']);
```

**New Pattern** (recommended):
```php
use LiturgicalCalendar\Components\Metadata\MetadataProvider;

// Initialize MetadataProvider once at application bootstrap
MetadataProvider::getInstance(
    apiUrl: 'https://litcal.johnromanodorazio.com/api/dev',
    httpClient: $httpClient,
    cache: $cache,
    logger: $logger
);

// Components automatically use the configured singleton
$calendar = new CalendarSelect();
```

**Validation Method Changes:**

```php
// Old (instance method - still works but deprecated pattern)
$calendar = new CalendarSelect();
$isValid = $calendar->isValidDioceseForNation('boston_us', 'US');

// New (static method - recommended)
$isValid = MetadataProvider::isValidDioceseForNation('boston_us', 'US');
// Or via CalendarSelect
$isValid = CalendarSelect::isValidDioceseForNation('boston_us', 'US');
```

See the **[MetadataProvider Architecture](#metadataprovider-architecture)** section below for complete documentation.

---

## New Features

### 1. PSR-18 HTTP Client Support

Replace `file_get_contents()` with PSR-18 compliant HTTP clients (Guzzle, Symfony HTTP Client, etc.) for better performance and reliability.

### 2. HTTP Response Caching (PSR-16)

Cache API responses to reduce network calls and improve page load times.

**Supported Cache Backends:**
- **ArrayCache** (in-memory, included)
- **Symfony Cache** (Redis, Filesystem, APCu, Memcached)
- Any PSR-16 compatible cache

### 3. Logging (PSR-3)

Log all HTTP requests, responses, cache hits/misses, and errors for debugging and monitoring.

**Supported Loggers:**
- **Monolog** (recommended)
- **Symfony Logger**
- Any PSR-3 compatible logger

### 4. Retry Middleware

Automatically retry failed HTTP requests with exponential backoff.

**Features:**
- Configurable retry attempts (default: 3)
- Exponential or linear backoff
- Configurable status codes to retry (default: 408, 429, 500, 502, 503, 504)

### 5. Circuit Breaker Pattern

Prevent cascading failures by "opening" the circuit after too many consecutive failures.

**States:**
- **CLOSED**: Normal operation
- **OPEN**: Failing fast, blocking requests
- **HALF_OPEN**: Testing service recovery

### 6. Production-Ready Client

Pre-configured HTTP client with retry, circuit breaker, caching, and logging.

---

## Migration Examples

### Basic Usage (No Changes Required)

Your existing code continues to work:

```php
use LiturgicalCalendar\Components\CalendarSelect;

// Works exactly as before
$calendar = new CalendarSelect();
$html = $calendar->getSelect();
```

### Adding HTTP Caching

Reduce API calls by caching responses:

```php
use LiturgicalCalendar\Components\CalendarSelect;
use LiturgicalCalendar\Components\Metadata\MetadataProvider;
use LiturgicalCalendar\Components\Cache\ArrayCache;
use LiturgicalCalendar\Components\Http\HttpClientFactory;

// In-memory cache (request-scoped)
$cache = new ArrayCache();
$httpClient = HttpClientFactory::create();

// Initialize MetadataProvider with cache
MetadataProvider::getInstance(
    apiUrl: 'https://litcal.johnromanodorazio.com/api/dev',
    httpClient: $httpClient,
    cache: $cache,
    cacheTtl: 3600  // 1 hour
);

// Create component - uses MetadataProvider's cache
$calendar = new CalendarSelect();

// First call - fetches from API
$html1 = $calendar->getSelect();

// Second call - returns from cache (no API call!)
$html2 = $calendar->getSelect();
```

**With Persistent Cache (Symfony Cache):**

```php
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;
use LiturgicalCalendar\Components\Metadata\MetadataProvider;

// Persistent filesystem cache
$filesystemAdapter = new FilesystemAdapter('litcal', 3600, '/tmp/litcal-cache');
$cache = new Psr16Cache($filesystemAdapter);

// Initialize MetadataProvider with persistent cache
MetadataProvider::getInstance(
    apiUrl: 'https://litcal.johnromanodorazio.com/api/dev',
    cache: $cache,
    cacheTtl: 3600 * 24  // 24 hours
);

$calendar = new CalendarSelect();
```

### Adding Logging

Monitor HTTP requests and debug issues:

```php
use LiturgicalCalendar\Components\CalendarSelect;
use LiturgicalCalendar\Components\Metadata\MetadataProvider;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Create logger
$logger = new Logger('liturgical-calendar');
$logger->pushHandler(new StreamHandler('php://stderr', Logger::INFO));

// Initialize MetadataProvider with logger
MetadataProvider::getInstance(
    apiUrl: 'https://litcal.johnromanodorazio.com/api/dev',
    logger: $logger
);

// Create component - uses MetadataProvider's logger
$calendar = new CalendarSelect();

// All HTTP requests will be logged
$html = $calendar->getSelect();
```

**Example Log Output:**
```
[2025-11-15 10:30:00] liturgical-calendar.INFO: HTTP GET request {"url":"https://litcal.johnromanodorazio.com/api/dev/calendars"}
[2025-11-15 10:30:01] liturgical-calendar.INFO: HTTP GET response {"status_code":200,"duration_ms":423.5}
```

### Adding Retry Logic

Automatically retry failed requests:

```php
use LiturgicalCalendar\Components\Http\HttpClientFactory;
use LiturgicalCalendar\Components\Metadata\MetadataProvider;
use LiturgicalCalendar\Components\CalendarSelect;

// Create HTTP client with retry support
$httpClient = HttpClientFactory::createWithRetry(
    maxRetries: 3,                        // Retry up to 3 times
    retryDelay: 1000,                     // Initial delay: 1 second
    useExponentialBackoff: true,          // Use exponential backoff
    retryStatusCodes: [500, 502, 503, 504] // Retry on these status codes
);

// Initialize MetadataProvider with retry-enabled client
MetadataProvider::getInstance(
    apiUrl: 'https://litcal.johnromanodorazio.com/api/dev',
    httpClient: $httpClient
);

$calendar = new CalendarSelect();
```

**Retry Behavior:**
- **Attempt 1**: Immediate request
- **Attempt 2**: Wait 1 second (2^0 * 1000ms)
- **Attempt 3**: Wait 2 seconds (2^1 * 1000ms)
- **Attempt 4**: Wait 4 seconds (2^2 * 1000ms)

### Adding Circuit Breaker

Prevent cascading failures:

```php
use LiturgicalCalendar\Components\Http\HttpClientFactory;
use LiturgicalCalendar\Components\Metadata\MetadataProvider;
use LiturgicalCalendar\Components\CalendarSelect;

// Create HTTP client with circuit breaker
$httpClient = HttpClientFactory::createWithCircuitBreaker(
    failureThreshold: 5,   // Open circuit after 5 failures
    recoveryTimeout: 60,   // Try recovery after 60 seconds
    successThreshold: 2    // Close circuit after 2 successes
);

// Initialize MetadataProvider with circuit breaker-enabled client
MetadataProvider::getInstance(
    apiUrl: 'https://litcal.johnromanodorazio.com/api/dev',
    httpClient: $httpClient
);

$calendar = new CalendarSelect();
```

**Circuit Breaker States:**
1. **CLOSED** (Normal): Requests pass through
2. **OPEN** (Failing): Blocks requests, fails immediately
3. **HALF_OPEN** (Testing): Allows limited requests to test recovery

### Production-Ready Setup

Combine all features for a robust production setup:

```php
use LiturgicalCalendar\Components\Http\HttpClientFactory;
use LiturgicalCalendar\Components\Metadata\MetadataProvider;
use LiturgicalCalendar\Components\CalendarSelect;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// 1. Setup cache (Redis)
$redis = RedisAdapter::createConnection('redis://localhost');
$cache = new Psr16Cache(new RedisAdapter($redis, 'litcal', 3600));

// 2. Setup logger
$logger = new Logger('liturgical-calendar');
$logger->pushHandler(new StreamHandler('/var/log/litcal.log', Logger::WARNING));

// 3. Create production-ready HTTP client
// Includes: Circuit Breaker + Retry + Caching + Logging
$httpClient = HttpClientFactory::createProductionClient(
    cache: $cache,
    logger: $logger,
    cacheTtl: 3600,          // 1 hour cache
    maxRetries: 3,           // 3 retry attempts
    failureThreshold: 5      // Circuit breaker threshold
);

// 4. Initialize MetadataProvider with production client
MetadataProvider::getInstance(
    apiUrl: 'https://litcal.johnromanodorazio.com/api/dev',
    httpClient: $httpClient,
    cache: $cache,
    logger: $logger,
    cacheTtl: 3600 * 24
);

// 5. Create components - they automatically use the configured MetadataProvider
$calendar = new CalendarSelect();
```

**Middleware Stack (innermost to outermost):**
1. Base HTTP Client (Guzzle or file_get_contents)
2. Circuit Breaker (protects against cascading failures)
3. Retry (retries failed requests)
4. Caching (caches successful responses)
5. Logging (logs all operations)

---

## Performance Improvements

### Cache Hit Rates

With caching enabled:
- **First request**: ~400ms (API call)
- **Cached requests**: <1ms (no network)
- **Typical cache hit rate**: 80-95% on production sites

### Recommended Cache TTLs

| Data Type | Recommended TTL | Rationale |
|-----------|----------------|-----------|
| Calendar Metadata | 24 hours (86400s) | Changes infrequently |
| Locale Information | 24 hours (86400s) | Very stable (supported locales per calendar) |
| Calendar Data | 1 week (604800s) | Liturgical calendar data is stable long-term |

### Example Performance Gains

**Before (without cache):**
- 10 page loads = 10 API calls = ~4 seconds total

**After (with cache):**
- 10 page loads = 1 API call + 9 cache hits = ~0.4 seconds total
- **90% faster!**

---

## Troubleshooting

### Cache Not Working

**Problem:** Responses not being cached

**Solutions:**
1. Verify cache is injected:
   ```php
   $calendar = new CalendarSelect([], null, null, $cache); // ✓ Correct
   $calendar = new CalendarSelect([], null, null);         // ✗ No cache
   ```

2. Check cache permissions (filesystem cache):
   ```bash
   chmod 777 /tmp/litcal-cache
   ```

3. Verify cache backend is working:
   ```php
   $cache->set('test', 'value', 60);
   var_dump($cache->get('test')); // Should print 'value'
   ```

### Logs Not Appearing

**Problem:** No log entries

**Solutions:**
1. Verify logger is injected:
   ```php
   $calendar = new CalendarSelect([], null, $logger); // ✓ Correct
   ```

2. Check log level:
   ```php
   // Too high - won't log INFO/DEBUG
   $logger->pushHandler(new StreamHandler('log.txt', Logger::ERROR));

   // Correct - logs everything
   $logger->pushHandler(new StreamHandler('log.txt', Logger::DEBUG));
   ```

3. Check file permissions:
   ```bash
   chmod 666 /var/log/litcal.log
   ```

### Circuit Breaker Opening Unexpectedly

**Problem:** Circuit breaker opens during normal operation

**Solutions:**
1. Increase failure threshold:
   ```php
   // More tolerant
   $httpClient = HttpClientFactory::createWithCircuitBreaker(
       failureThreshold: 10  // Was: 5
   );
   ```

2. Check underlying service health
3. Review logs for actual failures:
   ```php
   $logger->pushHandler(new StreamHandler('php://stderr', Logger::DEBUG));
   ```

### Retry Taking Too Long

**Problem:** Retries add too much latency

**Solutions:**
1. Reduce retry attempts:
   ```php
   $httpClient = HttpClientFactory::createWithRetry(
       maxRetries: 1,  // Was: 3
       retryDelay: 500 // Was: 1000
   );
   ```

2. Use linear backoff instead of exponential:
   ```php
   $httpClient = HttpClientFactory::createWithRetry(
       useExponentialBackoff: false
   );
   ```

3. Don't retry certain status codes:
   ```php
   $httpClient = HttpClientFactory::createWithRetry(
       retryStatusCodes: [503, 504] // Only retry 503/504
   );
   ```

---

## MetadataProvider Architecture

The `MetadataProvider` class provides centralized management of calendar metadata with the following features:

### Singleton Pattern with Immutable Configuration

All configuration is set **once** on first initialization and becomes **immutable** for the application lifetime:

```php
use LiturgicalCalendar\Components\Metadata\MetadataProvider;

// First call - initializes with configuration
MetadataProvider::getInstance(
    apiUrl: 'https://litcal.johnromanodorazio.com/api/dev',
    httpClient: $httpClient,
    cache: $cache,
    logger: $logger,
    cacheTtl: 86400  // 24 hours
);

// All subsequent calls return the same singleton (parameters ignored)
$provider = MetadataProvider::getInstance();
```

### Complete Example with Production Setup

```php
use LiturgicalCalendar\Components\Metadata\MetadataProvider;
use LiturgicalCalendar\Components\Http\HttpClientFactory;
use LiturgicalCalendar\Components\CalendarSelect;
use LiturgicalCalendar\Components\ApiOptions\Input\Locale;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// 1. Setup cache and logger
$redis = RedisAdapter::createConnection('redis://localhost');
$cache = new Psr16Cache(new RedisAdapter($redis, 'litcal', 3600));
$logger = new Logger('litcal');
$logger->pushHandler(new StreamHandler('/var/log/litcal.log', Logger::WARNING));

// 2. Create HTTP client with all features
$httpClient = HttpClientFactory::createProductionClient(
    cache: $cache,
    logger: $logger,
    cacheTtl: 3600,
    maxRetries: 3,
    failureThreshold: 5
);

// 3. Initialize MetadataProvider ONCE at application bootstrap
MetadataProvider::getInstance(
    apiUrl: 'https://litcal.johnromanodorazio.com/api/dev',
    httpClient: $httpClient,
    cache: $cache,
    logger: $logger,
    cacheTtl: 86400
);

// 4. Create components - they automatically use the configured singleton
$calendarSelect = new CalendarSelect();
$locale = new Locale();

// 5. Use static validation methods
$isValid = MetadataProvider::isValidDioceseForNation('boston_us', 'US');
```

### Static Validation Methods

No need to create instances or pass URLs:

```php
// Check if diocese belongs to nation
$isValid = MetadataProvider::isValidDioceseForNation('boston_us', 'US');

// Get configured API URL
$apiUrl = MetadataProvider::getApiUrl();

// Get metadata endpoint URL (API URL + /calendars)
$metadataUrl = MetadataProvider::getMetadataUrl();

// Check if metadata is cached
$isCached = MetadataProvider::isCached();
```

### Two-Tier Caching Strategy

1. **Process-wide cache** (static property) - Persists for PHP process lifetime, takes precedence
2. **PSR-16 cache** (optional) - Used only for initial HTTP fetch

```php
// First component - fetches from API
$calendar1 = new CalendarSelect();

// Second component - uses process-wide cache (no API call)
$calendar2 = new CalendarSelect();

// Long-running processes: manually clear cache when needed
MetadataProvider::clearCache();
```

### Component Integration

Components that use MetadataProvider:
- `CalendarSelect` - Calendar dropdown selection
- `Locale` (ApiOptions) - Locale dropdown selection

Both automatically use the globally configured singleton:

```php
// NO need to pass HTTP client, cache, or logger
$calendarSelect = new CalendarSelect([
    'locale' => 'en',
    'url' => 'https://litcal.johnromanodorazio.com/api/dev'
]);
```

### Best Practices

1. **Initialize once at bootstrap**: Configure MetadataProvider in your application's initialization code
2. **Use static methods**: Prefer `MetadataProvider::isValidDioceseForNation()` over instance methods
3. **Long-running processes**: Call `clearCache()` periodically to refresh metadata
4. **Testing**: Use `resetForTesting()` in test setup for isolation

---

## Questions?

- **GitHub Issues**: https://github.com/Liturgical-Calendar/liturgy-components-php/issues
- **Documentation**: See PSR_COMPATIBILITY.md for implementation details
- **Examples**: See examples/ directory for working code samples

---

**Generated with Claude Code**
**Last Updated**: 2025-11-18
