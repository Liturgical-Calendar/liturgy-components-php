<?php

namespace LiturgicalCalendar\Components\Tests\Http;

use PHPUnit\Framework\TestCase;
use LiturgicalCalendar\Components\Http\CachingHttpClient;
use LiturgicalCalendar\Components\Http\HttpClientInterface;
use LiturgicalCalendar\Components\Cache\ArrayCache;
use Psr\SimpleCache\CacheInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;

/**
 * Test suite for CachingHttpClient decorator
 */
class CachingHttpClientTest extends TestCase
{
    private HttpClientInterface $mockClient;
    private CacheInterface $cache;
    private LoggerInterface $mockLogger;

    protected function setUp(): void
    {
        $this->mockClient = $this->createMock(HttpClientInterface::class);
        $this->cache      = new ArrayCache();
        $this->mockLogger = $this->createMock(LoggerInterface::class);
    }

    public function testGetRequestCachesMiss(): void
    {
        $url          = 'https://example.com/api/data';
        $responseBody = '{"test": "data"}';

        // Mock response
        $mockResponse = $this->createMockResponse(200, $responseBody);

        // Expect the underlying client to be called once
        $this->mockClient->expects($this->once())
            ->method('get')
            ->with($url, [])
            ->willReturn($mockResponse);

        $cachingClient = new CachingHttpClient($this->mockClient, $this->cache, 3600, $this->mockLogger);

        // First call - cache miss
        $response = $cachingClient->get($url);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($responseBody, $response->getBody()->getContents());
    }

    public function testGetRequestCacheHit(): void
    {
        $url          = 'https://example.com/api/data';
        $responseBody = '{"test": "data"}';

        // Mock response
        $mockResponse = $this->createMockResponse(200, $responseBody);

        // Expect the underlying client to be called only once
        $this->mockClient->expects($this->once())
            ->method('get')
            ->with($url, [])
            ->willReturn($mockResponse);

        $cachingClient = new CachingHttpClient($this->mockClient, $this->cache, 3600, $this->mockLogger);

        // First call - cache miss, stores in cache
        $response1 = $cachingClient->get($url);
        $this->assertEquals(200, $response1->getStatusCode());

        // Second call - cache hit, should not call underlying client again
        $response2 = $cachingClient->get($url);
        $this->assertEquals(200, $response2->getStatusCode());
        $this->assertEquals($responseBody, $response2->getBody()->getContents());
    }

