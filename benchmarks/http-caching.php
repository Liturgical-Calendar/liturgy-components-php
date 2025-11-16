<?php

/**
 * Performance Benchmark: HTTP Caching
 *
 * This script benchmarks the performance improvement achieved by using
 * HTTP response caching with the PSR-16 ArrayCache implementation.
 *
 * Usage:
 *   php benchmarks/http-caching.php
 */

require __DIR__ . '/../vendor/autoload.php';

use LiturgicalCalendar\Components\CalendarSelect;
use LiturgicalCalendar\Components\Http\HttpClientFactory;
use LiturgicalCalendar\Components\Cache\ArrayCache;

// Configuration
const ITERATIONS = 10;
const API_URL    = 'https://litcal.johnromanodorazio.com/api/dev';

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║  HTTP Response Caching Performance Benchmark                  ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

echo "Configuration:\n";
echo '  - API Endpoint: ' . API_URL . "\n";
echo '  - Iterations: ' . ITERATIONS . "\n";
echo "  - Component: CalendarSelect (fetches /calendars metadata)\n\n";

// ============================================================================
// Test 1: Without Caching
// ============================================================================

echo "┌────────────────────────────────────────────────────────────────┐\n";
echo "│ Test 1: WITHOUT Caching                                        │\n";
echo "└────────────────────────────────────────────────────────────────┘\n\n";

$timesWithoutCache  = [];
$memoryWithoutCache = [];

for ($i = 1; $i <= ITERATIONS; $i++) {
    // Create new instance each iteration (no caching)
    $httpClient = HttpClientFactory::create();
    $calendar   = new CalendarSelect(['url' => API_URL], $httpClient);

    $memoryBefore = memory_get_usage();
    $startTime    = microtime(true);

    try {
        $html        = $calendar->getSelect();
        $endTime     = microtime(true);
        $memoryAfter = memory_get_usage();

        $duration   = ( $endTime - $startTime ) * 1000; // Convert to milliseconds
        $memoryUsed = ( $memoryAfter - $memoryBefore ) / 1024; // Convert to KB

        $timesWithoutCache[]  = $duration;
        $memoryWithoutCache[] = $memoryUsed;

        echo sprintf("  Request %2d: %7.2f ms | Memory: %7.2f KB\n", $i, $duration, $memoryUsed);
    } catch (\Exception $e) {
        echo "  Request $i: ERROR - " . $e->getMessage() . "\n";
    }

    // Small delay to avoid rate limiting
    usleep(100000); // 100ms
}

if ($timesWithoutCache === []) {
    echo "\nNo successful requests in Test 1; cannot compute averages.\n";
    exit(1);
}

$avgTimeWithoutCache   = array_sum($timesWithoutCache) / count($timesWithoutCache);
$avgMemoryWithoutCache = array_sum($memoryWithoutCache) / count($memoryWithoutCache);

echo "\n  Average Time:   " . sprintf("%7.2f ms\n", $avgTimeWithoutCache);
echo '  Average Memory: ' . sprintf("%7.2f KB\n", $avgMemoryWithoutCache);

// ============================================================================
// Test 2: With Caching (ArrayCache)
// ============================================================================

echo "\n┌────────────────────────────────────────────────────────────────┐\n";
echo "│ Test 2: WITH Caching (ArrayCache)                             │\n";
echo "└────────────────────────────────────────────────────────────────┘\n\n";

$timesWithCache  = [];
$memoryWithCache = [];
$cacheHits       = 0;

// Create cache and HTTP client once
$cache      = new ArrayCache();
$httpClient = HttpClientFactory::create();
$calendar   = new CalendarSelect(['url' => API_URL], $httpClient, null, $cache);

