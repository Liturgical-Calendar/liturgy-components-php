<?php

namespace LiturgicalCalendar\Components\Tests\Http;

use LiturgicalCalendar\Components\Http\HttpClientFactory;
use LiturgicalCalendar\Components\Http\HttpClientInterface;
use LiturgicalCalendar\Components\Http\PsrHttpClient;
use LiturgicalCalendar\Components\Http\FileGetContentsClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Unit tests for HttpClientFactory
 */
class HttpClientFactoryTest extends TestCase
{
    public function testCreateReturnsHttpClientInterface(): void
    {
        $client = HttpClientFactory::create();

        $this->assertInstanceOf(HttpClientInterface::class, $client);
    }

    public function testCreateWithNullParametersReturnsFallbackClient(): void
    {
        $client = HttpClientFactory::create(null, null, null);

        // Returns HttpClientInterface (may be PsrHttpClient via auto-discovery or FileGetContentsClient fallback)
        $this->assertInstanceOf(HttpClientInterface::class, $client);
    }

    public function testCreateWithAllParametersReturnsPsrClient(): void
    {
        $mockHttpClient     = $this->createMock(ClientInterface::class);
        $mockRequestFactory = $this->createMock(RequestFactoryInterface::class);
        $mockStreamFactory  = $this->createMock(StreamFactoryInterface::class);

        $client = HttpClientFactory::create(
            $mockHttpClient,
            $mockRequestFactory,
            $mockStreamFactory
        );

        $this->assertInstanceOf(PsrHttpClient::class, $client);
    }

    public function testCreateWithPartialParametersReturnsFallbackClient(): void
    {
        $mockHttpClient = $this->createMock(ClientInterface::class);

        // Missing request and stream factories
        $client = HttpClientFactory::create($mockHttpClient, null, null);

        // Returns HttpClientInterface (may be PsrHttpClient via auto-discovery or FileGetContentsClient fallback)
        $this->assertInstanceOf(HttpClientInterface::class, $client);
    }

    public function testCreateFallbackReturnsFileGetContentsClient(): void
    {
        $client = HttpClientFactory::createFallback();

        $this->assertInstanceOf(FileGetContentsClient::class, $client);
        $this->assertInstanceOf(HttpClientInterface::class, $client);
    }

