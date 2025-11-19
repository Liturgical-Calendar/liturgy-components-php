<?php

namespace LiturgicalCalendar\Components\Tests;

use PHPUnit\Framework\TestCase;
use LiturgicalCalendar\Components\ApiClient;
use LiturgicalCalendar\Components\CalendarRequest;
use LiturgicalCalendar\Components\Http\HttpClientInterface;
use LiturgicalCalendar\Components\Cache\ArrayCache;
use Psr\Log\LoggerInterface;

class ApiClientTest extends TestCase
{
    private const TEST_API_URL = 'https://test-api.example.com';

    protected function setUp(): void
    {
        // Reset singleton before each test to ensure test isolation
        ApiClient::resetForTesting();
    }

    protected function tearDown(): void
    {
        // Cleanup after each test
        ApiClient::resetForTesting();
    }

    public function testGetInstanceReturnsSingleton()
    {
        $instance1 = ApiClient::getInstance(['apiUrl' => self::TEST_API_URL]);
        $instance2 = ApiClient::getInstance();

        $this->assertSame($instance1, $instance2, 'getInstance() should return the same singleton instance');
    }

    public function testGetInstanceIgnoresParametersAfterFirstCall()
    {
        $cache1 = new ArrayCache();
        $cache2 = new ArrayCache();

        // First call with cache1
        ApiClient::getInstance(['apiUrl' => self::TEST_API_URL, 'cache' => $cache1]);

        // Second call with different parameters should be ignored
        ApiClient::getInstance(['apiUrl' => 'https://different-url.com', 'cache' => $cache2]);

        // Verify original URL is used
        $this->assertEquals(self::TEST_API_URL, ApiClient::getApiUrl());

        // Verify original cache is used
        $this->assertSame($cache1, ApiClient::getCache());
    }

    public function testIsInitializedReturnsFalseBeforeInitialization()
    {
        $this->assertFalse(ApiClient::isInitialized(), 'isInitialized() should return false before getInstance() is called');
    }

    public function testIsInitializedReturnsTrueAfterInitialization()
    {
        ApiClient::getInstance(['apiUrl' => self::TEST_API_URL]);

        $this->assertTrue(ApiClient::isInitialized(), 'isInitialized() should return true after getInstance() is called');
    }

    public function testGetApiUrlReturnsNullBeforeInitialization()
    {
        $this->assertNull(ApiClient::getApiUrl(), 'getApiUrl() should return null before initialization');
    }

    public function testGetApiUrlReturnsConfiguredUrl()
    {
        ApiClient::getInstance(['apiUrl' => self::TEST_API_URL]);

        $this->assertEquals(self::TEST_API_URL, ApiClient::getApiUrl());
    }

    public function testGetApiUrlReturnsDefaultUrlWhenNotProvided()
    {
        ApiClient::getInstance();

        $this->assertEquals('https://litcal.johnromanodorazio.com/api/dev', ApiClient::getApiUrl());
    }

    public function testGetApiUrlTrimsTrailingSlash()
    {
        ApiClient::getInstance(['apiUrl' => self::TEST_API_URL . '/']);

        $this->assertEquals(self::TEST_API_URL, ApiClient::getApiUrl(), 'API URL should have trailing slash trimmed');
    }

    public function testGetHttpClientReturnsNullBeforeInitialization()
    {
        $this->assertNull(ApiClient::getHttpClient(), 'getHttpClient() should return null before initialization');
    }

    public function testGetHttpClientReturnsConfiguredClient()
    {
        $httpClient = $this->createMock(HttpClientInterface::class);

        ApiClient::getInstance(['httpClient' => $httpClient]);

        $this->assertSame($httpClient, ApiClient::getHttpClient());
    }

    public function testGetHttpClientCreatesDefaultClientWhenNotProvided()
    {
        ApiClient::getInstance();

        $this->assertInstanceOf(HttpClientInterface::class, ApiClient::getHttpClient());
    }

