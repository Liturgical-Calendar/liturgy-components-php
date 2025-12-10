# PSR Compatibility Implementation Game Plan

## Executive Summary

This document outlines the strategy for implementing PSR-compliant HTTP client functionality in the liturgy-components-php library,
replacing current `file_get_contents()` usage with PSR-7, PSR-17, and PSR-18 standards.

## Current State Analysis

### HTTP Usage Locations

1. **src/CalendarSelect.php** (line 419)
   - Fetches calendar metadata from API
   - Single GET request to metadata URL
   - Error handling: throws exception on failure

1. **src/ApiOptions/Input/Locale.php** (line 126)
   - Fetches locale information from API
   - Single GET request to `/calendars` endpoint
   - Error handling: throws exception on failure

### Current Implementation Limitations

- Uses native `file_get_contents()` - blocking, no timeout control
- No retry logic or error recovery
- Limited HTTP configuration options
- No middleware support (logging, caching, etc.)
- Difficult to mock for testing
- No connection pooling or async capabilities

---

## PSR Standards Overview

### PSR-7: HTTP Message Interfaces

Defines interfaces for HTTP messages (requests/responses), URIs, and streams.

**Key Interfaces:**

- `RequestInterface` - HTTP request
- `ResponseInterface` - HTTP response
- `UriInterface` - URI/URL representation
- `StreamInterface` - Message body stream

### PSR-17: HTTP Factories

Defines factory interfaces for creating PSR-7 objects.

**Key Interfaces:**

- `RequestFactoryInterface` - Creates PSR-7 requests
- `ResponseFactoryInterface` - Creates PSR-7 responses
- `StreamFactoryInterface` - Creates PSR-7 streams
- `UriFactoryInterface` - Creates PSR-7 URIs

### PSR-18: HTTP Client

Defines interface for sending PSR-7 requests and returning PSR-7 responses.

**Key Interface:**

- `ClientInterface::sendRequest(RequestInterface): ResponseInterface`

---

## PSR Standards Implementation Overview

This section provides a comprehensive view of all PSR standards we'll implement:

| PSR        | Standard                 | Purpose                   | Priority | Complexity |
|------------|--------------------------|---------------------------|----------|------------|
| **PSR-7**  | HTTP Message Interfaces  | Request/Response objects  | High     | Medium     |
| **PSR-17** | HTTP Factories           | Create PSR-7 objects      | High     | Low        |
| **PSR-18** | HTTP Client              | Send HTTP requests        | High     | Medium     |
| **PSR-3**  | Logger Interface         | Structured logging        | Medium   | Low        |
| **PSR-6**  | Caching Interface        | Cache pool abstraction    | Low      | Medium     |
| **PSR-16** | Simple Cache             | Simplified cache interface| Low      | Low        |

---

## Recommended Dependencies

### 1. PSR-7 & PSR-17 Implementation: `nyholm/psr7`

**Why Nyholm PSR-7:**

- ✅ User preference
- ✅ Lightweight (no dependencies)
- ✅ Implements both PSR-7 and PSR-17
- ✅ Strict type safety
- ✅ Excellent PHPStan level 10 compatibility
- ✅ Well-maintained, active development
- ✅ Performance-optimized

**Installation:**

```bash
composer require nyholm/psr7
```text
### 2. PSR-18 HTTP Client: **Decision Required**

#### Option A: Guzzle 7+ (Recommended)

```bash
composer require guzzlehttp/guzzle:^7.0
composer require guzzlehttp/psr7  # For Guzzle's PSR-7 implementation
```text
**Pros:**

- Industry standard, most popular PHP HTTP client
- Feature-rich (middleware, async, streaming, etc.)
- Excellent documentation and community support
- Built-in retry logic and error handling
- Connection pooling
- Works seamlessly with Nyholm PSR-7

**Cons:**

- Larger dependency footprint
- More features than needed for simple GET requests

#### Option B: Symfony HTTP Client

```bash
composer require symfony/http-client
composer require nyholm/psr7  # Already included above
```text
**Pros:**

- Part of Symfony ecosystem
- PSR-18 compliant
- Native async support
- Good performance
- Scoped clients for configuration isolation

**Cons:**

- Symfony dependency (though standalone package)
- Less popular than Guzzle

#### Option C: php-http/curl-client (HTTPlug)

```bash
composer require php-http/curl-client
composer require nyholm/psr7
```text
**Pros:**

- Lightweight, minimal dependencies
- Uses cURL extension (widely available)
- Part of HTTPlug ecosystem
- Good for simple use cases

**Cons:**

- Requires cURL extension
- Less feature-rich than Guzzle
- HTTPlug 2 is primarily an abstraction layer

#### Option D: PHP-HTTP Discovery + Adapter Pattern

```bash
composer require php-http/discovery
composer require psr/http-client-implementation  # Virtual package
```text
**Pros:**

- Client-agnostic approach
- Auto-discovers available HTTP client
- Maximum flexibility for library users

**Cons:**

- Requires users to install their own HTTP client
- More complex setup

### Recommendation: **Guzzle 7+ (Option A)**

**Rationale:**

- Most battle-tested and reliable
- Best error handling out of the box
- Middleware ecosystem for future extensibility (caching, logging, retry)
- Can work with Nyholm PSR-7 via adapter
- Users can still swap implementations via dependency injection

---

### 3. PSR-3 Logging Implementation: **Decision Required**

#### Option A: Monolog (Recommended)

```bash
composer require monolog/monolog:^3.0
```text
**Pros:**

- Industry standard for PHP logging
- PSR-3 compliant
- Multiple handlers (file, syslog, email, Slack, etc.)
- Processors for context enrichment
- Formatters for structured output (JSON, line, etc.)
- Excellent integration with Guzzle middleware
- PHPStan level 10 compatible

**Cons:**

- Larger dependency footprint
- More features than needed for basic logging

**Configuration Example:**

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Processor\IntrospectionProcessor;

$logger = new Logger('liturgical-calendar');
$logger->pushHandler(new StreamHandler('php://stderr', Logger::WARNING));
$logger->pushHandler(new RotatingFileHandler('/var/log/litcal/app.log', 7, Logger::DEBUG));
$logger->pushProcessor(new IntrospectionProcessor());
```text
#### Option B: Symfony Logger

```bash
composer require symfony/monolog-bundle  # Or standalone
```text
**Pros:**

- Part of Symfony ecosystem
- PSR-3 compliant
- Good integration with Symfony components
- Flexible configuration

**Cons:**

- Symfony dependency
- More complex setup outside Symfony apps

#### Option C: No Logging (PSR-3 Interface Only)

```bash
composer require psr/log:^3.0
```text
**Pros:**

- Minimal dependency
- Users can inject their own logger
- Library stays lightweight

**Cons:**

- No out-of-the-box logging
- Requires users to provide implementation

#### Option D: PSR-3 NullLogger (Development Placeholder)

```php
use Psr\Log\NullLogger;

// Default fallback
$logger = $logger ?? new NullLogger();
```text
**Pros:**

- Built into PSR-3 package
- No-op implementation (silent)
- Safe default when no logger configured

**Cons:**

- No actual logging happens

### Recommendation: **Monolog (Option A)** + **NullLogger Fallback**

**Rationale:**

- Monolog as suggested dependency in composer.json
- NullLogger as default when not installed
- Maximum flexibility for library users
- Professional logging capabilities when needed
- Easy integration with HTTP client middleware

---

### 4. PSR-6/PSR-16 Caching Implementation: **Decision Required**

#### Option A: Symfony Cache (Recommended)

```bash
composer require symfony/cache:^6.0 || ^7.0
```text
**Pros:**

- Implements both PSR-6 (CacheItemPoolInterface) and PSR-16 (CacheInterface)
- Multiple adapters: APCu, Redis, Memcached, Filesystem, PDO, etc.
- Chain adapter for multi-level caching
- Tag-based invalidation
- Excellent performance
- PHPStan compatible
- Well-documented

**Cons:**

- Symfony dependency
- Larger package

**Supported Backends:**

- **APCu**: In-memory, PHP process level, very fast
- **Redis**: Distributed, persistent, recommended for production
- **Filesystem**: File-based, no extensions required, good for dev
- **Array**: In-memory, request-scoped, testing
- **ChainAdapter**: Fallback chain (e.g., APCu → Redis → Filesystem)

**Configuration Example:**

```php
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Adapter\ApcuAdapter;