for ($i = 1; $i <= ITERATIONS; $i++) {
    $memoryBefore = memory_get_usage();
    $startTime    = microtime(true);

    try {
        $html        = $calendar->getSelect();
        $endTime     = microtime(true);
        $memoryAfter = memory_get_usage();

        $duration   = ( $endTime - $startTime ) * 1000;
        $memoryUsed = ( $memoryAfter - $memoryBefore ) / 1024;

        $timesWithCache[]  = $duration;
        $memoryWithCache[] = $memoryUsed;

        $status = ( $i === 1 ) ? 'MISS' : 'HIT ';
        if ($i > 1) {
            $cacheHits++;
        }

        echo sprintf("  Request %2d: %7.2f ms | Memory: %7.2f KB | Cache: %s\n", $i, $duration, $memoryUsed, $status);
    } catch (\Exception $e) {
        echo "  Request $i: ERROR - " . $e->getMessage() . "\n";
    }
}

if ($timesWithCache === []) {
    echo "\nNo successful requests in Test 2; cannot compute averages.\n";
    exit(1);
}

$avgTimeWithCache   = array_sum($timesWithCache) / count($timesWithCache);
$avgMemoryWithCache = array_sum($memoryWithCache) / count($memoryWithCache);
$cacheHitRate       = ( $cacheHits / ITERATIONS ) * 100;

echo "\n  Average Time:   " . sprintf("%7.2f ms\n", $avgTimeWithCache);
echo '  Average Memory: ' . sprintf("%7.2f KB\n", $avgMemoryWithCache);
echo '  Cache Hit Rate: ' . sprintf("%6.1f%%\n", $cacheHitRate);

// ============================================================================
// Performance Comparison
// ============================================================================

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║  Performance Comparison                                        ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$timeImprovement   = ( ( $avgTimeWithoutCache - $avgTimeWithCache ) / $avgTimeWithoutCache ) * 100;
$memoryImprovement = ( ( $avgMemoryWithoutCache - $avgMemoryWithCache ) / $avgMemoryWithoutCache ) * 100;

$totalTimeWithoutCache = array_sum($timesWithoutCache);
$totalTimeWithCache    = array_sum($timesWithCache);
$totalTimeSaved        = $totalTimeWithoutCache - $totalTimeWithCache;

echo "  Metric                    | Without Cache | With Cache    | Improvement\n";
echo "  ──────────────────────────┼───────────────┼───────────────┼─────────────\n";
echo sprintf(
    "  Average Response Time     | %9.2f ms  | %9.2f ms  | %6.1f%%\n",
    $avgTimeWithoutCache,
    $avgTimeWithCache,
    $timeImprovement
);
echo sprintf(
    "  Total Time (%2d requests)  | %9.2f ms  | %9.2f ms  | %6.1f%%\n",
    ITERATIONS,
    $totalTimeWithoutCache,
    $totalTimeWithCache,
    $timeImprovement
);
echo sprintf(
    "  Average Memory Usage      | %9.2f KB  | %9.2f KB  | %6.1f%%\n",
    $avgMemoryWithoutCache,
    $avgMemoryWithCache,
    $memoryImprovement
);
echo "\n  Total Time Saved: " . sprintf('%.2f ms', $totalTimeSaved) . "\n";
echo '  Cache Hit Rate:   ' . sprintf('%.1f%%', $cacheHitRate) . "\n";

// ============================================================================
// Recommendations
// ============================================================================

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║  Recommendations                                               ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

if ($timeImprovement > 50) {
    echo "  ✅ Excellent performance improvement with caching!\n";
    echo "     HTTP response caching is highly recommended for production.\n\n";
} elseif ($timeImprovement > 20) {
    echo "  ✅ Good performance improvement with caching.\n";
    echo "     Consider enabling caching in production.\n\n";
} else {
    echo "  ⚠️  Moderate performance improvement.\n";
    echo "     Network conditions or API response times may vary.\n\n";
}

echo "  Recommended Cache TTLs:\n";
echo "    - Calendar Metadata: 24 hours (86400s)\n";
echo "    - Locale Information: 24 hours (86400s)\n";
echo "    - Calendar Data:     1 week (604800s)\n\n";

echo "  For production deployments, consider:\n";
echo "    - Redis or Memcached for persistent caching\n";
echo "    - Symfony Cache with filesystem adapter\n";
echo "    - PSR-16 compatible cache of your choice\n\n";

echo "  See UPGRADE.md for comprehensive caching documentation.\n\n";