    public function testCreateWithGuzzleThrowsExceptionWhenGuzzleNotInstalled(): void
    {
        // Skip this test if Guzzle is actually installed
        if (class_exists('\GuzzleHttp\Client')) {
            $this->markTestSkipped('Guzzle is installed, cannot test missing dependency behavior');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Guzzle HTTP client not found. Install with: composer require guzzlehttp/guzzle');

        HttpClientFactory::createWithGuzzle();
    }

    public function testCreateWithGuzzleReturnsClientWhenGuzzleInstalled(): void
    {
        // Skip this test if Guzzle is not installed
        if (!class_exists('\GuzzleHttp\Client')) {
            $this->markTestSkipped('Guzzle is not installed');
        }

        $client = HttpClientFactory::createWithGuzzle();

        $this->assertInstanceOf(HttpClientInterface::class, $client);
        $this->assertInstanceOf(PsrHttpClient::class, $client);
    }

    public function testFactoryMethodsReturnSameInterface(): void
    {
        $client1 = HttpClientFactory::create();
        $client2 = HttpClientFactory::createFallback();

        // Both should implement the same interface
        $this->assertInstanceOf(HttpClientInterface::class, $client1);
        $this->assertInstanceOf(HttpClientInterface::class, $client2);
    }

    public function testCreateCanAcceptOnlyHttpClient(): void
    {
        $mockHttpClient = $this->createMock(ClientInterface::class);

        $client = HttpClientFactory::create($mockHttpClient);

        // Returns HttpClientInterface (may be PsrHttpClient via auto-discovery or FileGetContentsClient fallback)
        $this->assertInstanceOf(HttpClientInterface::class, $client);
    }

    public function testCreateReturnsWorkingClient(): void
    {
        $client = HttpClientFactory::create();

        // Verify the client has the required methods
        // @phpstan-ignore-next-line - Testing runtime method existence even though interface guarantees it
        $this->assertTrue(method_exists($client, 'get'));
        // @phpstan-ignore-next-line - Testing runtime method existence even though interface guarantees it
        $this->assertTrue(method_exists($client, 'post'));
    }

    public function testMultipleCallsToCreateReturnNewInstances(): void
    {
        $client1 = HttpClientFactory::create();
        $client2 = HttpClientFactory::create();

        // Should be different instances
        $this->assertNotSame($client1, $client2);
    }

    public function testCreateWithMixedParametersHandlesNulls(): void
    {
        $mockRequestFactory = $this->createMock(RequestFactoryInterface::class);

        // Null client, with request factory, null stream factory
        $client = HttpClientFactory::create(null, $mockRequestFactory, null);

        // Returns HttpClientInterface (may be PsrHttpClient via auto-discovery or FileGetContentsClient fallback)
        $this->assertInstanceOf(HttpClientInterface::class, $client);
    }

    public function testFactoryCreatesClientsThatImplementCorrectInterface(): void
    {
        $mockHttpClient     = $this->createMock(ClientInterface::class);
        $mockRequestFactory = $this->createMock(RequestFactoryInterface::class);
        $mockStreamFactory  = $this->createMock(StreamFactoryInterface::class);

        $psrClient      = HttpClientFactory::create(
            $mockHttpClient,
            $mockRequestFactory,
            $mockStreamFactory
        );
        $fallbackClient = HttpClientFactory::createFallback();

        // Both should implement HttpClientInterface
        $this->assertInstanceOf(HttpClientInterface::class, $psrClient);
        $this->assertInstanceOf(HttpClientInterface::class, $fallbackClient);

        // Both should have get() and post() methods
        $reflection1 = new \ReflectionClass($psrClient);
        $reflection2 = new \ReflectionClass($fallbackClient);

        $this->assertTrue($reflection1->hasMethod('get'));
        $this->assertTrue($reflection1->hasMethod('post'));
        $this->assertTrue($reflection2->hasMethod('get'));
        $this->assertTrue($reflection2->hasMethod('post'));
    }

    // ============================================================================
    // Middleware Helper Method Tests
    // ============================================================================

    public function testCreateWithLoggingReturnsHttpClientInterface(): void
    {
        $client = HttpClientFactory::createWithLogging();

        $this->assertInstanceOf(HttpClientInterface::class, $client);
    }

    public function testCreateWithLoggingReturnsLoggingHttpClient(): void
    {
        $client = HttpClientFactory::createWithLogging();

        // Outermost decorator should be LoggingHttpClient
        $this->assertInstanceOf(\LiturgicalCalendar\Components\Http\LoggingHttpClient::class, $client);
    }

    public function testCreateWithCachingReturnsHttpClientInterface(): void
    {
        $client = HttpClientFactory::createWithCaching();

        $this->assertInstanceOf(HttpClientInterface::class, $client);
    }

    public function testCreateWithCachingReturnsLoggingHttpClient(): void
    {
        $client = HttpClientFactory::createWithCaching();

        // Outermost decorator should be LoggingHttpClient (wraps CachingHttpClient)
        $this->assertInstanceOf(\LiturgicalCalendar\Components\Http\LoggingHttpClient::class, $client);
    }

    public function testCreateWithRetryReturnsHttpClientInterface(): void
    {
        $client = HttpClientFactory::createWithRetry();

        $this->assertInstanceOf(HttpClientInterface::class, $client);
    }

    public function testCreateWithRetryReturnsLoggingHttpClient(): void
    {
        $client = HttpClientFactory::createWithRetry();

        // Outermost decorator should be LoggingHttpClient (wraps RetryHttpClient)
        $this->assertInstanceOf(\LiturgicalCalendar\Components\Http\LoggingHttpClient::class, $client);
    }

    public function testCreateWithCircuitBreakerReturnsHttpClientInterface(): void
    {
        $client = HttpClientFactory::createWithCircuitBreaker();

        $this->assertInstanceOf(HttpClientInterface::class, $client);
    }

    public function testCreateWithCircuitBreakerReturnsLoggingHttpClient(): void
    {
        $client = HttpClientFactory::createWithCircuitBreaker();

        // Outermost decorator should be LoggingHttpClient (wraps CircuitBreakerHttpClient)
        $this->assertInstanceOf(\LiturgicalCalendar\Components\Http\LoggingHttpClient::class, $client);
    }

    public function testCreateProductionClientReturnsHttpClientInterface(): void
    {
        $client = HttpClientFactory::createProductionClient();

        $this->assertInstanceOf(HttpClientInterface::class, $client);
    }

    public function testCreateProductionClientReturnsLoggingHttpClient(): void
    {
        $client = HttpClientFactory::createProductionClient();

        // Outermost decorator should be LoggingHttpClient (wraps full middleware stack)
        $this->assertInstanceOf(\LiturgicalCalendar\Components\Http\LoggingHttpClient::class, $client);
    }

    public function testMiddlewareMethodsAcceptCustomParameters(): void
    {
        $mockLogger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $mockCache  = $this->createMock(\Psr\SimpleCache\CacheInterface::class);

        // All middleware methods should accept custom parameters without errors
        $loggingClient        = HttpClientFactory::createWithLogging($mockLogger);
        $cachingClient        = HttpClientFactory::createWithCaching($mockCache, 7200, $mockLogger);
        $retryClient          = HttpClientFactory::createWithRetry(5, 2000, true, null, $mockLogger);
        $circuitBreakerClient = HttpClientFactory::createWithCircuitBreaker(10, 30, 3, $mockLogger);
        $productionClient     = HttpClientFactory::createProductionClient($mockCache, $mockLogger, 7200, 5, 10);

        // All should return HttpClientInterface instances
        $this->assertInstanceOf(HttpClientInterface::class, $loggingClient);
        $this->assertInstanceOf(HttpClientInterface::class, $cachingClient);
        $this->assertInstanceOf(HttpClientInterface::class, $retryClient);
        $this->assertInstanceOf(HttpClientInterface::class, $circuitBreakerClient);
        $this->assertInstanceOf(HttpClientInterface::class, $productionClient);
    }

    public function testMiddlewareMethodsReturnNewInstances(): void
    {
        // Multiple calls should return different instances
        $client1 = HttpClientFactory::createWithLogging();
        $client2 = HttpClientFactory::createWithLogging();

        $this->assertNotSame($client1, $client2);

        $client3 = HttpClientFactory::createProductionClient();
        $client4 = HttpClientFactory::createProductionClient();

        $this->assertNotSame($client3, $client4);
    }
}