// Development: Filesystem cache
$cache = new FilesystemAdapter('litcal', 3600, '/tmp/litcal-cache');

// Production: Multi-level cache (APCu → Redis → File)
$redis = RedisAdapter::createConnection('redis://localhost');
$cache = new ChainAdapter([
    new ApcuAdapter('litcal', 3600),
    new RedisAdapter($redis, 'litcal', 7200),
    new FilesystemAdapter('litcal', 86400)
]);
```text
#### Option B: PSR-6/PSR-16 Simple Implementations

**For PSR-6:**

```bash
composer require cache/filesystem-adapter
composer require cache/redis-adapter
composer require cache/apcu-adapter
```text
**For PSR-16:**

```bash
composer require symfony/cache  # Includes PSR-16 SimpleCache
```text
**Pros:**

- Lightweight, focused packages
- PSR-6 compliant
- Multiple backend options

**Cons:**

- Less feature-rich than Symfony Cache
- Requires separate packages per adapter
- May have different APIs across adapters

#### Option C: Custom In-Memory Cache (Minimal)

```php
namespace LiturgicalCalendar\Components\Cache;

use Psr\SimpleCache\CacheInterface;

class ArrayCache implements CacheInterface
{
    private array $cache = [];

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->cache[$key] ?? $default;
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $this->cache[$key] = $value;
        return true;
    }

    // ... implement other PSR-16 methods
}
```text
**Pros:**

- No dependencies
- Simple implementation
- Good for testing

**Cons:**

- Request-scoped only (no persistence)
- No advanced features
- Not suitable for production caching

#### Option D: No Caching (Interface Only)

```bash
composer require psr/simple-cache:^3.0
composer require psr/cache:^3.0
```text
**Pros:**

- Minimal dependencies
- Users provide their own cache implementation
- Library stays lightweight

**Cons:**

- No out-of-the-box caching
- Requires user configuration

### Recommendation: **Symfony Cache (Option A)** + **Array Cache Fallback**

**Rationale:**

- Symfony Cache as suggested dependency
- Implements both PSR-6 and PSR-16
- Multiple production-ready adapters
- In-memory ArrayCache as default fallback
- Easy to configure different backends per environment
- Excellent for HTTP response caching

---

## Implementation Strategy

### Overview of Implementation Phases

| Phase | Focus | Duration | Dependencies |
|-------|-------|----------|--------------|
| **Phase 1** | HTTP Client Abstraction | Week 1 | PSR-7, PSR-17, PSR-18 |
| **Phase 2** | Dependency Injection | Week 1 | - |
| **Phase 3** | Code Refactoring | Week 2 | Phase 1, 2 |
| **Phase 4** | Testing | Week 2 | Phase 3 |
| **Phase 5** | Logging Integration | Week 3 | PSR-3 |
| **Phase 6** | Caching Integration | Week 3-4 | PSR-6, PSR-16 |
| **Phase 7** | Middleware & Polish | Week 4 | All phases |

---

### Phase 1: HTTP Client Abstraction (Week 1)

#### 1.1 Create HTTP Client Service Interface

**Location:** `src/Http/HttpClientInterface.php`

```php
<?php
namespace LiturgicalCalendar\Components\Http;

use Psr\Http\Message\ResponseInterface;

interface HttpClientInterface
{
    /**
     * Perform HTTP GET request
     *
     * @param string $url The URL to fetch
     * @param array<string,string> $headers Optional request headers
     * @return ResponseInterface
     * @throws \LiturgicalCalendar\Components\Http\HttpException
     */
    public function get(string $url, array $headers = []): ResponseInterface;

    /**
     * Perform HTTP POST request
     *
     * @param string $url The URL to post to
     * @param array<string,mixed>|string $body Request body
     * @param array<string,string> $headers Optional request headers
     * @return ResponseInterface
     * @throws \LiturgicalCalendar\Components\Http\HttpException
     */
    public function post(string $url, array|string $body, array $headers = []): ResponseInterface;
}
```text
#### 1.2 Create PSR-18 Implementation

**Location:** `src/Http/PsrHttpClient.php`

```php
<?php
namespace LiturgicalCalendar\Components\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\ResponseInterface;

