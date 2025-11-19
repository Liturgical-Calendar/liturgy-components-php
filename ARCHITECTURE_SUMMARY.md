# API Client Architecture Summary

## Overview

This document summarizes the coordinated API client architecture for the liturgy-components-php package, designed to ensure all API-consuming components use shared HTTP client configuration.

## Architecture Diagram

```text
┌─────────────────────────────────────────────────────────────┐
│                      Application Bootstrap                  │
│                                                             │
│  ApiClient::getInstance([                                   │
│      'apiUrl' => 'https://litcal.../api/dev',               │
│      'httpClient' => $httpClient,  // optional              │
│      'cache' => $cache,            // optional              │
│      'logger' => $logger,          // optional              │
│      'cacheTtl' => 86400          // optional               │
│  ]);                                                        │
└─────────────────────────────────────────────────────────────┘
                              │
                              │ Initializes once
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                        ApiClient                            │
│                      (Singleton)                            │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐    │
│  │  Configuration (Immutable after initialization)     │    │
│  │  • HttpClient (with middleware)                     │    │
│  │  • Cache (PSR-16)                                   │    │
│  │  • Logger (PSR-3)                                   │    │
│  │  • API URL                                          │    │
│  │  • Cache TTL                                        │    │
│  └─────────────────────────────────────────────────────┘    │
│                                                             │
│  Static Methods:                                            │
│  • getHttpClient() → HttpClientInterface                    │
│  • getApiUrl() → string                                     │
│  • getCache() → CacheInterface                              │
│  • getLogger() → LoggerInterface                            │
│  • getCacheTtl() → int                                      │
│                                                             │
│  Instance Methods (Factory):                                │
│  • calendar() → CalendarRequest                             │
│  • metadata() → MetadataProvider                            │
└─────────────────────────────────────────────────────────────┘
                              │
                              │ Provides dependencies to
                              ▼
 ┌─────────────────────────────────────────────────────────┐
 │         Component Layer (uses shared config)            │
 │                                                         │
 │  ┌──────────────────────┐  ┌──────────────────────┐     │
 │  │  MetadataProvider    │  │  CalendarRequest     │     │
 │  │  (Singleton)         │  │  (Instance-based)    │     │
 │  │                      │  │                      │     │
 │  │  Fetches metadata    │  │  Fetches calendar    │     │
 │  │  from /calendars     │  │  data from /calendar │     │
 │  │                      │  │                      │     │
 │  │  Priority:           │  │  Priority:           │     │
 │  │  1. Explicit params  │  │  1. Explicit params  │     │
 │  │  2. ApiClient        │  │  2. ApiClient        │     │
 │  │  3. Defaults         │  │  3. Defaults         │     │
 │  └──────────────────────┘  └──────────────────────┘     │
 └─────────────────────────────────────────────────────────┘
                              │
                              │ Used by
                              ▼
    ┌─────────────────────────────────────────────────────────┐
    │              UI Components (unchanged)                  │
    │                                                         │
    │  • CalendarSelect                                       │
    │  • ApiOptions                                           │
    │  • WebCalendar                                          │
    │  • Locale                                               │
    └─────────────────────────────────────────────────────────┘
```

## Component Responsibilities

### 1. ApiClient (New - Singleton)

**Purpose**: Central configuration manager for all API interactions

**Responsibilities**:

- Store shared HttpClient, Cache, Logger, API URL, Cache TTL
- Provide static accessors for all dependencies
- Ensure configuration is immutable after initialization

**HttpClient Configuration Behavior**:

