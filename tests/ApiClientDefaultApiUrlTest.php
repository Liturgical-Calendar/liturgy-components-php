<?php

namespace LiturgicalCalendar\Components\Tests;

use LiturgicalCalendar\Components\ApiClient;
use PHPUnit\Framework\TestCase;

/**
 * Covers ApiClient::defaultApiUrl(), the resolver that lets LITCAL_API_URL
 * redirect the library — which is how the test suite prefers a local API over
 * the public one, and how CI or a self-hosted deployment points it elsewhere.
 *
 * Kept in its own class because every test here mutates a process-wide
 * environment variable that the PHPUnit bootstrap also sets.
 */
final class ApiClientDefaultApiUrlTest extends TestCase
{
    private const PUBLIC_API_URL = 'https://litcal.johnromanodorazio.com/api/dev';

    /** @var string|false The value on the way in, false when it was unset. */
    private string|false $originalEnv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalEnv = getenv('LITCAL_API_URL');
    }

    protected function tearDown(): void
    {
        // putenv() with no `=` unsets, which is the only way to restore a
        // variable that was not set to begin with — assigning '' would leave a
        // blank one behind and quietly change what the next test resolves.
        if (false === $this->originalEnv) {
            putenv('LITCAL_API_URL');
        } else {
            putenv('LITCAL_API_URL=' . $this->originalEnv);
        }
        parent::tearDown();
    }

    public function testFallsBackToThePublicApiWhenUnset(): void
    {
        putenv('LITCAL_API_URL');

        $this->assertSame(self::PUBLIC_API_URL, ApiClient::defaultApiUrl());
    }

    public function testUsesAConfiguredUrl(): void
    {
        putenv('LITCAL_API_URL=http://localhost:8000');

        $this->assertSame('http://localhost:8000', ApiClient::defaultApiUrl());
    }

    public function testTrimsSurroundingWhitespace(): void
    {
        putenv('LITCAL_API_URL=  http://localhost:8000  ');

        $this->assertSame('http://localhost:8000', ApiClient::defaultApiUrl());
    }

    public function testStripsTrailingSlashes(): void
    {
        putenv('LITCAL_API_URL=http://localhost:8000/api/dev///');

        $this->assertSame('http://localhost:8000/api/dev', ApiClient::defaultApiUrl());
    }

    public function testFallsBackWhenBlank(): void
    {
        putenv('LITCAL_API_URL=');

        $this->assertSame(self::PUBLIC_API_URL, ApiClient::defaultApiUrl());
    }

    public function testFallsBackWhenOnlyWhitespace(): void
    {
        putenv('LITCAL_API_URL=   ');

        $this->assertSame(self::PUBLIC_API_URL, ApiClient::defaultApiUrl());
    }

    public function testRestoringAnUnsetVariableLeavesItUnset(): void
    {
        putenv('LITCAL_API_URL');
        $this->assertFalse(getenv('LITCAL_API_URL'));

        putenv('LITCAL_API_URL=http://example.test');
        $this->assertNotFalse(getenv('LITCAL_API_URL'));

        putenv('LITCAL_API_URL');
        $this->assertFalse(getenv('LITCAL_API_URL'));
    }
}
