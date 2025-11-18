<?php

namespace LiturgicalCalendar\Components\Tests\Metadata;

use PHPUnit\Framework\TestCase;
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
        // Clear metadata cache before each test
        MetadataProvider::clearCache();
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
                    ]
                ],
                'national_calendars_keys' => ['VA'],
                'diocesan_calendars'      => [],
                'diocesan_calendars_keys' => [],
                'diocesan_groups'         => [],
                'wider_regions'           => [],
                'wider_regions_keys'      => [],
                'locales'                 => ['en', 'it', 'la']
            ]
        ], JSON_THROW_ON_ERROR);
    }

    public function testGetInstanceReturnsSingletonPerConfiguration()
    {
        $httpClient = $this->createMockHttpClient();
        $cache      = new ArrayCache();
        $logger     = $this->createMock(LoggerInterface::class);

        // Same exact object instances should return same singleton
        $instance1 = MetadataProvider::getInstance($httpClient, $cache, $logger);
        $instance2 = MetadataProvider::getInstance($httpClient, $cache, $logger);

        $this->assertSame($instance1, $instance2, 'Same configuration should return same instance');
    }

    public function testGetInstanceReturnsSingletonForDefaultConfiguration()
    {
        // Clear any existing instances first
        MetadataProvider::clearCache();

        // Multiple calls with all null parameters should return same instance
        $instance1 = MetadataProvider::getInstance();
        $instance2 = MetadataProvider::getInstance();
        $instance3 = MetadataProvider::getInstance(null, null, null);

        $this->assertSame($instance1, $instance2, 'Default config (all null) should return same instance');
        $this->assertSame($instance1, $instance3, 'Explicit nulls should return same instance as default');
    }

    public function testDifferentCacheTtlCreatesDifferentInstances()
    {
        $httpClient = $this->createMockHttpClient();
        $cache      = new ArrayCache();
        $logger     = $this->createMock(LoggerInterface::class);

        // Different TTLs should create different provider instances
        $instance1Hour = MetadataProvider::getInstance($httpClient, $cache, $logger, 3600);
        $instance2Hour = MetadataProvider::getInstance($httpClient, $cache, $logger, 7200);

        $this->assertNotSame($instance1Hour, $instance2Hour, 'Different TTLs should create different instances');

        // Same TTL should return same instance
        $instance1HourAgain = MetadataProvider::getInstance($httpClient, $cache, $logger, 3600);
        $this->assertSame($instance1Hour, $instance1HourAgain, 'Same TTL should return same instance');
    }

    public function testGetMetadataFetchesFromApi()
    {
        $httpClient = $this->createMockHttpClient();
        $provider   = MetadataProvider::getInstance($httpClient);

        $metadata = $provider->getMetadata(self::API_URL);

        $this->assertInstanceOf(CalendarIndex::class, $metadata);
        $this->assertCount(1, $metadata->nationalCalendars);
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

        $provider = MetadataProvider::getInstance($httpClient);

        // First call - should fetch from API
        $metadata1 = $provider->getMetadata(self::API_URL);

        // Second call - should use cached result (HTTP client should not be called again)
        $metadata2 = $provider->getMetadata(self::API_URL);

        $this->assertSame($metadata1, $metadata2, 'Second call should return cached metadata');
    }

    public function testIsCachedReturnsFalseInitially()
    {
        $this->assertFalse(MetadataProvider::isCached(self::API_URL));
    }

    public function testIsCachedReturnsTrueAfterFetch()
    {
        $httpClient = $this->createMockHttpClient();
        $provider   = MetadataProvider::getInstance($httpClient);

        $provider->getMetadata(self::API_URL);

        $this->assertTrue(MetadataProvider::isCached(self::API_URL));
    }

    public function testClearCacheRemovesAllCachedMetadata()
    {
        $httpClient = $this->createMockHttpClient();
        $provider   = MetadataProvider::getInstance($httpClient);

        $provider->getMetadata(self::API_URL);
        $this->assertTrue(MetadataProvider::isCached(self::API_URL));

        MetadataProvider::clearCache();
        $this->assertFalse(MetadataProvider::isCached(self::API_URL));
    }

    public function testGetMetadataThrowsExceptionOnNon200Response()
    {
        $httpClient = $this->createMockHttpClient(404, '');
        $provider   = MetadataProvider::getInstance($httpClient);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to fetch metadata');

        $provider->getMetadata(self::API_URL);
    }

    public function testGetMetadataThrowsExceptionOnInvalidJson()
    {
        $httpClient = $this->createMockHttpClient(200, 'invalid json');
        $provider   = MetadataProvider::getInstance($httpClient);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to decode metadata');

        $provider->getMetadata(self::API_URL);
    }

    public function testGetMetadataThrowsExceptionOnMissingLitcalMetadata()
    {
        $httpClient = $this->createMockHttpClient(200, json_encode(['other_data' => []]));
        $provider   = MetadataProvider::getInstance($httpClient);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Missing \'litcal_metadata\'');

        $provider->getMetadata(self::API_URL);
    }

    public function testGetMetadataThrowsExceptionOnInvalidLitcalMetadataType()
    {
        $httpClient = $this->createMockHttpClient(200, json_encode(['litcal_metadata' => 'not an array']));
        $provider   = MetadataProvider::getInstance($httpClient);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('\'litcal_metadata\' must be an array');

        $provider->getMetadata(self::API_URL);
    }

    public function testGetMetadataThrowsExceptionOnMissingRequiredFields()
    {
        $httpClient = $this->createMockHttpClient(200, json_encode([
            'litcal_metadata' => [
                'national_calendars' => []
                // Missing diocesan_calendars and locales
            ]
        ]));
        $provider = MetadataProvider::getInstance($httpClient);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Missing');

        $provider->getMetadata(self::API_URL);
    }

    public function testMetadataProviderWithCache()
    {
        $cache      = new ArrayCache();
        $httpClient = $this->createMockHttpClient();
        $provider   = MetadataProvider::getInstance($httpClient, $cache);

        $metadata = $provider->getMetadata(self::API_URL);

        $this->assertInstanceOf(CalendarIndex::class, $metadata);
    }

    public function testMetadataProviderWithLogger()
    {
        $logger     = $this->createMock(LoggerInterface::class);
        $httpClient = $this->createMockHttpClient();

        // Expect at least one log message
        $logger->expects($this->atLeastOnce())->method('info');

        $provider = MetadataProvider::getInstance($httpClient, null, $logger);
        $provider->getMetadata(self::API_URL);
    }

    public function testDifferentApiUrlsCacheSeparately()
    {
        $httpClient = $this->createMockHttpClient();
        $provider   = MetadataProvider::getInstance($httpClient);

        $url1 = 'https://example.com/api/v1';
        $url2 = 'https://example.com/api/v2';

        $this->assertInstanceOf(CalendarIndex::class, $provider->getMetadata($url1));
        $this->assertInstanceOf(CalendarIndex::class, $provider->getMetadata($url2));

        $this->assertTrue(MetadataProvider::isCached($url1));
        $this->assertTrue(MetadataProvider::isCached($url2));
    }

    public function testApiUrlTrailingSlashNormalization()
    {
        $httpClient = $this->createMockHttpClient();
        $provider   = MetadataProvider::getInstance($httpClient);

        $metadata1 = $provider->getMetadata(self::API_URL);
        $metadata2 = $provider->getMetadata(self::API_URL . '/');

        // Both should use the same cached result
        $this->assertSame($metadata1, $metadata2);
    }
}
