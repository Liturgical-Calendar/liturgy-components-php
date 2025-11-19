<?php

namespace LiturgicalCalendar\Components\Tests\Metadata;

use PHPUnit\Framework\TestCase;
use LiturgicalCalendar\Components\ApiClient;
use LiturgicalCalendar\Components\Metadata\MetadataProvider;
use LiturgicalCalendar\Components\Models\Index\CalendarIndex;
use LiturgicalCalendar\Components\Http\HttpClientInterface;
use LiturgicalCalendar\Components\Cache\ArrayCache;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;

class MetadataProviderTest extends TestCase
{
    private const API_URL = 'https://litcal.johnromanodorazio.com/api/dev';

    protected function setUp(): void
    {
        // Reset singletons and cache before each test to ensure test isolation
        MetadataProvider::resetForTesting();
        ApiClient::resetForTesting();
    }

    /**
     * Create a mock HTTP client that returns valid metadata
     */
    private function createMockHttpClient(int $statusCode = 200, ?string $responseBody = null): HttpClientInterface
    {
        if ($responseBody === null) {
            $responseBody = $this->getValidMetadataJson();
        }

        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn($responseBody);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getBody')->willReturn($stream);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('get')->willReturn($response);

        return $httpClient;
    }

    /**
     * Get valid metadata JSON response
     */
    private function getValidMetadataJson(): string
    {
        return json_encode([
            'litcal_metadata' => [
                'national_calendars'      => [
                    [
                        'calendar_id' => 'VA',
                        'locales'     => ['en', 'it', 'la'],
                        'missals'     => ['EDITIO_TYPICA_1970'],
                        'settings'    => [
                            'epiphany'               => 'JAN6',
                            'ascension'              => 'THURSDAY',
                            'corpus_christi'         => 'THURSDAY',
                            'eternal_high_priest'    => false,
                            'holydays_of_obligation' => []
                        ]
                    ],
                    [
                        'calendar_id' => 'US',
                        'locales'     => ['en'],
                        'missals'     => ['USA_EDITION_2011'],
                        'settings'    => [
                            'epiphany'               => 'SUNDAY_JAN2_JAN8',
                            'ascension'              => 'SUNDAY',
                            'corpus_christi'         => 'SUNDAY',
                            'eternal_high_priest'    => true,
                            'holydays_of_obligation' => []
                        ],
                        'dioceses'    => ['boston_us', 'newyork_us', 'chicago_us']
                    ]
                ],
                'national_calendars_keys' => ['VA', 'US'],
                'diocesan_calendars'      => [],
                'diocesan_calendars_keys' => [],
                'diocesan_groups'         => [],
                'wider_regions'           => [],
                'wider_regions_keys'      => [],
                'locales'                 => ['en', 'it', 'la']
            ]
        ], JSON_THROW_ON_ERROR);
    }

    public function testGetInstanceReturnsSingleton()
    {
        $httpClient = $this->createMockHttpClient();
        $cache      = new ArrayCache();
        $logger     = $this->createMock(LoggerInterface::class);

        // Suppress double-wrapping warning for this test (intentional test scenario)
        set_error_handler(function () {
            return true;
        }, E_USER_WARNING);

        // First call initializes the singleton
        $instance1 = MetadataProvider::getInstance(self::API_URL, $httpClient, $cache, $logger);
        // Subsequent calls return the same instance (parameters are ignored)
        $instance2 = MetadataProvider::getInstance(self::API_URL, $httpClient, $cache, $logger);

        restore_error_handler();

        $this->assertSame($instance1, $instance2, 'Should return same singleton instance');
    }

    public function testGetInstanceReturnsSingletonForDefaultConfiguration()
    {
        // Multiple calls should return same instance
        $instance1 = MetadataProvider::getInstance();
        $instance2 = MetadataProvider::getInstance();
        $instance3 = MetadataProvider::getInstance(null, null, null, null);

        $this->assertSame($instance1, $instance2, 'Default config should return same instance');
        $this->assertSame($instance1, $instance3, 'Explicit nulls should return same instance as default');
    }

    public function testSubsequentGetInstanceCallsIgnoreParameters()
    {
        $httpClient1 = $this->createMockHttpClient();
        $httpClient2 = $this->createMockHttpClient();

        // First call initializes with httpClient1
        $instance1 = MetadataProvider::getInstance(self::API_URL, $httpClient1);
        // Second call with different httpClient should return same instance
        $instance2 = MetadataProvider::getInstance('https://different-url.com', $httpClient2);

        $this->assertSame($instance1, $instance2, 'Subsequent calls should ignore parameters and return same instance');
        $this->assertSame(self::API_URL, MetadataProvider::getApiUrl(), 'API URL should remain the one from first call');
    }

