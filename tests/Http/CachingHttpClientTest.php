<?php

namespace LiturgicalCalendar\Components\Tests\Http;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
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
    /** @var MockObject&HttpClientInterface */
    private $mockClient;

    private CacheInterface $cache;

    /** @var MockObject&LoggerInterface */
    private $mockLogger;

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
            ->willReturnCallback(function (string $url) use ($url1, $mockResponse1, $mockResponse2): ResponseInterface {
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

        // Track debug log calls (capture both message and context)
        $debugCalls = [];
        $this->mockLogger->expects($this->exactly(3))
            ->method('debug')
            ->willReturnCallback(function (string $message, array $context) use (&$debugCalls): void {
                $debugCalls[] = ['message' => $message, 'context' => $context];
            });

        $cachingClient = new CachingHttpClient($this->mockClient, $this->cache, 3600, $this->mockLogger);

        // First call - cache miss
        $cachingClient->get($url);

        // Second call - cache hit
        $cachingClient->get($url);

        // Verify log messages
        $messages = array_column($debugCalls, 'message');
        $this->assertContains('Cache miss', $messages);
        $this->assertContains('Cache hit', $messages);
        $this->assertContains('Response cached', $messages);
    }

    public function testIrrelevantHeadersDoNotAffectCacheKey(): void
    {
        $url          = 'https://example.com/api/data';
        $responseBody = '{"test": "data"}';

        $mockResponse = $this->createMockResponse(200, $responseBody);

        // Expect the underlying client to be called only once
        // despite different irrelevant headers on subsequent requests
        $this->mockClient->expects($this->once())
            ->method('get')
            ->willReturn($mockResponse);

        $cachingClient = new CachingHttpClient($this->mockClient, $this->cache, 3600, $this->mockLogger);

        // First call with User-Agent header
        $response1 = $cachingClient->get($url, ['User-Agent' => 'TestClient/1.0']);
        $this->assertEquals(200, $response1->getStatusCode());

        // Second call with different User-Agent - should hit cache
        $response2 = $cachingClient->get($url, ['User-Agent' => 'TestClient/2.0']);
        $this->assertEquals(200, $response2->getStatusCode());
        $this->assertEquals($responseBody, $response2->getBody()->getContents());

        // Third call with X-Request-ID - should also hit cache
        $response3 = $cachingClient->get($url, ['X-Request-ID' => 'abc123']);
        $this->assertEquals(200, $response3->getStatusCode());
    }

    public function testRelevantHeadersAffectCacheKey(): void
    {
        $url           = 'https://example.com/api/data';
        $responseBody1 = '{"test": "data", "lang": "en"}';
        $responseBody2 = '{"test": "data", "lang": "it"}';

        $mockResponse1 = $this->createMockResponse(200, $responseBody1);
        $mockResponse2 = $this->createMockResponse(200, $responseBody2);

        // Expect the underlying client to be called twice
        // because Accept-Language header differs
        $this->mockClient->expects($this->exactly(2))
            ->method('get')
            ->willReturnOnConsecutiveCalls($mockResponse1, $mockResponse2);

        $cachingClient = new CachingHttpClient($this->mockClient, $this->cache, 3600, $this->mockLogger);

        // First call with English locale
        $response1 = $cachingClient->get($url, ['Accept-Language' => 'en']);
        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertEquals($responseBody1, $response1->getBody()->getContents());

        // Second call with Italian locale - should miss cache
        $response2 = $cachingClient->get($url, ['Accept-Language' => 'it']);
        $this->assertEquals(200, $response2->getStatusCode());
        $this->assertEquals($responseBody2, $response2->getBody()->getContents());
    }

    public function testAcceptHeaderAffectsCacheKey(): void
    {
        $url      = 'https://example.com/api/data';
        $jsonBody = '{"test": "data"}';
        $xmlBody  = '<test>data</test>';

        $mockResponse1 = $this->createMockResponse(200, $jsonBody);
        $mockResponse2 = $this->createMockResponse(200, $xmlBody);

        // Expect the underlying client to be called twice
        // because Accept header differs
        $this->mockClient->expects($this->exactly(2))
            ->method('get')
            ->willReturnOnConsecutiveCalls($mockResponse1, $mockResponse2);

        $cachingClient = new CachingHttpClient($this->mockClient, $this->cache, 3600, $this->mockLogger);

        // First call requesting JSON
        $response1 = $cachingClient->get($url, ['Accept' => 'application/json']);
        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertEquals($jsonBody, $response1->getBody()->getContents());

        // Second call requesting XML - should miss cache
        $response2 = $cachingClient->get($url, ['Accept' => 'application/xml']);
        $this->assertEquals(200, $response2->getStatusCode());
        $this->assertEquals($xmlBody, $response2->getBody()->getContents());
    }

    public function testNonSeekableStreamHandledGracefully(): void
    {
        $url          = 'https://example.com/api/data';
        $responseBody = '{"test": "data"}';

        // Create a non-seekable stream
        $mockStream = $this->createMock(StreamInterface::class);
        $mockStream->method('getContents')->willReturn($responseBody);
        $mockStream->method('isSeekable')->willReturn(false);
        // Verify rewind() is never called on non-seekable stream
        $mockStream->expects($this->never())->method('rewind');

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn($mockStream);
        $mockResponse->method('getHeaders')->willReturn([]);

        $this->mockClient->expects($this->once())
            ->method('get')
            ->with($url, [])
            ->willReturn($mockResponse);

        $cachingClient = new CachingHttpClient($this->mockClient, $this->cache, 3600, $this->mockLogger);

        // Should cache successfully without calling rewind()
        $response = $cachingClient->get($url);
        $this->assertEquals(200, $response->getStatusCode());

        // Verify response was cached
        $cachedResponse = $cachingClient->get($url);
        $this->assertEquals(200, $cachedResponse->getStatusCode());
    }

    /**
     * Helper method to create mock response with seekable stream
     */
    private function createMockResponse(int $statusCode, string $body): ResponseInterface
    {
        $mockStream = $this->createMock(StreamInterface::class);
        $mockStream->method('getContents')->willReturn($body);
        $mockStream->method('isSeekable')->willReturn(true);
        // rewind() has void return type, so don't specify a return value
        $mockStream->method('rewind');

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn($statusCode);
        $mockResponse->method('getBody')->willReturn($mockStream);
        $mockResponse->method('getHeaders')->willReturn([]);

        return $mockResponse;
    }
}