    public function testGetCacheReturnsNullBeforeInitialization()
    {
        $this->assertNull(ApiClient::getCache(), 'getCache() should return null before initialization');
    }

    public function testGetCacheReturnsConfiguredCache()
    {
        $cache = new ArrayCache();

        ApiClient::getInstance(['cache' => $cache]);

        $this->assertSame($cache, ApiClient::getCache());
    }

    public function testGetCacheReturnsNullWhenNotProvided()
    {
        ApiClient::getInstance();

        $this->assertNull(ApiClient::getCache());
    }

    public function testGetLoggerReturnsNullBeforeInitialization()
    {
        $this->assertNull(ApiClient::getLogger(), 'getLogger() should return null before initialization');
    }

    public function testGetLoggerReturnsConfiguredLogger()
    {
        $logger = $this->createMock(LoggerInterface::class);

        ApiClient::getInstance(['logger' => $logger]);

        $this->assertSame($logger, ApiClient::getLogger());
    }

    public function testGetLoggerReturnsNullLoggerWhenNotProvided()
    {
        ApiClient::getInstance();

        $logger = ApiClient::getLogger();
        $this->assertInstanceOf(\Psr\Log\LoggerInterface::class, $logger);
    }

    public function testGetCacheTtlReturnsNullBeforeInitialization()
    {
        $this->assertNull(ApiClient::getCacheTtl(), 'getCacheTtl() should return null before initialization');
    }

    public function testGetCacheTtlReturnsConfiguredValue()
    {
        $ttl = 3600;

        ApiClient::getInstance(['cacheTtl' => $ttl]);

        $this->assertEquals($ttl, ApiClient::getCacheTtl());
    }

    public function testGetCacheTtlReturnsDefaultWhenNotProvided()
    {
        ApiClient::getInstance();

        $this->assertEquals(86400, ApiClient::getCacheTtl(), 'Default cache TTL should be 24 hours (86400 seconds)');
    }

    public function testCreateCalendarRequestThrowsExceptionWhenNotInitialized()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ApiClient must be initialized');