    public function testGetMetadataFetchesFromApi()
    {
        $httpClient = $this->createMockHttpClient();
        $provider   = MetadataProvider::getInstance(self::API_URL, $httpClient);

        $metadata = $provider->getMetadata();

        $this->assertInstanceOf(CalendarIndex::class, $metadata);
        $this->assertCount(2, $metadata->nationalCalendars);
        $this->assertCount(3, $metadata->locales);
    }

    public function testGetMetadataCachesResult()
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $stream     = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn($this->getValidMetadataJson());

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($stream);

        // Expect get() to be called only once
        $httpClient->expects($this->once())
                   ->method('get')
                   ->with(self::API_URL . '/calendars')
                   ->willReturn($response);

        $provider = MetadataProvider::getInstance(self::API_URL, $httpClient);

        // First call - should fetch from API
        $metadata1 = $provider->getMetadata();

        // Second call - should use cached result (HTTP client should not be called again)
        $metadata2 = $provider->getMetadata();

        $this->assertSame($metadata1, $metadata2, 'Second call should return cached metadata');
    }

    public function testIsCachedReturnsFalseInitially()
    {
        $this->assertFalse(MetadataProvider::isCached());
    }

    public function testIsCachedReturnsTrueAfterFetch()
    {
        $httpClient = $this->createMockHttpClient();
        $provider   = MetadataProvider::getInstance(self::API_URL, $httpClient);

        $provider->getMetadata();

        $this->assertTrue(MetadataProvider::isCached());
    }

    public function testClearCacheRemovesAllCachedMetadata()
    {
        $httpClient = $this->createMockHttpClient();
        $provider   = MetadataProvider::getInstance(self::API_URL, $httpClient);

        $provider->getMetadata();
        $this->assertTrue(MetadataProvider::isCached());

        MetadataProvider::clearCache();
        $this->assertFalse(MetadataProvider::isCached());
    }

    public function testGetMetadataThrowsExceptionOnNon200Response()
    {
        $httpClient = $this->createMockHttpClient(404, '');
        $provider   = MetadataProvider::getInstance(self::API_URL, $httpClient);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to fetch metadata');

        $provider->getMetadata();
    }

    public function testGetMetadataThrowsExceptionOnInvalidJson()
    {
        $httpClient = $this->createMockHttpClient(200, 'invalid json');
        $provider   = MetadataProvider::getInstance(self::API_URL, $httpClient);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to decode metadata');

        $provider->getMetadata();
    }

    public function testGetMetadataThrowsExceptionOnMissingLitcalMetadata()
    {
        $httpClient = $this->createMockHttpClient(200, json_encode(['other_data' => []]));
        $provider   = MetadataProvider::getInstance(self::API_URL, $httpClient);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Missing \'litcal_metadata\'');

        $provider->getMetadata();
    }

    public function testGetMetadataThrowsExceptionOnInvalidLitcalMetadataType()
    {
        $httpClient = $this->createMockHttpClient(200, json_encode(['litcal_metadata' => 'not an array']));
        $provider   = MetadataProvider::getInstance(self::API_URL, $httpClient);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('\'litcal_metadata\' must be an array');

        $provider->getMetadata();
    }

    public function testGetMetadataThrowsExceptionOnMissingRequiredFields()
    {
        $httpClient = $this->createMockHttpClient(200, json_encode([
            'litcal_metadata' => [
                'national_calendars' => []
                // Missing diocesan_calendars and locales
            ]
        ]));
        $provider = MetadataProvider::getInstance(self::API_URL, $httpClient);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Missing');

        $provider->getMetadata();
    }

    public function testMetadataProviderWithCache()
    {
        $cache      = new ArrayCache();
        $httpClient = $this->createMockHttpClient();

        // Suppress double-wrapping warning for this test (intentional test scenario)
        set_error_handler(function () {
            return true;
        }, E_USER_WARNING);

        $provider = MetadataProvider::getInstance(self::API_URL, $httpClient, $cache);

        restore_error_handler();

        $metadata = $provider->getMetadata();

        $this->assertInstanceOf(CalendarIndex::class, $metadata);
    }

    public function testMetadataProviderWithLogger()
    {
        $logger     = $this->createMock(LoggerInterface::class);
        $httpClient = $this->createMockHttpClient();

        // Expect at least one log message
        $logger->expects($this->atLeastOnce())->method('info');

        // Suppress double-wrapping warning for this test (intentional test scenario)
        set_error_handler(function () {
            return true;
        }, E_USER_WARNING);

        $provider = MetadataProvider::getInstance(self::API_URL, $httpClient, null, $logger);

        restore_error_handler();

        $provider->getMetadata();
    }

    public function testApiUrlIsConfiguredCorrectly()
    {
        $httpClient = $this->createMockHttpClient();
        MetadataProvider::getInstance(self::API_URL, $httpClient);

        $this->assertSame(self::API_URL, MetadataProvider::getApiUrl());
    }

    public function testGetMetadataUrlNormalizesTrailingSlash()
    {
        $httpClient = $this->createMockHttpClient();

        // Test without trailing slash
        MetadataProvider::resetForTesting();
        MetadataProvider::getInstance(self::API_URL, $httpClient);
        $this->assertSame(self::API_URL . '/calendars', MetadataProvider::getMetadataUrl());

        // Test with trailing slash - should produce same result (no double slash)
        MetadataProvider::resetForTesting();
        MetadataProvider::getInstance(self::API_URL . '/', $httpClient);
        $this->assertSame(self::API_URL . '/calendars', MetadataProvider::getMetadataUrl());
        $this->assertStringNotContainsString('//calendars', MetadataProvider::getMetadataUrl());
    }

    public function testApiUrlTrailingSlashNormalization()
    {
        $httpClient = $this->createMockHttpClient();
        $provider   = MetadataProvider::getInstance(self::API_URL . '/', $httpClient);

        $provider->getMetadata();
        // Should strip trailing slash and cache under normalized URL
        $this->assertTrue(MetadataProvider::isCached());
    }

    public function testIsValidDioceseForNationReturnsTrueForValidDiocese()
    {
        $httpClient = $this->createMockHttpClient();
        MetadataProvider::getInstance(self::API_URL, $httpClient);

        $this->assertTrue(
            MetadataProvider::isValidDioceseForNation('boston_us', 'US'),
            'boston_us should be valid for US'
        );
        $this->assertTrue(
            MetadataProvider::isValidDioceseForNation('newyork_us', 'US'),
            'newyork_us should be valid for US'
        );
    }

    public function testIsValidDioceseForNationReturnsFalseForInvalidDiocese()
    {
        $httpClient = $this->createMockHttpClient();
        MetadataProvider::getInstance(self::API_URL, $httpClient);

        $this->assertFalse(
            MetadataProvider::isValidDioceseForNation('invalid_diocese', 'US'),
            'invalid_diocese should not be valid for US'
        );
    }

    public function testIsValidDioceseForNationReturnsFalseForInvalidNation()
    {
        $httpClient = $this->createMockHttpClient();
        MetadataProvider::getInstance(self::API_URL, $httpClient);

        $this->assertFalse(
            MetadataProvider::isValidDioceseForNation('boston_us', 'INVALID'),
            'boston_us should not be valid for non-existent nation'
        );
    }

    public function testIsValidDioceseForNationReturnsFalseForNationWithoutDioceses()
    {
        $httpClient = $this->createMockHttpClient();
        MetadataProvider::getInstance(self::API_URL, $httpClient);

        $this->assertFalse(
            MetadataProvider::isValidDioceseForNation('some_diocese', 'VA'),
            'VA nation should not have any dioceses'
        );
    }

    public function testIsValidDioceseForNationThrowsExceptionWhenNotInitialized()
    {
        // Don't initialize MetadataProvider
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('MetadataProvider must be initialized');

        MetadataProvider::isValidDioceseForNation('boston_us', 'US');
    }

    public function testWarnsAboutDoubleWrappingWhenBothClientAndCacheProvided()
    {
        $httpClient     = $this->createMockHttpClient();
        $cache          = new ArrayCache();
        $warningIssued  = false;
        $warningMessage = '';

        // Set up error handler to catch the warning
        set_error_handler(function (int $_errno, string $errstr) use (&$warningIssued, &$warningMessage): bool {
            $warningIssued  = true;
            $warningMessage = $errstr;
            return true; // Suppress the warning
        }, E_USER_WARNING);

        MetadataProvider::getInstance(self::API_URL, $httpClient, $cache);

        restore_error_handler();

        $this->assertTrue($warningIssued, 'Expected warning was not issued');
        $this->assertStringContainsString('double-wrapping', $warningMessage);
    }

    public function testWarnsAboutDoubleWrappingWhenBothClientAndLoggerProvided()
    {
        $httpClient     = $this->createMockHttpClient();
        $logger         = $this->createMock(LoggerInterface::class);
        $warningIssued  = false;
        $warningMessage = '';

        // Set up error handler to catch the warning
        set_error_handler(function (int $_errno, string $errstr) use (&$warningIssued, &$warningMessage): bool {
            $warningIssued  = true;
            $warningMessage = $errstr;
            return true; // Suppress the warning
        }, E_USER_WARNING);

        MetadataProvider::getInstance(self::API_URL, $httpClient, null, $logger);

        restore_error_handler();

        $this->assertTrue($warningIssued, 'Expected warning was not issued');
        $this->assertStringContainsString('double-wrapping', $warningMessage);
    }

    public function testWarnsAboutDoubleWrappingWhenClientAndBothCacheAndLoggerProvided()
    {
        $httpClient     = $this->createMockHttpClient();
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

        MetadataProvider::getInstance(self::API_URL, $httpClient, $cache, $logger);

        restore_error_handler();

        $this->assertTrue($warningIssued, 'Expected warning was not issued');
        $this->assertStringContainsString('double-wrapping', $warningMessage);
    }

    /**
     * @group apiclient
     */
    public function testUsesApiClientConfigurationWhenNoParametersProvided()
    {
        $httpClient = $this->createMockHttpClient();
        $cache      = new ArrayCache();
        $logger     = $this->createMock(LoggerInterface::class);

        // Suppress double-wrapping warning for this test (intentional test scenario)
        set_error_handler(function () {
            return true;
        }, E_USER_WARNING);

        // Initialize ApiClient first
        ApiClient::getInstance([
            'apiUrl'     => self::API_URL,
            'httpClient' => $httpClient,
            'cache'      => $cache,
            'logger'     => $logger,
            'cacheTtl'   => 3600
        ]);

        restore_error_handler();

        // Create MetadataProvider without parameters - should use ApiClient config
        $provider = MetadataProvider::getInstance();

        $this->assertInstanceOf(MetadataProvider::class, $provider);
        $this->assertEquals(self::API_URL, MetadataProvider::getApiUrl());
    }

    /**
     * @group apiclient
     */
    public function testExplicitParametersOverrideApiClientConfiguration()
    {
        $apiClientHttpClient = $this->createMockHttpClient();
        $explicitHttpClient  = $this->createMockHttpClient();
        $customApiUrl        = 'https://custom-api.example.com';

        // Initialize ApiClient
        ApiClient::getInstance([
            'apiUrl'     => self::API_URL,
            'httpClient' => $apiClientHttpClient
        ]);

        // Suppress double-wrapping warning (intentional for this test)
        set_error_handler(function () {
            return true;
        }, E_USER_WARNING);

        // Create MetadataProvider with explicit parameters - should override ApiClient
        MetadataProvider::getInstance(
            apiUrl: $customApiUrl,
            httpClient: $explicitHttpClient
        );

        restore_error_handler();

        // Verify explicit parameters take precedence
        $this->assertEquals($customApiUrl, MetadataProvider::getApiUrl());
    }

    /**
     * @group apiclient
     */
    public function testUsesDefaultsWhenNeitherApiClientNorParametersProvided()
    {
        // Don't initialize ApiClient
        // Create MetadataProvider without parameters
        $provider = MetadataProvider::getInstance();

        $this->assertInstanceOf(MetadataProvider::class, $provider);
        $this->assertEquals(self::API_URL, MetadataProvider::getApiUrl());
    }

    /**
     * @group apiclient
     */
    public function testApiClientCacheTtlIsRespected()
    {
        $httpClient = $this->createMockHttpClient();
        $cache      = new ArrayCache();
        $customTtl  = 7200;

        // Suppress double-wrapping warning for this test (intentional test scenario)
        set_error_handler(function () {
            return true;
        }, E_USER_WARNING);

        // Initialize ApiClient with custom TTL
        ApiClient::getInstance([
            'apiUrl'     => self::API_URL,
            'httpClient' => $httpClient,
            'cache'      => $cache,
            'cacheTtl'   => $customTtl
        ]);

        restore_error_handler();

        // Create MetadataProvider - should use ApiClient's TTL
        MetadataProvider::getInstance();

        // Verify the TTL was used (indirectly via ApiClient)
        $this->assertEquals($customTtl, ApiClient::getCacheTtl());
    }

    /**
     * @group apiclient
     */
    public function testMetadataProviderWorksWithoutApiClientInitialization()
    {
        // Ensure ApiClient is NOT initialized
        $this->assertFalse(ApiClient::isInitialized());

        $httpClient = $this->createMockHttpClient();

        // Create MetadataProvider with explicit parameters (backward compatible)
        $provider = MetadataProvider::getInstance(
            apiUrl: self::API_URL,
            httpClient: $httpClient
        );

        $metadata = $provider->getMetadata();

        $this->assertInstanceOf(CalendarIndex::class, $metadata);
        $this->assertCount(2, $metadata->nationalCalendars);
    }

    /**
     * @group apiclient
     */
    public function testApiClientApiUrlIsUsedWhenProvided()
    {
        $customApiUrl = 'https://api-client-url.example.com';
        $httpClient   = $this->createMockHttpClient();

        // Initialize ApiClient with custom URL
        ApiClient::getInstance([
            'apiUrl'     => $customApiUrl,
            'httpClient' => $httpClient
        ]);

        // Create MetadataProvider without apiUrl parameter
        MetadataProvider::getInstance();

        // Verify ApiClient URL is used
        $this->assertEquals($customApiUrl, MetadataProvider::getApiUrl());
    }

    /**
     * @group apiclient
     */
    public function testWarnsAboutDoubleWrappingWhenApiClientProvidesHttpClientAndExplicitCacheProvided()
    {
        $httpClient     = $this->createMockHttpClient();
        $cache          = new ArrayCache();
        $warningIssued  = false;
        $warningMessage = '';

        // Initialize ApiClient with httpClient
        ApiClient::getInstance([
            'apiUrl'     => self::API_URL,
            'httpClient' => $httpClient
        ]);

        // Set up error handler to catch the warning
        set_error_handler(function (int $_errno, string $errstr) use (&$warningIssued, &$warningMessage): bool {
            $warningIssued  = true;
            $warningMessage = $errstr;
            return true; // Suppress the warning
        }, E_USER_WARNING);

        // Create MetadataProvider with explicit cache (httpClient from ApiClient)
        // This should trigger warning because httpClient comes from ApiClient + explicit cache
        MetadataProvider::getInstance(cache: $cache);

        restore_error_handler();

        $this->assertTrue($warningIssued, 'Expected warning was not issued when ApiClient provides httpClient and explicit cache is passed');
        $this->assertStringContainsString('double-wrapping', $warningMessage);
        $this->assertStringContainsString('either explicitly or from ApiClient', $warningMessage);
    }

    /**
     * @group apiclient
     */
    public function testWarnsAboutDoubleWrappingWhenApiClientProvidesHttpClientAndExplicitLoggerProvided()
    {
        $httpClient     = $this->createMockHttpClient();
        $logger         = $this->createMock(LoggerInterface::class);
        $warningIssued  = false;
        $warningMessage = '';

        // Initialize ApiClient with httpClient
        ApiClient::getInstance([
            'apiUrl'     => self::API_URL,
            'httpClient' => $httpClient
        ]);

        // Set up error handler to catch the warning
        set_error_handler(function (int $_errno, string $errstr) use (&$warningIssued, &$warningMessage): bool {
            $warningIssued  = true;
            $warningMessage = $errstr;
            return true; // Suppress the warning
        }, E_USER_WARNING);

        // Create MetadataProvider with explicit logger (httpClient from ApiClient)
        // This should trigger warning because httpClient comes from ApiClient + explicit logger
        MetadataProvider::getInstance(logger: $logger);

        restore_error_handler();

        $this->assertTrue($warningIssued, 'Expected warning was not issued when ApiClient provides httpClient and explicit logger is passed');
        $this->assertStringContainsString('double-wrapping', $warningMessage);
        $this->assertStringContainsString('either explicitly or from ApiClient', $warningMessage);
    }

    /**
     * @group apiclient
     */
    public function testNoWarningWhenBothHttpClientAndCacheFromApiClient()
    {
        $httpClient    = $this->createMockHttpClient();
        $cache         = new ArrayCache();
        $warningIssued = false;

        // Initialize ApiClient with BOTH httpClient AND cache
        ApiClient::getInstance([
            'apiUrl'     => self::API_URL,
            'httpClient' => $httpClient,
            'cache'      => $cache
        ]);

        // Set up error handler to catch any warnings
        set_error_handler(function (int $_errno, string $_errstr) use (&$warningIssued): bool {
            $warningIssued = true;
            return true;
        }, E_USER_WARNING);

        // Create MetadataProvider without parameters - both come from ApiClient
        // This should NOT trigger warning (recommended pattern)
        MetadataProvider::getInstance();

        restore_error_handler();

        $this->assertFalse($warningIssued, 'Warning should NOT be issued when both httpClient and cache come from ApiClient');
    }
}