class PsrHttpClient implements HttpClientInterface
{
    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory
    ) {}

    public function get(string $url, array $headers = []): ResponseInterface
    {
        $request = $this->requestFactory->createRequest('GET', $url);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        try {
            return $this->httpClient->sendRequest($request);
        } catch (\Psr\Http\Client\ClientExceptionInterface $e) {
            throw new HttpException(
                "HTTP request failed: {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        }
    }

    public function post(string $url, array|string $body, array $headers = []): ResponseInterface
    {
        $request = $this->requestFactory->createRequest('POST', $url);

        $bodyContent = is_array($body) ? json_encode($body) : $body;
        $stream = $this->streamFactory->createStream($bodyContent);
        $request = $request->withBody($stream);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        try {
            return $this->httpClient->sendRequest($request);
        } catch (\Psr\Http\Client\ClientExceptionInterface $e) {
            throw new HttpException(
                "HTTP request failed: {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        }
    }
}
```text
#### 1.3 Create Custom Exception

**Location:** `src/Http/HttpException.php`

```php
<?php
namespace LiturgicalCalendar\Components\Http;

class HttpException extends \Exception
{
}
```text
#### 1.4 Create Legacy Adapter (Backward Compatibility)

**Location:** `src/Http/FileGetContentsClient.php`

```php
<?php
namespace LiturgicalCalendar\Components\Http;

use Psr\Http\Message\ResponseInterface;
use Nyholm\Psr7\Response;

/**
 * Legacy HTTP client using file_get_contents
 * For backward compatibility when PSR implementations are not available
 */
class FileGetContentsClient implements HttpClientInterface
{
    public function get(string $url, array $headers = []): ResponseInterface
    {
        $context = $this->createContext('GET', $headers);

        $content = @file_get_contents($url, false, $context);

        if ($content === false) {
            throw new HttpException("Failed to fetch URL: {$url}");
        }

        // Parse response headers from $http_response_header
        $statusCode = $this->parseStatusCode($http_response_header ?? []);
        $responseHeaders = $this->parseHeaders($http_response_header ?? []);

        return new Response($statusCode, $responseHeaders, $content);
    }

    public function post(string $url, array|string $body, array $headers = []): ResponseInterface
    {
        $bodyContent = is_array($body) ? json_encode($body) : $body;
        $context = $this->createContext('POST', $headers, $bodyContent);

        $content = @file_get_contents($url, false, $context);

        if ($content === false) {
            throw new HttpException("Failed to post to URL: {$url}");
        }

        $statusCode = $this->parseStatusCode($http_response_header ?? []);
        $responseHeaders = $this->parseHeaders($http_response_header ?? []);

        return new Response($statusCode, $responseHeaders, $content);
    }

    private function createContext(string $method, array $headers, ?string $body = null)
    {
        $options = [
            'http' => [
                'method' => $method,
                'header' => $this->formatHeaders($headers),
                'ignore_errors' => true,
            ]
        ];

        if ($body !== null) {
            $options['http']['content'] = $body;
        }

        return stream_context_create($options);
    }

    private function formatHeaders(array $headers): string
    {
        $formatted = [];
        foreach ($headers as $name => $value) {
            $formatted[] = "{$name}: {$value}";
        }
        return implode("\r\n", $formatted);
    }

    private function parseStatusCode(array $headers): int
    {
        if (empty($headers)) {
            return 200;
        }

        $statusLine = $headers[0];
        if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $statusLine, $matches)) {
            return (int) $matches[1];
        }

        return 200;
    }

    private function parseHeaders(array $headers): array
    {
        $parsed = [];
        foreach (array_slice($headers, 1) as $header) {
            if (str_contains($header, ':')) {
                [$name, $value] = explode(':', $header, 2);
                $parsed[trim($name)] = trim($value);
            }
        }
        return $parsed;
    }
}
```text
### Phase 2: Dependency Injection Setup (Week 1)

#### 2.1 Create HTTP Client Factory

**Location:** `src/Http/HttpClientFactory.php`

```php
<?php
namespace LiturgicalCalendar\Components\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

class HttpClientFactory
{
    /**
     * Create HTTP client with PSR implementations if available,
     * fallback to file_get_contents otherwise
     */
    public static function create(
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null
    ): HttpClientInterface {
        // If all PSR dependencies are provided, use PSR client
        if ($httpClient !== null && $requestFactory !== null && $streamFactory !== null) {
            return new PsrHttpClient($httpClient, $requestFactory, $streamFactory);
        }

        // Try to auto-discover PSR implementations
        if (class_exists('\Http\Discovery\Psr18ClientDiscovery')) {
            try {
                $discoveredClient = \Http\Discovery\Psr18ClientDiscovery::find();
                $discoveredRequestFactory = \Http\Discovery\Psr17FactoryDiscovery::findRequestFactory();
                $discoveredStreamFactory = \Http\Discovery\Psr17FactoryDiscovery::findStreamFactory();

                return new PsrHttpClient(
                    $discoveredClient,
                    $discoveredRequestFactory,
                    $discoveredStreamFactory
                );
            } catch (\Http\Discovery\Exception\NotFoundException $e) {
                // Fall through to legacy client
            }
        }

        // Fallback to legacy file_get_contents implementation
        return new FileGetContentsClient();
    }

    /**
     * Create default client with recommended Guzzle + Nyholm PSR-7 setup
     */
    public static function createWithGuzzle(): HttpClientInterface
    {
        if (!class_exists('\GuzzleHttp\Client')) {
            throw new \RuntimeException(
                'Guzzle HTTP client not found. Install with: composer require guzzlehttp/guzzle'
            );
        }

        if (!class_exists('\Nyholm\Psr7\Factory\Psr17Factory')) {
            throw new \RuntimeException(
                'Nyholm PSR-7 not found. Install with: composer require nyholm/psr7'
            );
        }

        $guzzle = new \GuzzleHttp\Client([
            'timeout' => 30,
            'connect_timeout' => 10,
            'http_errors' => true,
        ]);

        $psr17Factory = new \Nyholm\Psr7\Factory\Psr17Factory();

        return new PsrHttpClient($guzzle, $psr17Factory, $psr17Factory);
    }
}
```text
### Phase 3: Refactor Existing Code (Week 2)

#### 3.1 Update CalendarSelect.php

**Before:**

```php
$metadataRaw = file_get_contents($url);
if ($metadataRaw === false) {
    throw new \Exception("Error fetching metadata from {$this->metadataUrl}");
}
$metadataJSON = json_decode($metadataRaw, true);
```text
**After:**

```php
$response = $this->httpClient->get($url);

if ($response->getStatusCode() !== 200) {
    throw new \Exception(
        "Error fetching metadata from {$this->metadataUrl}. " .
        "Status: {$response->getStatusCode()}"
    );
}

$metadataRaw = $response->getBody()->getContents();
$metadataJSON = json_decode($metadataRaw, true);
```text
**Constructor changes:**

```php
public function __construct(
    ?array $options = null,
    ?HttpClientInterface $httpClient = null
) {
    $this->httpClient = $httpClient ?? HttpClientFactory::create();
    // ... existing constructor code
}
```text
#### 3.2 Update ApiOptions/Input/Locale.php

Similar refactoring pattern as CalendarSelect.php

### Phase 4: Testing Strategy (Week 2)

#### 4.1 Unit Tests

**Location:** `tests/Unit/Http/`

- `PsrHttpClientTest.php` - Test PSR client with mocked PSR-18 client
- `FileGetContentsClientTest.php` - Test legacy fallback
- `HttpClientFactoryTest.php` - Test factory methods and auto-discovery

#### 4.2 Integration Tests

**Location:** `tests/Integration/`

- Test actual HTTP requests with VCR (record/replay)
- Test fallback behavior when PSR dependencies unavailable
- Test error handling and exceptions

#### 4.3 Mock Example

```php
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class CalendarSelectTest extends TestCase
{
    public function testFetchMetadataSuccess(): void
    {
        // Mock the PSR-7 stream
        $mockStream = $this->createMock(StreamInterface::class);
        $mockStream->method('getContents')
            ->willReturn('{"litcal_metadata": {...}}');

        // Mock the PSR-7 response
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn($mockStream);

        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->method('sendRequest')->willReturn($mockResponse);

        $httpClient = new PsrHttpClient(
            $mockClient,
            $requestFactory,
            $streamFactory
        );

        $calendarSelect = new CalendarSelect(null, $httpClient);
        // ... test assertions
    }
}
```text
---

### Phase 5: Logging Integration (Week 3)

#### 5.1 Create Logging Abstraction

**Location:** `src/Logging/LoggerAwareTrait.php`

```php
<?php
namespace LiturgicalCalendar\Components\Logging;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

trait LoggerAwareTrait
{
    private ?LoggerInterface $logger = null;

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    protected function getLogger(): LoggerInterface
    {
        return $this->logger ?? new NullLogger();
    }
}
```text
#### 5.2 Logging HTTP Client Decorator

**Location:** `src/Http/LoggingHttpClient.php`

```php
<?php
namespace LiturgicalCalendar\Components\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * HTTP Client decorator that logs all requests and responses
 */
class LoggingHttpClient implements HttpClientInterface
{
    public function __construct(
        private HttpClientInterface $client,
        private LoggerInterface $logger = new NullLogger()
    ) {}

    public function get(string $url, array $headers = []): ResponseInterface
    {
        $this->logger->info('HTTP GET request', [
            'url' => $url,
            'headers' => $headers,
        ]);

        $startTime = microtime(true);

        try {
            $response = $this->client->get($url, $headers);
            $duration = microtime(true) - $startTime;

            $this->logger->info('HTTP GET response', [
                'url' => $url,
                'status_code' => $response->getStatusCode(),
                'duration_ms' => round($duration * 1000, 2),
                'response_size' => $response->getBody()->getSize(),
            ]);

            return $response;
        } catch (HttpException $e) {
            $duration = microtime(true) - $startTime;

            $this->logger->error('HTTP GET failed', [
                'url' => $url,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'exception' => get_class($e),
            ]);

            throw $e;
        }
    }

    public function post(string $url, array|string $body, array $headers = []): ResponseInterface
    {
        $this->logger->info('HTTP POST request', [
            'url' => $url,
            'headers' => $headers,
            'body_size' => is_string($body) ? strlen($body) : count($body),
        ]);

        $startTime = microtime(true);

        try {
            $response = $this->client->post($url, $body, $headers);
            $duration = microtime(true) - $startTime;

            $this->logger->info('HTTP POST response', [
                'url' => $url,
                'status_code' => $response->getStatusCode(),
                'duration_ms' => round($duration * 1000, 2),
            ]);

            return $response;
        } catch (HttpException $e) {
            $duration = microtime(true) - $startTime;

            $this->logger->error('HTTP POST failed', [
                'url' => $url,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
            ]);

            throw $e;
        }
    }
}
```text
#### 5.3 Update HttpClientFactory with Logging

**Location:** `src/Http/HttpClientFactory.php` (enhancement)

```php
public static function createWithLogging(
    ?LoggerInterface $logger = null
): HttpClientInterface {
    $baseClient = self::createWithGuzzle();
    $logger = $logger ?? new NullLogger();

    return new LoggingHttpClient($baseClient, $logger);
}
```text
#### 5.4 Update CalendarSelect with Logger Support

**Location:** `src/CalendarSelect.php` (enhancement)

```php
use LiturgicalCalendar\Components\Logging\LoggerAwareTrait;

class CalendarSelect
{
    use LoggerAwareTrait;

    public function __construct(
        ?array $options = null,
        ?HttpClientInterface $httpClient = null,
        ?LoggerInterface $logger = null
    ) {
        $this->httpClient = $httpClient ?? HttpClientFactory::create();

        if ($logger !== null) {
            $this->setLogger($logger);
            // Wrap HTTP client with logging decorator
            $this->httpClient = new LoggingHttpClient($this->httpClient, $logger);
        }

        // ... existing constructor code
    }
}
```text
#### 5.5 Logging Events to Track

**HTTP Operations:**

- Request initiated (URL, method, headers)
- Response received (status code, duration, size)
- Request failed (error, duration, exception type)

**Business Logic:**

- Metadata fetched successfully
- Cache hit/miss
- Locale validation
- Configuration changes

**Errors:**

- Network failures
- Invalid JSON responses
- Configuration errors
- Validation failures

---

### Phase 6: Caching Integration (Week 3-4)

#### 6.1 Cache Abstraction Strategy

**Implementation Decision:** Use PSR-16 directly without custom extensions

The library uses `Psr\SimpleCache\CacheInterface` directly rather than creating a custom domain-specific cache interface. This design decision:

- **Maintains PSR compatibility**: Consumers can use any PSR-16 cache implementation
- **Reduces complexity**: No custom cache interface to maintain
- **Improves interoperability**: Works seamlessly with existing PSR-16 libraries (Symfony Cache, etc.)
- **Keeps domain logic in decorators**: HTTP caching logic lives in `CachingHttpClient`, not in cache implementations

The caching strategy is implemented through:

1. **PSR-16 implementations**: ArrayCache (in-memory), or consumer-provided implementations
1. **HTTP client decorator**: `CachingHttpClient` handles domain-specific caching logic
1. **Factory methods**: `HttpClientFactory::createWithCaching()` for convenient setup

#### 6.2 Caching HTTP Client Decorator

**Location:** `src/Http/CachingHttpClient.php`

```php
<?php
namespace LiturgicalCalendar\Components\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Nyholm\Psr7\Response;

/**
 * HTTP Client decorator that caches GET responses
 *
 * Uses PSR-16 Simple Cache interface to cache successful HTTP GET responses.
 * POST requests are not cached.
 *
 * Cache keys are generated based on URL and semantically relevant headers only.
 * Headers like User-Agent, X-Request-ID, etc. are excluded to maximize cache hits.
 */
class CachingHttpClient implements HttpClientInterface
{
    /**
     * Headers that affect the API response and should be part of the cache key.
     *
     * For Liturgical Calendar API:
     * - Accept-Language: Determines the language/locale of the response
     * - Accept: Determines the response format (json, xml, yaml, icalendar)
     *
     * Other headers (User-Agent, X-Request-ID, etc.) don't affect the response
     * content and are excluded to improve cache hit rates.
     */
    private const CACHE_RELEVANT_HEADERS = [
        'accept-language',
        'accept',
    ];

    public function __construct(
        private HttpClientInterface $client,
        private CacheInterface $cache,
        private int $defaultTtl = 3600,
        private LoggerInterface $logger = new NullLogger()
    ) {}

    public function get(string $url, array $headers = []): ResponseInterface
    {
        $cacheKey = $this->getCacheKey($url, $headers);

        // Try to get from cache
        $cachedData = $this->cache->get($cacheKey);

        if ($cachedData !== null) {
            $this->logger->debug('Cache hit', ['url' => $url, 'key' => $cacheKey]);

            return new Response(
                $cachedData['status'],
                $cachedData['headers'],
                $cachedData['body']
            );
        }

        $this->logger->debug('Cache miss', ['url' => $url, 'key' => $cacheKey]);

        // Fetch from HTTP client
        $response = $this->client->get($url, $headers);

        // Cache successful responses (2xx)
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $this->cacheResponse($cacheKey, $response);
        }

        return $response;
    }

    public function post(string $url, array|string $body, array $headers = []): ResponseInterface
    {
        // POST requests are not cached
        return $this->client->post($url, $body, $headers);
    }

    private function getCacheKey(string $url, array $headers): string
    {
        // Filter headers to only include cache-relevant ones
        $relevantHeaders = [];
        foreach ($headers as $name => $value) {
            if (in_array(strtolower($name), self::CACHE_RELEVANT_HEADERS, true)) {
                $relevantHeaders[strtolower($name)] = $value;
            }
        }

        // Sort headers by key for consistent cache keys
        ksort($relevantHeaders);

        $keyData = [
            'url' => $url,
            'headers' => $relevantHeaders,
        ];

        return 'http_' . hash('sha256', serialize($keyData));
    }

    private function cacheResponse(string $cacheKey, ResponseInterface $response): void
    {
        $body = $response->getBody()->getContents();

        // Reset stream position after reading
        $response->getBody()->rewind();

        $cacheData = [
            'status' => $response->getStatusCode(),
            'headers' => $response->getHeaders(),
            'body' => $body,
        ];

        $this->cache->set($cacheKey, $cacheData, $this->defaultTtl);

        $this->logger->debug('Response cached', [
            'key' => $cacheKey,
            'ttl' => $this->defaultTtl,
            'size' => strlen($body),
        ]);
    }
}
```text
#### 6.3 In-Memory Array Cache Implementation

**Location:** `src/Cache/ArrayCache.php`

```php
<?php
namespace LiturgicalCalendar\Components\Cache;