- If `httpClient` is provided: Use it as-is (assumes it's already decorated)
- If `httpClient` is NOT provided: Create production client using `cache`/`logger` parameters
- If BOTH `httpClient` AND `cache`/`logger` are provided: Triggers warning (likely configuration mistake)

**Key Methods**:

```php
// Initialization
ApiClient::getInstance(array $config): self

// Accessors
ApiClient::getHttpClient(): ?HttpClientInterface
ApiClient::getApiUrl(): ?string
ApiClient::getCache(): ?CacheInterface
ApiClient::getLogger(): ?LoggerInterface
ApiClient::getCacheTtl(): ?int
ApiClient::isInitialized(): bool

// Factory methods (instance)
$apiClient->calendar(): CalendarRequest
$apiClient->metadata(): MetadataProvider

// Testing
ApiClient::resetForTesting(): void
```

### 2. MetadataProvider (Modified - Singleton)

**Purpose**: Fetch and cache calendar metadata from `/calendars` endpoint

**Changes**:

- Modified `getInstance()` to check ApiClient before creating defaults
- Maintains backward compatibility - explicit params still work
- Priority: Explicit params > ApiClient > Defaults

**Behavior**:

```php
// New recommended approach (uses ApiClient)
ApiClient::getInstance(['apiUrl' => '...', 'httpClient' => $client]);
$provider = MetadataProvider::getInstance();  // No params needed!

// Old approach still works (backward compatible)
$provider = MetadataProvider::getInstance(
    apiUrl: '...',
    httpClient: $client,
    cache: $cache
);
```

### 3. CalendarRequest (New - Instance-based)

**Purpose**: Fetch calendar data from `/calendar/*` endpoints

**Characteristics**:

- Instance-based (not singleton) - allows multiple concurrent requests
- Checks ApiClient for dependencies if not explicitly provided
- Fluent chainable API for building requests
- Priority: Explicit constructor params > ApiClient > Defaults

**Behavior**:

```php
// Option 1: Via ApiClient factory method (recommended)
$apiClient = ApiClient::getInstance();
$request = $apiClient->calendar();

// Option 2: Direct instantiation with ApiClient fallback
$request = new CalendarRequest();  // Uses ApiClient if initialized

// Option 3: Explicit dependencies (testing, custom config)
$request = new CalendarRequest($httpClient, $logger, $cache, $apiUrl);
```

## Dependency Resolution Priority

All components follow the same priority chain for dependency resolution:

```text
1. Explicit Parameters (highest priority)
   ↓
2. ApiClient Configuration (if initialized)
   ↓
3. Default Values (fallback)
```

### Examples

#### Scenario 1: ApiClient initialized, explicit params provided

```php
ApiClient::getInstance(['httpClient' => $apiClient]);
$provider = MetadataProvider::getInstance(httpClient: $customClient);
// Result: Uses $customClient (explicit param wins)
```

#### Scenario 2: ApiClient initialized, no explicit params

```php
ApiClient::getInstance(['httpClient' => $apiClient]);
$provider = MetadataProvider::getInstance();
// Result: Uses $apiClient from ApiClient
```

#### Scenario 3: ApiClient not initialized

```php
$provider = MetadataProvider::getInstance();
// Result: Creates default HttpClient via HttpClientFactory::create()
```

## Benefits of This Architecture

### ✅ Single Configuration Point

#### Pattern 1: Provide pre-decorated HttpClient (RECOMMENDED)

```php
// Create production-ready HTTP client with all middleware
$httpClient = HttpClientFactory::createProductionClient(
    cache: $cache,
    logger: $logger,
    cacheTtl: 86400,
    maxRetries: 3,
    failureThreshold: 5
);

// Initialize ApiClient with decorated client
// Note: Don't also pass cache/logger here - they're already in $httpClient
ApiClient::getInstance([
    'apiUrl' => 'https://litcal.johnromanodorazio.com/api/dev',
    'httpClient' => $httpClient  // Already decorated with cache/logger
]);

// All components automatically use this configuration
$metadata = $apiClient->metadata()->getMetadata();
$calendar = $apiClient->calendar()->year(2024)->get();
```

#### Pattern 2: Let ApiClient create the HttpClient

```php
// ApiClient creates production client with these dependencies
ApiClient::getInstance([
    'apiUrl' => 'https://litcal.johnromanodorazio.com/api/dev',
    'cache' => $cache,      // Used to create production client
    'logger' => $logger,    // Used to create production client
    'cacheTtl' => 86400
]);

// All components automatically use this configuration
$metadata = $apiClient->metadata()->getMetadata();
$calendar = $apiClient->calendar()->year(2024)->get();
```

#### ⚠️ Warning: Don't Mix Both Patterns

```php
// ❌ BAD - Triggers double-wrapping warning
ApiClient::getInstance([
    'apiUrl' => 'https://...',
    'httpClient' => $preDecoratedClient,  // Already has cache/logger
    'cache' => $cache,                     // Will NOT decorate $preDecoratedClient
    'logger' => $logger                    // Will NOT decorate $preDecoratedClient
]);
// This triggers a warning because it's unclear which behavior you want
```

### ✅ No Duplicate HttpClient Configuration

- Single HttpClient instance with all middleware (caching, logging, retry, circuit breaker)
- No risk of different middleware stacks for different components
- Consistent behavior across all API requests

### ✅ Backward Compatibility

```php
// Old code still works
MetadataProvider::getInstance(
    apiUrl: 'https://...',
    httpClient: $client
);
```

### ✅ Flexibility for Testing

```php
// Test with mocks
$mockClient = new MockHttpClient();
$request = new CalendarRequest($mockClient);

// Or reset ApiClient for test isolation
ApiClient::resetForTesting();
ApiClient::getInstance(['httpClient' => $mockClient]);
```

### ✅ Future-Proof

```php
// Easy to add more API endpoint components via factory methods
$events = $apiClient->events();
$missals = $apiClient->missals();
$lectionary = $apiClient->lectionary();
```

## Migration Path

### Phase 1: Introduce ApiClient (No Breaking Changes)

- Create ApiClient class
- Update MetadataProvider to check ApiClient
- All existing code continues to work unchanged

### Phase 2: Implement CalendarRequest (Uses ApiClient)

- CalendarRequest automatically integrates with ApiClient
- Users can start using new pattern

### Phase 3: Update Documentation & Examples

- Recommend new ApiClient pattern in docs
- Update examples to use ApiClient
- Mark old pattern as "legacy but supported"

### Phase 4: (Future) Deprecation Notice

- Add deprecation notices for direct initialization (if desired)
- Give users time to migrate

## Usage Patterns

### Pattern 1: Production Application (Recommended)

```php
// bootstrap.php
use LiturgicalCalendar\Components\ApiClient;
use LiturgicalCalendar\Components\Http\HttpClientFactory;

$httpClient = HttpClientFactory::createProductionClient(
    cache: $cache,
    logger: $logger,
    cacheTtl: 3600,
    maxRetries: 3,
    failureThreshold: 5
);

ApiClient::getInstance([
    'apiUrl' => 'https://litcal.johnromanodorazio.com/api/dev',
    'httpClient' => $httpClient
]);

// Application code
$calendarSelect = new CalendarSelect();
$calendar = $apiClient->calendar()
    ->year(2024)
    ->nation('US')
    ->get();
```

### Pattern 2: Quick Prototyping (Auto-configuration)

```php
// ApiClient creates default HttpClient if not provided
ApiClient::getInstance(['apiUrl' => 'https://litcal.../api/dev']);

// Components work immediately
$metadata = $apiClient->metadata()->getMetadata();
$calendar = $apiClient->calendar()->year(2024)->get();
```

### Pattern 3: Testing (Explicit Mocks)

```php
// Test setup
ApiClient::resetForTesting();

$mockClient = new MockHttpClient();
ApiClient::getInstance([
    'apiUrl' => 'http://test-api.local',
    'httpClient' => $mockClient
]);

// All components use mock
$metadata = $apiClient->metadata()->getMetadata();
$calendar = $apiClient->calendar()->year(2024)->get();
```

### Pattern 4: Legacy/Independent Use (Backward Compatible)

```php
// Each component configured independently (still supported)
$metadata = MetadataProvider::getInstance(
    apiUrl: 'https://...',
    httpClient: $client1
);

$request = new CalendarRequest($client2, $logger, $cache, 'https://...');
```

## Implementation Timeline

See FEATURE_ROADMAP.md for detailed implementation plan:

1. **Phase 0** (Week 1): ApiClient Foundation
   - Create ApiClient singleton
   - Update MetadataProvider integration
   - Tests and documentation

1. **Phase 1** (Week 2): Core CalendarRequest
   - Implement CalendarRequest with ApiClient support
   - Fluent API, validation, tests

1. **Phase 2-6** (Weeks 3-7): Response models, caching, advanced features

## Related Documentation

- **FEATURE_ROADMAP.md** - Detailed implementation plan with all phases
- **CLAUDE.md** - Project instructions including MetadataProvider architecture
- **PSR_COMPATIBILITY.md** - PSR compliance details

---

**Document Version**: 1.0
**Created**: 2025-11-18
**Status**: Architectural Proposal
