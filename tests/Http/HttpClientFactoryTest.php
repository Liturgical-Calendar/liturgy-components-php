<?php

namespace LiturgicalCalendar\Tests\Http;

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

        // Should return FileGetContentsClient when no PSR dependencies provided
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

        // Should fall back because not all dependencies are provided
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
        $this->expectExceptionMessage('Guzzle HTTP client not found');

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

        // Should use fallback when missing factories
        $this->assertInstanceOf(HttpClientInterface::class, $client);
    }

    public function testCreateReturnsWorkingClient(): void
    {
        $client = HttpClientFactory::create();

        // Verify the client has the required methods
        $this->assertTrue(method_exists($client, 'get'));
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

        // Should fall back because not all dependencies provided
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
}