use Psr\SimpleCache\CacheInterface;

/**
 * Simple in-memory cache for development and testing
 * Request-scoped only (no persistence)
 */
class ArrayCache implements CacheInterface
{
    private array $cache = [];
    private array $expiry = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (!isset($this->cache[$key])) {
            return $default;
        }

        // Check expiry
        if (isset($this->expiry[$key]) && time() > $this->expiry[$key]) {
            unset($this->cache[$key], $this->expiry[$key]);
            return $default;
        }

        return $this->cache[$key];
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $this->cache[$key] = $value;

        if ($ttl !== null) {
            $seconds = $ttl instanceof \DateInterval
                ? (new \DateTime())->add($ttl)->getTimestamp() - time()
                : $ttl;

            $this->expiry[$key] = time() + $seconds;
        }

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->cache[$key], $this->expiry[$key]);
        return true;
    }

    public function clear(): bool
    {
        $this->cache = [];
        $this->expiry = [];
        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }
}
```text
#### 6.4 Update HttpClientFactory with Caching Support

**Location:** `src/Http/HttpClientFactory.php` (enhancement)

```php
public static function createWithCaching(
    ?CacheInterface $cache = null,
    int $ttl = 3600,
    ?LoggerInterface $logger = null
): HttpClientInterface {
    $baseClient = self::createWithGuzzle();
    $cache = $cache ?? new ArrayCache();
    $logger = $logger ?? new NullLogger();

    // Wrap with caching, then logging
    $cachedClient = new CachingHttpClient($baseClient, $cache, $ttl, $logger);
    return new LoggingHttpClient($cachedClient, $logger);
}
```text
#### 6.5 Update CalendarSelect with Cache Support

**Location:** `src/CalendarSelect.php` (enhancement)

```php
use Psr\SimpleCache\CacheInterface;
use LiturgicalCalendar\Components\Cache\ArrayCache;

class CalendarSelect
{
    public function __construct(
        ?array $options = null,
        ?HttpClientInterface $httpClient = null,
        ?LoggerInterface $logger = null,
        ?CacheInterface $cache = null
    ) {
        // If cache provided, wrap HTTP client
        if ($cache !== null && $httpClient !== null) {
            $httpClient = new CachingHttpClient(
                $httpClient,
                $cache,
                3600, // 1 hour TTL for metadata
                $logger ?? new NullLogger()
            );
        }

        $this->httpClient = $httpClient ?? HttpClientFactory::create();

        // ... existing constructor code
    }
}
```text
#### 6.6 Cache Strategy Configuration

**Recommended Cache TTLs:**

- Calendar metadata: **1 hour** (changes infrequently)
- Locale information: **24 hours** (very stable)
- API responses: **5-15 minutes** (balances freshness vs. performance)

**Cache Invalidation Triggers:**

- Manual cache clear via admin interface
- Metadata URL change
- Time-based expiration
- On deployment/version update

**Multi-Level Caching Example:**

```php
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

// Create multi-level cache
$cache = new Psr16Cache(
    new ChainAdapter([
        new ApcuAdapter('litcal', 3600),           // L1: APCu (fast, in-memory)
        new FilesystemAdapter('litcal', 86400),    // L2: Filesystem (persistent)
    ])
);

$httpClient = HttpClientFactory::createWithCaching($cache, 3600);
$calendar = new CalendarSelect(null, $httpClient);
```text
---

### Phase 7: Middleware & Polish (Week 4)

#### 7.1 Retry Middleware (Already documented)

See existing "Future Enhancements" section for retry implementation.

#### 7.2 Circuit Breaker Pattern (Advanced)

**Location:** `src/Http/CircuitBreakerHttpClient.php`

Prevents cascading failures by failing fast after consecutive errors.

```php
class CircuitBreakerHttpClient implements HttpClientInterface
{
    private const STATE_CLOSED = 'closed';      // Normal operation
    private const STATE_OPEN = 'open';          // Failing fast
    private const STATE_HALF_OPEN = 'half_open'; // Testing recovery

