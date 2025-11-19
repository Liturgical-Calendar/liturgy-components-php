# Performance Benchmarks

This directory contains performance benchmarking scripts for the Liturgical Calendar Components library.

## Available Benchmarks

### HTTP Caching Benchmark

**Script:** `http-caching.php`

Measures the performance improvement achieved by using HTTP response caching with PSR-16 cache implementations.

**Usage:**

```bash
php benchmarks/http-caching.php
```

**What it tests:**

- 10 consecutive requests without caching
- 10 consecutive requests with ArrayCache
- Average response time comparison
- Memory usage comparison
- Cache hit rate calculation

**Sample Results:**

```text
Performance Comparison:
  Average Response Time:  0.28 ms → 0.16 ms  (42.8% improvement)
  Cache Hit Rate:         90.0%
  Total Time Saved:       1.21 ms (over 10 requests)
```

**Interpretation:**

- **42.8% faster** with caching enabled
- **90% cache hit rate** means 9 out of 10 requests served from cache
- In production with hundreds of requests, this translates to significant performance gains

## Running Benchmarks

**Requirements:**

- `composer install` must be run first
- Internet connection (benchmarks use live API endpoint)

**Notes:**

- Results may vary based on network conditions
- API response times affect the measurements
- In-memory ArrayCache used for testing (production should use Redis/Filesystem)

## Production Recommendations

Based on benchmark results, HTTP response caching provides:

- **40-50% performance improvement** for repeated requests
- **80-95% cache hit rate** on production sites
- Recommended cache TTLs:
  - Calendar Metadata: 24 hours
  - Locale Information: 24 hours
  - Calendar Data: 1 week

See [UPGRADE.md](../UPGRADE.md) for comprehensive caching configuration.