    public function testGetRequestWithHeaders(): void
    {
        $url          = 'https://example.com/api/data';
        $headers      = ['Authorization' => 'Bearer token'];
        $responseBody = '{"test": "data"}';

        $mockResponse = $this->createMockResponse(200, $responseBody);

        $this->mockClient->expects($this->once())
            ->method('get')
            ->with($url, $headers)
            ->willReturn($mockResponse);

        $cachingClient = new CachingHttpClient($this->mockClient, $this->cache, 3600, $this->mockLogger);

        $response = $cachingClient->get($url, $headers);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testNonSuccessResponsesNotCached(): void
    {
        $url = 'https://example.com/api/data';

        // Mock 404 response
        $mockResponse = $this->createMockResponse(404, 'Not Found');

        // Expect the underlying client to be called twice (not cached)
        $this->mockClient->expects($this->exactly(2))
            ->method('get')
            ->with($url, [])
            ->willReturn($mockResponse);

        $cachingClient = new CachingHttpClient($this->mockClient, $this->cache, 3600, $this->mockLogger);

        // First call - 404 should not be cached
        $response1 = $cachingClient->get($url);
        $this->assertEquals(404, $response1->getStatusCode());

        // Second call - should hit underlying client again
        $response2 = $cachingClient->get($url);
        $this->assertEquals(404, $response2->getStatusCode());
    }

    public function testPostRequestsNotCached(): void
    {
        $url          = 'https://example.com/api/data';
        $body         = ['key' => 'value'];
        $responseBody = '{"result": "success"}';

        $mockResponse = $this->createMockResponse(200, $responseBody);

        // Expect the underlying client to be called twice
        $this->mockClient->expects($this->exactly(2))
            ->method('post')
            ->with($url, $body, [])
            ->willReturn($mockResponse);

        $cachingClient = new CachingHttpClient($this->mockClient, $this->cache, 3600, $this->mockLogger);

        // POST requests should never be cached
        $response1 = $cachingClient->post($url, $body);
        $this->assertEquals(200, $response1->getStatusCode());

        $response2 = $cachingClient->post($url, $body);
        $this->assertEquals(200, $response2->getStatusCode());
    }

    public function testCacheTtlRespected(): void
    {
        $url          = 'https://example.com/api/data';
        $responseBody = '{"test": "data"}';

        $mockResponse = $this->createMockResponse(200, $responseBody);

        // Expect two calls - one initial, one after cache expiry
        $this->mockClient->expects($this->exactly(2))
            ->method('get')
            ->with($url, [])
            ->willReturn($mockResponse);

        // Use 1 second TTL for testing
        $cachingClient = new CachingHttpClient($this->mockClient, $this->cache, 1, $this->mockLogger);

        // First call - cache miss
        $response1 = $cachingClient->get($url);
        $this->assertEquals(200, $response1->getStatusCode());

        // Wait for cache to expire
        sleep(2);

        // Second call - cache expired, should hit underlying client
        $response2 = $cachingClient->get($url);
        $this->assertEquals(200, $response2->getStatusCode());
    }

    public function testDifferentUrlsCachedSeparately(): void
    {
        $url1  = 'https://example.com/api/data1';
        $url2  = 'https://example.com/api/data2';
        $body1 = '{"data": 1}';
        $body2 = '{"data": 2}';

        $mockResponse1 = $this->createMockResponse(200, $body1);
        $mockResponse2 = $this->createMockResponse(200, $body2);

        $this->mockClient->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(function ($url) use ($url1, $url2, $mockResponse1, $mockResponse2) {
                return $url === $url1 ? $mockResponse1 : $mockResponse2;
            });

        $cachingClient = new CachingHttpClient($this->mockClient, $this->cache, 3600, $this->mockLogger);

        $response1 = $cachingClient->get($url1);
        $this->assertEquals($body1, $response1->getBody()->getContents());

        $response2 = $cachingClient->get($url2);
        $this->assertEquals($body2, $response2->getBody()->getContents());
    }

    public function testLogsCacheHit(): void
    {
        $url          = 'https://example.com/api/data';
        $responseBody = '{"test": "data"}';

        $mockResponse = $this->createMockResponse(200, $responseBody);

        $this->mockClient->expects($this->once())
            ->method('get')
            ->willReturn($mockResponse);

        // Track debug log calls
        $debugCalls = [];
        $this->mockLogger->expects($this->exactly(3))
            ->method('debug')
            ->willReturnCallback(function ($message, $context) use (&$debugCalls) {
                $debugCalls[] = $message;
            });

        $cachingClient = new CachingHttpClient($this->mockClient, $this->cache, 3600, $this->mockLogger);

        // First call - cache miss
        $cachingClient->get($url);

        // Second call - cache hit
        $cachingClient->get($url);

        // Verify log messages
        $this->assertContains('Cache miss', $debugCalls);
        $this->assertContains('Cache hit', $debugCalls);
        $this->assertContains('Response cached', $debugCalls);
    }

    /**
     * Helper method to create mock response
     */
    private function createMockResponse(int $statusCode, string $body): ResponseInterface
    {
        $mockStream = $this->createMock(StreamInterface::class);
        $mockStream->method('getContents')->willReturn($body);
        // rewind() has void return type, so don't specify a return value
        $mockStream->method('rewind');

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn($statusCode);
        $mockResponse->method('getBody')->willReturn($mockStream);
        $mockResponse->method('getHeaders')->willReturn([]);

        return $mockResponse;
    }
}