    private string $state = self::STATE_CLOSED;
    private int $failureCount = 0;
    private ?int $lastFailureTime = null;

    public function __construct(
        private HttpClientInterface $client,
        private int $failureThreshold = 5,
        private int $recoveryTimeout = 60, // seconds
        private LoggerInterface $logger = new NullLogger()
    ) {}

    public function get(string $url, array $headers = []): ResponseInterface
    {
        $this->updateState();

        if ($this->state === self::STATE_OPEN) {
            $this->logger->warning('Circuit breaker OPEN - request blocked', ['url' => $url]);
            throw new HttpException('Service temporarily unavailable (circuit breaker open)');
        }

        try {
            $response = $this->client->get($url, $headers);
            $this->onSuccess();
            return $response;
        } catch (HttpException $e) {
            $this->onFailure();
            throw $e;
        }
    }

    private function updateState(): void
    {
        if ($this->state === self::STATE_OPEN) {
            if (time() - $this->lastFailureTime >= $this->recoveryTimeout) {
                $this->state = self::STATE_HALF_OPEN;
                $this->logger->info('Circuit breaker entering HALF_OPEN state');
            }
        }
    }

    private function onSuccess(): void
    {
        if ($this->state === self::STATE_HALF_OPEN) {
            $this->state = self::STATE_CLOSED;
            $this->failureCount = 0;
            $this->logger->info('Circuit breaker CLOSED - service recovered');
        }
    }

    private function onFailure(): void
    {
        $this->failureCount++;
        $this->lastFailureTime = time();

        if ($this->failureCount >= $this->failureThreshold) {
            $this->state = self::STATE_OPEN;
            $this->logger->error('Circuit breaker OPEN - too many failures', [
                'failure_count' => $this->failureCount,
            ]);
        }
    }
}
```text
---

## Migration Timeline

### Week 1: HTTP Client Foundation

- [x] Add PSR dependencies to composer.json (PSR-7, PSR-17, PSR-18)
- [x] Create HTTP abstraction layer (interfaces and implementations)
- [x] Create PSR-18 client implementation with Guzzle + Nyholm PSR-7
- [x] Create file_get_contents fallback client
- [x] Create factory and auto-discovery logic
- [x] Update README with PSR-18 benefits and configuration

### Week 2: Integration & Testing

- [x] Refactor CalendarSelect to use HTTP client
- [x] Refactor ApiOptions/Input/Locale to use HTTP client
- [x] Add comprehensive unit tests for HTTP clients
- [x] Add integration tests with mocked responses
- [x] PHPStan level 10 validation
- [x] Update documentation (README.md + UPGRADE.md)

### Week 3: Logging & Caching

- [x] Add PSR-3 (logging) dependencies
- [x] Implement LoggingHttpClient decorator
- [x] Add PSR-6/PSR-16 (caching) dependencies
- [x] Implement CachingHttpClient decorator
- [x] Implement ArrayCache fallback
- [x] Update CalendarSelect to support logging and caching
- [x] Update ApiOptions/Input/Locale to support logging and caching
- [x] Unit tests for LoggingHttpClient
- [x] Unit tests for ArrayCache
- [x] Unit tests for CachingHttpClient
- [x] PHPStan level 10 validation maintained
- [x] Performance benchmarking (with/without cache)
- [ ] Integration tests for caching (optional)

### Week 4: Advanced Features & Polish

- [x] Implement RetryHttpClient middleware
- [x] Implement CircuitBreakerHttpClient
- [x] Update HttpClientFactory with retry and circuit breaker support
- [x] Create comprehensive unit tests for RetryHttpClient (10 tests)
- [x] Create comprehensive unit tests for CircuitBreakerHttpClient (11 tests)
- [x] Create migration guide (UPGRADE.md)
- [x] Final PHPStan level 10 validation
- [ ] Add comprehensive examples to README (optional)
- [ ] Performance benchmarking comprehensive report (optional)
- [ ] Prepare release notes (optional)

---

## Dependency Management Strategy

### composer.json Updates

**Option 1: Soft Dependencies (Recommended)**

```json
{
    "require": {
        "php": ">=8.1",
        "ext-intl": "*",
        "ext-json": "*",
        "vlucas/phpdotenv": "^5.6",
        "psr/http-client": "^1.0",
        "psr/http-factory": "^1.0",
        "psr/http-message": "^1.0 || ^2.0",
        "psr/log": "^3.0",
        "psr/simple-cache": "^3.0"
    },
    "suggest": {
        "guzzlehttp/guzzle": "^7.0 - PSR-18 HTTP client implementation (recommended)",
        "nyholm/psr7": "^1.0 - PSR-7 and PSR-17 implementations (recommended)",
        "monolog/monolog": "^3.0 - PSR-3 logging implementation (recommended)",
        "symfony/cache": "^6.0 || ^7.0 - PSR-6/PSR-16 caching implementation (recommended)",
        "php-http/discovery": "^1.0 - Automatic PSR implementation discovery"
    }
}
```text
**Benefits:**

- Library remains lightweight
- Users can choose their own PSR implementations
- Falls back to file_get_contents if PSR packages not installed
- No breaking changes for existing users

**Option 2: Hard Dependencies**

```json
{
    "require": {
        "php": ">=8.1",
        "ext-intl": "*",
        "ext-json": "*",
        "vlucas/phpdotenv": "^5.6",
        "guzzlehttp/guzzle": "^7.0",
        "nyholm/psr7": "^1.0",
        "monolog/monolog": "^3.0",
        "symfony/cache": "^6.0 || ^7.0"
    }
}
```text
**Benefits:**

- Guaranteed PSR compliance out of the box
- Professional error handling and logging included
- HTTP response caching available immediately
- Simpler implementation (no fallback needed)
- Better developer experience for library users

**Recommendation:** Start with **Option 1** to maintain backward compatibility

---

## Benefits of PSR Implementation

### 1. **Testability**

- Mock HTTP clients easily in unit tests
- No need for complex stream wrappers or VCR libraries
- Predictable, isolated test environments

### 2. **Flexibility**

- Users can inject their own HTTP clients
- Easy to add middleware (caching, logging, rate limiting)
- Swap implementations without code changes

### 3. **Reliability**

- Professional error handling
- Timeout configuration
- Retry logic support
- Better exception handling

### 4. **Performance**

- Connection pooling (with Guzzle)
- Async requests (future enhancement)
- HTTP/2 support (client-dependent)

### 5. **Standards Compliance**

- Industry-standard interfaces
- Interoperability with other PSR-compliant libraries
- Future-proof architecture

### 6. **Advanced Features (Future)**

- Response caching middleware
- Request logging middleware
- Authentication middleware
- Rate limiting
- Circuit breaker pattern

---

## Backward Compatibility Considerations

### For Library Users

#### Existing Code (No Changes Required)

```php
$calendar = new CalendarSelect();
// Works exactly as before with file_get_contents fallback
```text
#### With PSR Client (Optional)

```php
$httpClient = HttpClientFactory::createWithGuzzle();
$calendar = new CalendarSelect(null, $httpClient);
// Uses PSR-18 HTTP client
```text
#### Custom Client Injection

```php
$myCustomClient = new MyCustomPsrClient();
$calendar = new CalendarSelect(null, $myCustomClient);
// Maximum flexibility
```text
### Breaking Changes: **NONE**

All changes are additive. Existing code continues to work unchanged.

---

## Documentation Updates Required

### 1. README.md

- Add PSR-18 section explaining benefits
- Installation instructions for recommended packages
- Example configurations

### 2. UPGRADE.md (New File)

- Migration guide for users wanting PSR benefits
- Code examples showing before/after
- Performance comparison

### 3. API Documentation

- Document HttpClientInterface
- Document factory methods
- Middleware examples

---

## Future Enhancements

### Phase 4: Middleware Support (Future)

#### Caching Middleware

```php
use Psr\Cache\CacheItemPoolInterface;

class CachingHttpClient implements HttpClientInterface
{
    public function __construct(
        private HttpClientInterface $client,
        private CacheItemPoolInterface $cache,
        private int $ttl = 3600
    ) {}