        ApiClient::createCalendarRequest();
    }

    public function testCreateCalendarRequestReturnsCalendarRequestInstance()
    {
        ApiClient::getInstance(['apiUrl' => self::TEST_API_URL]);

        $request = ApiClient::createCalendarRequest();

        $this->assertInstanceOf(CalendarRequest::class, $request);
    }

    public function testResetForTestingClearsSingleton()
    {
        ApiClient::getInstance(['apiUrl' => self::TEST_API_URL]);

        $this->assertTrue(ApiClient::isInitialized());

        ApiClient::resetForTesting();

        $this->assertFalse(ApiClient::isInitialized());
        $this->assertNull(ApiClient::getApiUrl());
        $this->assertNull(ApiClient::getHttpClient());
        $this->assertNull(ApiClient::getCache());
        $this->assertNull(ApiClient::getLogger());
        $this->assertNull(ApiClient::getCacheTtl());
    }

    public function testFullConfiguration()
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $cache      = new ArrayCache();
        $logger     = $this->createMock(LoggerInterface::class);
        $ttl        = 7200;

        // Suppress double-wrapping warning for this test (intentional test scenario)
        set_error_handler(function () {
            return true;
        }, E_USER_WARNING);

        ApiClient::getInstance([
            'apiUrl'     => self::TEST_API_URL,
            'httpClient' => $httpClient,
            'cache'      => $cache,
            'logger'     => $logger,
            'cacheTtl'   => $ttl
        ]);

        restore_error_handler();

        $this->assertEquals(self::TEST_API_URL, ApiClient::getApiUrl());
        $this->assertSame($httpClient, ApiClient::getHttpClient());
        $this->assertSame($cache, ApiClient::getCache());
        $this->assertSame($logger, ApiClient::getLogger());
        $this->assertEquals($ttl, ApiClient::getCacheTtl());
    }

    public function testMinimalConfiguration()
    {
        ApiClient::getInstance();

        // Should use all defaults
        $this->assertEquals('https://litcal.johnromanodorazio.com/api/dev', ApiClient::getApiUrl());
        $this->assertInstanceOf(HttpClientInterface::class, ApiClient::getHttpClient());
        $this->assertNull(ApiClient::getCache());
        $this->assertInstanceOf(\Psr\Log\LoggerInterface::class, ApiClient::getLogger());
        $this->assertEquals(86400, ApiClient::getCacheTtl());
    }

    public function testPartialConfigurationWithUrlOnly()
    {
        ApiClient::getInstance(['apiUrl' => self::TEST_API_URL]);

        $this->assertEquals(self::TEST_API_URL, ApiClient::getApiUrl());
        $this->assertInstanceOf(HttpClientInterface::class, ApiClient::getHttpClient());
    }

    public function testPartialConfigurationWithHttpClientOnly()
    {
        $httpClient = $this->createMock(HttpClientInterface::class);

        ApiClient::getInstance(['httpClient' => $httpClient]);

        $this->assertSame($httpClient, ApiClient::getHttpClient());
        $this->assertEquals('https://litcal.johnromanodorazio.com/api/dev', ApiClient::getApiUrl());
    }

    public function testWarnsAboutDoubleWrappingWhenBothClientAndCacheProvided()
    {
        $httpClient     = $this->createMock(HttpClientInterface::class);
        $cache          = new ArrayCache();
        $warningIssued  = false;
        $warningMessage = '';

        // Set up error handler to catch the warning
        set_error_handler(function (int $_errno, string $errstr) use (&$warningIssued, &$warningMessage): bool {
            $warningIssued  = true;
            $warningMessage = $errstr;
            return true; // Suppress the warning
        }, E_USER_WARNING);

        ApiClient::getInstance(['httpClient' => $httpClient, 'cache' => $cache]);

        restore_error_handler();

        $this->assertTrue($warningIssued, 'Expected warning was not issued');
        $this->assertStringContainsString('cache/logger configuration will be ignored', $warningMessage);
    }

    public function testWarnsAboutDoubleWrappingWhenBothClientAndLoggerProvided()
    {
        $httpClient     = $this->createMock(HttpClientInterface::class);
        $logger         = $this->createMock(LoggerInterface::class);
        $warningIssued  = false;
        $warningMessage = '';

        // Set up error handler to catch the warning
        set_error_handler(function (int $_errno, string $errstr) use (&$warningIssued, &$warningMessage): bool {
            $warningIssued  = true;
            $warningMessage = $errstr;
            return true; // Suppress the warning
        }, E_USER_WARNING);

        ApiClient::getInstance(['httpClient' => $httpClient, 'logger' => $logger]);

        restore_error_handler();

        $this->assertTrue($warningIssued, 'Expected warning was not issued');
        $this->assertStringContainsString('cache/logger configuration will be ignored', $warningMessage);
    }

    public function testWarnsAboutDoubleWrappingWhenClientAndBothCacheAndLoggerProvided()
    {
        $httpClient     = $this->createMock(HttpClientInterface::class);
        $cache          = new ArrayCache();
        $logger         = $this->createMock(LoggerInterface::class);
        $warningIssued  = false;
        $warningMessage = '';

        // Set up error handler to catch the warning
        set_error_handler(function (int $_errno, string $errstr) use (&$warningIssued, &$warningMessage): bool {
            $warningIssued  = true;
            $warningMessage = $errstr;
            return true; // Suppress the warning
        }, E_USER_WARNING);

        ApiClient::getInstance([
            'httpClient' => $httpClient,
            'cache'      => $cache,
            'logger'     => $logger
        ]);

        restore_error_handler();

        $this->assertTrue($warningIssued, 'Expected warning was not issued');
        $this->assertStringContainsString('cache/logger configuration will be ignored', $warningMessage);
    }
}
