<?php

/**
 * PHPUnit bootstrap.
 *
 * Prefers a local API over the public one.
 *
 * Most of this suite has no fixtures: every `CalendarSelect` constructed in a
 * test fetches `/calendars` over HTTP, and `CalendarSelectTest` resets the
 * metadata singleton before each test, so a run issues dozens of requests in
 * seconds. The public instance rate-limits that, and the whole suite then fails
 * with connection timeouts that look exactly like a code regression. The local
 * API does not rate-limit.
 *
 * The probe is deliberate rather than unconditional: pointing the suite at
 * localhost when nothing is listening would trade one confusing failure mode
 * for another. So it asks first, briefly, and falls back to whatever
 * `ApiClient::defaultApiUrl()` resolves to otherwise.
 *
 * Set `LITCAL_API_URL` yourself to override both — it wins over the probe.
 */

require_once __DIR__ . '/../vendor/autoload.php';

( static function (): void {
    if (is_string(getenv('LITCAL_API_URL')) && '' !== trim((string) getenv('LITCAL_API_URL'))) {
        return; // explicitly configured; do not second-guess it
    }

    $candidates = ['http://localhost:8000', 'http://127.0.0.1:8000'];

    foreach ($candidates as $candidate) {
        $context = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'timeout'       => 2,
                'ignore_errors' => true
            ]
        ]);

        $body = @file_get_contents($candidate . '/calendars', false, $context);
        if (false === $body) {
            continue;
        }

        // A 404 from some other server listening on the port is not the API.
        // Requiring the payload to parse as an object carrying the calendar
        // index is what tells the two apart.
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            continue;
        }
        $index = $decoded['litcal_metadata'] ?? $decoded;
        if (!is_array($index) || !isset($index['national_calendars'])) {
            continue;
        }

        putenv('LITCAL_API_URL=' . $candidate);
        fwrite(STDERR, "Using local API at {$candidate}\n");
        return;
    }
} )();