    public function get(string $url, array $headers = []): ResponseInterface
    {
        $cacheKey = 'http_' . md5($url . serialize($headers));
        $cacheItem = $this->cache->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $response = $this->client->get($url, $headers);

        $cacheItem->set($response);
        $cacheItem->expiresAfter($this->ttl);
        $this->cache->save($cacheItem);

        return $response;
    }
}
```text
#### Retry Middleware

```php
class RetryHttpClient implements HttpClientInterface
{
    public function __construct(
        private HttpClientInterface $client,
        private int $maxRetries = 3,
        private int $retryDelay = 1000 // milliseconds
    ) {}

    public function get(string $url, array $headers = []): ResponseInterface
    {
        $attempt = 0;

        while ($attempt < $this->maxRetries) {
            try {
                return $this->client->get($url, $headers);
            } catch (HttpException $e) {
                $attempt++;
                if ($attempt >= $this->maxRetries) {
                    throw $e;
                }
                usleep($this->retryDelay * 1000);
            }
        }
    }
}
```text
### Phase 5: Async Support (Future)

For future async calendar data fetching when multiple API calls needed.

---

## Security Considerations

### 1. SSL/TLS Verification

Ensure HTTP clients verify SSL certificates by default

### 2. Timeout Configuration

Prevent indefinite hangs with reasonable timeouts

### 3. URL Validation

Validate URLs before making requests

### 4. Response Size Limits

Prevent memory exhaustion from large responses

---

## Performance Benchmarks (To Be Measured)

### Metrics to Track

- Request latency comparison
- Memory usage comparison
- Connection pooling benefits
- Cache hit rates (with middleware)

### Expected Results

- Similar or better performance with Guzzle
- Memory usage increase minimal (~1-2MB)
- Significant gains with connection pooling and caching

---

## Conclusion

This comprehensive implementation plan provides a clear path to full PSR compliance (PSR-7, PSR-17, PSR-18, PSR-3, PSR-6, PSR-16) while maintaining backward compatibility. The phased approach allows for incremental adoption and testing. The architecture supports future enhancements through middleware while keeping the core library lightweight and flexible.

### Summary of Recommendations

| Component | Recommended Implementation | Fallback | Rationale |
|-----------|---------------------------|----------|-----------|
| **HTTP Messages** | `nyholm/psr7` | - | Lightweight, user preference, PHPStan compatible |
| **HTTP Client** | `guzzlehttp/guzzle` ^7.0 | `file_get_contents()` | Industry standard, middleware ecosystem |
| **Logging** | `monolog/monolog` ^3.0 | `NullLogger` | Professional logging, Guzzle integration |
| **Caching** | `symfony/cache` ^6.0/^7.0 | `ArrayCache` | PSR-6 & PSR-16, multi-backend support |
| **Discovery** | `php-http/discovery` (optional) | Manual injection | Auto-discovery convenience |

### Key Benefits of This Approach

1. **Backward Compatibility**: Zero breaking changes for existing users
1. **Flexibility**: Users can inject their own implementations
1. **Professional Features**: Logging, caching, retry, circuit breaker
1. **Testability**: Easy mocking and unit testing
1. **Standards Compliance**: Industry-standard PSR interfaces
1. **Performance**: HTTP response caching, connection pooling
1. **Observability**: Structured logging for debugging and monitoring
1. **Reliability**: Better error handling, retry logic, circuit breaker pattern

### Architecture Highlights

**Decorator Pattern Benefits:**

- Composable HTTP client functionality
- Clean separation of concerns
- Easy to add/remove features
- Testable in isolation

**Middleware Stack Example:**

```text
User Code
    ↓
LoggingHttpClient (logs requests/responses)
    ↓
CachingHttpClient (caches GET responses)
    ↓
RetryHttpClient (retries on failure)
    ↓
CircuitBreakerHttpClient (prevents cascading failures)
    ↓
PsrHttpClient (PSR-18 implementation)
    ↓
Guzzle (actual HTTP requests)
```text
### Next Steps

1. **Review and Approve** this comprehensive game plan
1. **Decide on Dependency Strategy**:
   - Option 1 (Soft Dependencies) - Recommended for backward compatibility
   - Option 2 (Hard Dependencies) - For guaranteed PSR compliance
1. **Create Feature Branch**: `feature/psr-compliance`
1. **Phase 1 (Week 1)**: HTTP Client abstraction and implementation
1. **Phase 2 (Week 1)**: Dependency injection and factory setup
1. **Phase 3 (Week 2)**: Refactor existing code to use HTTP client
1. **Phase 4 (Week 2)**: Comprehensive testing and PHPStan validation
1. **Phase 5 (Week 3)**: Logging integration with PSR-3
1. **Phase 6 (Week 3-4)**: Caching integration with PSR-6/PSR-16
1. **Phase 7 (Week 4)**: Advanced middleware and polish
1. **Documentation**: Update README, create UPGRADE.md, API docs
1. **Benchmarking**: Performance comparison before/after
1. **Release**: New minor version (e.g., v1.2.0 - no breaking changes)

### Success Metrics

- [ ] PHPStan level 10 compliance maintained
- [ ] Code coverage > 85% for new HTTP/Cache/Logging code
- [ ] Performance regression < 5% without caching
- [ ] Performance improvement > 50% with caching enabled
- [ ] Zero breaking changes for existing users
- [ ] Comprehensive documentation and examples
- [ ] Successful integration tests with mocked HTTP responses

---

## Additional Resources

### PSR Specifications

- [PSR-7: HTTP Message Interfaces](https://www.php-fig.org/psr/psr-7/)
- [PSR-17: HTTP Factories](https://www.php-fig.org/psr/psr-17/)
- [PSR-18: HTTP Client](https://www.php-fig.org/psr/psr-18/)
- [PSR-3: Logger Interface](https://www.php-fig.org/psr/psr-3/)
- [PSR-6: Caching Interface](https://www.php-fig.org/psr/psr-6/)
- [PSR-16: Simple Cache](https://www.php-fig.org/psr/psr-16/)

### Library Documentation

- [Guzzle Documentation](https://docs.guzzlephp.org/)
- [Nyholm PSR-7 Documentation](https://github.com/Nyholm/psr7)
- [Monolog Documentation](https://github.com/Seldaek/monolog)
- [Symfony Cache Component](https://symfony.com/doc/current/components/cache.html)
- [HTTPlug Documentation](https://docs.php-http.org/)

### Design Patterns

- [Decorator Pattern](https://refactoring.guru/design-patterns/decorator)
- [Circuit Breaker Pattern](https://martinfowler.com/bliki/CircuitBreaker.html)
- [Retry Pattern](https://docs.microsoft.com/en-us/azure/architecture/patterns/retry)

---

**Document Version:** 2.0
**Last Updated:** 2025-11-15
**Author:** Claude Code (Sonnet 4.5)
**Status:** Comprehensive Proposal - Awaiting Approval

---

## Implementation Progress

### Phase 1: HTTP Client Foundation ✅ COMPLETED (2025-11-15)

**Status**: Phase 1 fully implemented and tested

**Completed Items**:

- ✅ Added PSR interface dependencies to composer.json (psr/http-client, psr/http-factory, psr/http-message, psr/log, psr/simple-cache)
- ✅ Added `nyholm/psr7` ^1.0 for PSR-7/PSR-17 implementation
- ✅ Created HTTP abstraction layer in `src/Http/`:
  - `HttpClientInterface.php` - Contract for HTTP operations
  - `HttpException.php` - Custom exception for HTTP errors
  - `PsrHttpClient.php` - PSR-18 compliant HTTP client using Guzzle
  - `FileGetContentsClient.php` - Fallback using native PHP `file_get_contents()`
  - `HttpClientFactory.php` - Factory with auto-discovery and manual configuration
- ✅ Refactored `CalendarSelect.php` to use dependency-injected HTTP client
- ✅ Refactored `ApiOptions/Input/Locale.php` to use dependency-injected HTTP client
- ✅ PHPStan level 10 validation passing with zero errors
- ✅ All existing tests passing (13/13 tests, 128 assertions)
- ✅ Fixed pre-existing bug in `HolyDaysOfObligation.php` (missing `data-param` attribute)

**Architecture**:

- Backward compatible: existing code works without changes
- Dependency injection: optional `HttpClientInterface` parameter in constructors
- Auto-discovery: automatically uses available PSR-18 clients or falls back to native PHP
- Type-safe: maintains strict PHPStan level 10 compliance

**Files Created**:

```text
src/Http/
├── HttpClientInterface.php
├── HttpException.php
├── PsrHttpClient.php
├── FileGetContentsClient.php
└── HttpClientFactory.php
```text
**Files Modified**:

- `composer.json` - Added PSR dependencies
- `src/CalendarSelect.php` - Now accepts optional `HttpClientInterface`
- `src/ApiOptions/Input/Locale.php` - Now accepts optional `HttpClientInterface`
- `src/ApiOptions/Input/HolyDaysOfObligation.php` - Fixed missing `data` property

**Test Results**:

```text
PHPUnit 12.4.3
OK (54 tests, 224 assertions, 1 skipped)
PHPStan Level 10: No errors
```text
**Unit Tests Created** (2025-11-15):

- `tests/Http/HttpExceptionTest.php` - 5 tests for exception handling
- `tests/Http/FileGetContentsClientTest.php` - 13 tests for fallback client
- `tests/Http/PsrHttpClientTest.php` - 10 tests for PSR-18 client with mocks
- `tests/Http/HttpClientFactoryTest.php` - 13 tests for factory methods

**Test Coverage**:

- ✅ HttpException: Constructor variants, throwable behavior
- ✅ FileGetContentsClient: GET/POST, headers, error handling, response parsing
- ✅ PsrHttpClient: Request creation, header handling, JSON encoding, exception wrapping
- ✅ HttpClientFactory: Auto-discovery, fallback logic, Guzzle integration

**Next Steps**:

- Update README with usage examples and benefits
- Begin Phase 6: Caching Integration (PSR-6/PSR-16)
- Consider adding integration tests with live HTTP endpoints

---

### Phase 5: Logging Integration (PSR-3) ✅ COMPLETED (2025-11-15)

**Status**: Phase 5 fully implemented and tested

**Completed Items**:

- ✅ Created logging abstraction layer in `src/Logging/`:
  - `LoggerAwareTrait.php` - PSR-3 logger dependency injection trait
- ✅ Created `LoggingHttpClient.php` decorator in `src/Http/`:
  - Wraps any HttpClientInterface with logging capabilities
  - Logs all HTTP requests and responses
  - Tracks duration, status codes, and response sizes
  - Sanitizes sensitive headers (Authorization, API keys, cookies)
  - Logs errors and exceptions with context
- ✅ Updated `HttpClientFactory::createWithLogging()` method
- ✅ Updated `CalendarSelect` with optional logger parameter
- ✅ Updated `ApiOptions/Input/Locale` with optional logger parameter
- ✅ Created comprehensive unit tests for LoggingHttpClient

**Files Created**:

```text
src/Logging/
└── LoggerAwareTrait.php

src/Http/
└── LoggingHttpClient.php (decorator)

tests/Http/
└── LoggingHttpClientTest.php (9 tests)
```text
**Files Modified**:

- `src/Http/HttpClientFactory.php` - Added `createWithLogging()` method
- `src/CalendarSelect.php` - Added LoggerAwareTrait and logger injection
- `src/ApiOptions/Input/Locale.php` - Added logger injection support

**Architecture**:

- Decorator pattern: LoggingHttpClient wraps any HttpClientInterface
- PSR-3 compliant: Uses LoggerInterface with NullLogger fallback
- Security: Automatically sanitizes sensitive headers in logs
- Performance tracking: Logs request duration in milliseconds
- Error tracking: Logs exceptions with full context

**Logging Capabilities**:

- **Request Logging**: URL, method, headers (sanitized), body size
- **Response Logging**: Status code, duration, response size
- **Error Logging**: Exception message, duration, exception class
- **Header Sanitization**: Redacts Authorization, API-Key, Cookie headers

**Test Results**:

```text
PHPUnit 12.4.3
OK (63 tests, 274 assertions, 1 skipped)
PHPStan Level 10: No errors
```text
**Usage Example**:

```php
use LiturgicalCalendar\Components\CalendarSelect;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Create logger (e.g., Monolog)
$logger = new Logger('liturgical-calendar');
$logger->pushHandler(new StreamHandler('php://stderr', Logger::INFO));

// Inject logger into CalendarSelect
$calendar = new CalendarSelect([], null, $logger);

// All HTTP requests will now be logged automatically
```text
**Next Steps**:

- Update README with logging examples
- Consider adding structured logging formats (JSON)

---

### Phase 6: Caching Integration (PSR-6/PSR-16) ✅ COMPLETED (2025-11-15)

**Status**: Phase 6 fully implemented and tested

**Completed Items**:

- ✅ Added PSR-6 and PSR-16 dependencies to composer.json
- ✅ Created `ArrayCache` - PSR-16 in-memory cache implementation
- ✅ Created `CachingHttpClient` decorator in `src/Http/`:
  - Wraps any HttpClientInterface with HTTP response caching
  - Caches successful GET responses (2xx status codes)
  - Does not cache POST requests
  - Configurable TTL (time-to-live) per cache instance
  - Logs cache hits/misses when logger is provided
  - Generates unique cache keys based on URL and headers
- ✅ Updated `HttpClientFactory::createWithCaching()` method
- ✅ Updated `CalendarSelect` with optional cache parameter
- ✅ Updated `ApiOptions/Input/Locale` with optional cache parameter
- ✅ Created comprehensive unit tests for ArrayCache (13 tests)
- ✅ Created comprehensive unit tests for CachingHttpClient (8 tests)

**Files Created**:

```text
src/Cache/
└── ArrayCache.php (PSR-16 implementation)

src/Http/
└── CachingHttpClient.php (decorator)

tests/Cache/
└── ArrayCacheTest.php (13 tests)

tests/Http/
└── CachingHttpClientTest.php (8 tests)
```text
**Files Modified**:

- `composer.json` - Added psr/cache ^3.0
- `src/Http/HttpClientFactory.php` - Added createWithCaching() method
- `src/CalendarSelect.php` - Added cache injection support
- `src/ApiOptions/Input/Locale.php` - Added cache injection support

**Architecture**:

- Decorator pattern: CachingHttpClient wraps any HttpClientInterface
- PSR-16 Simple Cache compliant
- Flexible cache backends: ArrayCache (in-memory), or any PSR-16 implementation (Redis, Filesystem, etc.)
- Cache TTL defaults: 1 hour for calendar metadata, 24 hours for locale data
- Automatic cache key generation using SHA-256 hash of URL + headers

**Caching Capabilities**:

- **GET Request Caching**: Caches successful GET responses (status 200-299)
- **POST Bypass**: POST requests are never cached
- **TTL Support**: Configurable time-to-live for cache entries
- **Cache Key Generation**: SHA-256 hash of serialized URL and headers
- **Logging Integration**: Logs cache hit/miss/store events when logger provided
- **Type Safety**: Full PHPStan level 10 compliance

**ArrayCache Features** (PSR-16):

- In-memory cache for development/testing
- Request-scoped (no persistence between requests)
- TTL support with both integer seconds and DateInterval
- All PSR-16 methods: get, set, delete, clear, getMultiple, setMultiple, deleteMultiple, has
- Automatic expiry cleanup on access
- Supports all PHP data types (string, int, float, bool, array, object)

**Test Results**:

```text
PHPUnit 12.4.3
OK (84 tests, 362 assertions, 1 skipped)
PHPStan Level 10: No errors
```text
**Test Coverage**:

- ✅ ArrayCache: All PSR-16 methods, TTL expiry, data type support
- ✅ CachingHttpClient: Cache miss/hit, TTL respect, non-2xx bypass, POST bypass, logging
- ✅ Cache key uniqueness for different URLs and headers
- ✅ Integration with HttpClientFactory

**Usage Example**:

```php
use LiturgicalCalendar\Components\CalendarSelect;
use LiturgicalCalendar\Components\Cache\ArrayCache;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Create cache (in-memory for this example)
$cache = new ArrayCache();

// Optional: Create logger
$logger = new Logger('liturgical-calendar');
$logger->pushHandler(new StreamHandler('php://stderr', Logger::INFO));

// Inject cache into CalendarSelect
// Calendar metadata will be cached for 1 hour (default)
$calendar = new CalendarSelect([], null, $logger, $cache);

// First call - cache miss, fetches from API
$html1 = $calendar->getSelect();

// Second call - cache hit, no API request
$html2 = $calendar->getSelect();
```text
**Advanced Usage with Symfony Cache**:

```php
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;
use LiturgicalCalendar\Components\Http\HttpClientFactory;

// Use persistent filesystem cache
$filesystemCache = new Psr16Cache(
    new FilesystemAdapter('litcal', 3600, '/tmp/litcal-cache')
);

// Create HTTP client with caching (1 hour TTL)
$httpClient = HttpClientFactory::createWithCaching($filesystemCache, 3600);

// Use with CalendarSelect
$calendar = new CalendarSelect([], $httpClient);
```text
**Cache TTL Recommendations**:

- **Calendar Metadata**: 1 hour (3600 seconds) - changes infrequently
- **Locale Information**: 24 hours (86400 seconds) - very stable
- **Calendar Data**: 5-15 minutes - balance between freshness and performance

**Performance Benchmarking** (2025-11-15):

- ✅ Created `benchmarks/http-caching.php` - Automated benchmark script
- ✅ Benchmark Results:
  - **42.8% performance improvement** with caching enabled
  - **90% cache hit rate** for repeated requests
  - Average response time: 0.28 ms → 0.16 ms
  - Total time saved: 1.21 ms over 10 requests
- ✅ Created `benchmarks/README.md` with documentation

**Next Steps**:

- Consider integration tests with live API endpoints (optional)

---

### Phase 7: Advanced Middleware & Polish ✅ COMPLETED (2025-11-15)

**Status**: Phase 7 fully implemented and tested

**Completed Items**:

- ✅ Created `RetryHttpClient` middleware with exponential backoff
- ✅ Created `CircuitBreakerHttpClient` for preventing cascading failures
- ✅ Updated `HttpClientFactory` with three new methods:
  - `createWithRetry()` - HTTP client with retry logic
  - `createWithCircuitBreaker()` - HTTP client with circuit breaker
  - `createProductionClient()` - Full middleware stack for production
- ✅ Created comprehensive unit tests for RetryHttpClient (10 tests)
- ✅ Created comprehensive unit tests for CircuitBreakerHttpClient (11 tests)
- ✅ Created UPGRADE.md migration guide
- ✅ All tests passing (104 tests, 468 assertions)
- ✅ PHPStan Level 10 validation - No errors

**Files Created**:

```text
src/Http/
├── RetryHttpClient.php (retry middleware)
└── CircuitBreakerHttpClient.php (circuit breaker pattern)

tests/Http/
├── RetryHttpClientTest.php (10 tests)
└── CircuitBreakerHttpClientTest.php (11 tests)

UPGRADE.md (comprehensive migration guide)
```text
**Files Modified**:

- `src/Http/HttpClientFactory.php` - Added `createWithRetry()`, `createWithCircuitBreaker()`, `createProductionClient()` methods

**RetryHttpClient Features**:

- **Configurable Retries**: Default 3 attempts, customizable
- **Exponential Backoff**: Delays double with each retry (1s, 2s, 4s)
- **Linear Backoff Option**: Fixed delay between retries
- **Status Code Filtering**: Only retry specific HTTP status codes (default: 408, 429, 500, 502, 503, 504)
- **Exception Handling**: Catches and retries on HttpException
- **Logging Integration**: Logs each retry attempt with context
- **Type Safe**: Full PHPStan level 10 compliance

**CircuitBreakerHttpClient Features**:

- **Three States**:
  - CLOSED (normal operation)
  - OPEN (failing fast, blocking requests)
  - HALF_OPEN (testing service recovery)
- **Configurable Thresholds**:
  - Failure threshold (default: 5 consecutive failures)
  - Recovery timeout (default: 60 seconds)
  - Success threshold (default: 2 successes in HALF_OPEN to close)
- **Automatic State Management**: Transitions between states based on success/failure
- **Logging Integration**: Logs all state transitions
- **Manual Reset**: Can reset circuit breaker programmatically
- **Monitoring Methods**: `getState()`, `getFailureCount()` for observability

**HttpClientFactory Enhancements**:

1. **createWithRetry()** - Simple retry logic:

```php
$httpClient = HttpClientFactory::createWithRetry(
    maxRetries: 3,
    retryDelay: 1000,
    useExponentialBackoff: true,
    retryStatusCodes: [500, 502, 503, 504]
);
```text
1. **createWithCircuitBreaker()** - Prevent cascading failures:

```php
$httpClient = HttpClientFactory::createWithCircuitBreaker(
    failureThreshold: 5,
    recoveryTimeout: 60,
    successThreshold: 2
);
```text
1. **createProductionClient()** - Full middleware stack:

```php
$httpClient = HttpClientFactory::createProductionClient(
    cache: $cache,
    logger: $logger,
    cacheTtl: 3600,
    maxRetries: 3,
    failureThreshold: 5
);
```text
**Middleware Stack Architecture** (createProductionClient):

```text
┌─────────────────────────────────────┐
│ LoggingHttpClient (Layer 4)        │ ← Logs all operations
│  ↓                                   │
│ CachingHttpClient (Layer 3)        │ ← Caches successful responses
│  ↓                                   │
│ RetryHttpClient (Layer 2)          │ ← Retries failures
│  ↓                                   │
│ CircuitBreakerHttpClient (Layer 1) │ ← Protects base client
│  ↓                                   │
│ Base HTTP Client (Guzzle/Native)   │ ← Makes actual requests
└─────────────────────────────────────┘
```text
**Test Results**:

```text
PHPUnit 12.4.3
OK (104 tests, 468 assertions, 1 skipped)
PHPStan Level 10: No errors

Test Coverage:
✅ RetryHttpClient: Success, retries, max retries, status codes, backoff strategies
✅ CircuitBreakerHttpClient: All state transitions, thresholds, recovery
✅ Integration with logging
✅ Proper exception handling
✅ Timing verification (backoff strategies)
```text
**Usage Examples**:

**Basic Retry:**

```php
use LiturgicalCalendar\Components\Http\HttpClientFactory;
use LiturgicalCalendar\Components\CalendarSelect;

$httpClient = HttpClientFactory::createWithRetry(maxRetries: 3);
$calendar = new CalendarSelect([], $httpClient);
```text
**Circuit Breaker:**

```php
$httpClient = HttpClientFactory::createWithCircuitBreaker(
    failureThreshold: 5,
    recoveryTimeout: 60
);
$calendar = new CalendarSelect([], $httpClient);
```text
**Production Setup with All Features:**

```php
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Monolog\Logger;

$cache = new Psr16Cache(new RedisAdapter(...));
$logger = new Logger('liturgical-calendar');

// Includes: Circuit Breaker + Retry + Cache + Logging
$httpClient = HttpClientFactory::createProductionClient(
    cache: $cache,
    logger: $logger,
    cacheTtl: 3600,
    maxRetries: 3,
    failureThreshold: 5
);

$calendar = new CalendarSelect([], $httpClient, $logger, $cache);
```text
**Migration Guide**:

- Created comprehensive UPGRADE.md with examples
- Backward compatibility maintained - no breaking changes
- Step-by-step migration instructions
- Troubleshooting section for common issues
- Performance improvement guidelines

**Benefits**:

- **Reliability**: Automatic retries handle transient failures
- **Resilience**: Circuit breaker prevents cascading failures
- **Performance**: Caching reduces API calls by 80-95%
- **Observability**: Logging provides full request/response visibility
- **Production-Ready**: Single factory method creates fully configured client
- **Type-Safe**: PHPStan Level 10 ensures no type errors

**Next Steps** (Optional):

- Add comprehensive examples to README
- Performance benchmarking report
- Integration tests with live API endpoints

---

### Change Log

**Version 2.0 (2025-11-15)**:

- Added comprehensive PSR-3 (Logging) implementation plan
- Added comprehensive PSR-6/PSR-16 (Caching) implementation plan
- Added Phase 5 (Logging Integration)
- Added Phase 6 (Caching Integration)
- Added Phase 7 (Middleware & Polish)
- Added LoggingHttpClient decorator implementation
- Added CachingHttpClient decorator implementation
- Added ArrayCache PSR-16 implementation
- Added CircuitBreakerHttpClient pattern
- Updated composer.json recommendations for all PSR standards
- Added comprehensive summary and next steps
- Added success metrics and benchmarking criteria

**Version 1.0 (2025-11-15)**:

- Initial PSR-18 HTTP Client implementation plan
- Basic PSR-7/PSR-17 recommendations
- HTTP client abstraction layer design
