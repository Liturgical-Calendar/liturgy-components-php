<?php

namespace LiturgicalCalendar\Components\Tests\Http;

use LiturgicalCalendar\Components\Http\PsrHttpClient;
use LiturgicalCalendar\Components\Http\HttpException;
use LiturgicalCalendar\Components\Http\HttpClientInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Unit tests for PsrHttpClient
 */
class PsrHttpClientTest extends TestCase
{
    private ClientInterface $mockHttpClient;
    private RequestFactoryInterface $mockRequestFactory;
    private StreamFactoryInterface $mockStreamFactory;
    private PsrHttpClient $client;

    protected function setUp(): void
    {
        $this->mockHttpClient     = $this->createMock(ClientInterface::class);
        $this->mockRequestFactory = $this->createMock(RequestFactoryInterface::class);
        $this->mockStreamFactory  = $this->createMock(StreamFactoryInterface::class);

        $this->client = new PsrHttpClient(
            $this->mockHttpClient,
            $this->mockRequestFactory,
            $this->mockStreamFactory
        );
    }

    public function testImplementsHttpClientInterface(): void
    {
        $this->assertInstanceOf(HttpClientInterface::class, $this->client);
    }

    public function testGetCreatesRequestWithCorrectMethod(): void
    {
        $url          = 'https://example.com/api/test';
        $mockRequest  = $this->createMock(RequestInterface::class);
        $mockResponse = $this->createMock(ResponseInterface::class);

        $this->mockRequestFactory
            ->expects($this->once())
            ->method('createRequest')
            ->with('GET', $url)
            ->willReturn($mockRequest);

        $mockRequest
            ->expects($this->never())
            ->method('withHeader');

        $this->mockHttpClient
            ->expects($this->once())
            ->method('sendRequest')
            ->with($mockRequest)
            ->willReturn($mockResponse);

        $result = $this->client->get($url);

        $this->assertSame($mockResponse, $result);
    }

    public function testGetWithHeaders(): void
    {
        $url     = 'https://example.com/api/test';
        $headers = [
            'Authorization' => 'Bearer token123',
            'Accept'        => 'application/json'
        ];

        $mockRequest  = $this->createMock(RequestInterface::class);
        $mockResponse = $this->createMock(ResponseInterface::class);

        $this->mockRequestFactory
            ->expects($this->once())
            ->method('createRequest')
            ->with('GET', $url)
            ->willReturn($mockRequest);

        // Expect withHeader to be called for each header
        $mockRequest
            ->expects($this->exactly(2))
            ->method('withHeader')
            ->willReturnCallback(function ($name, $value) use ($mockRequest, $headers) {
                $this->assertArrayHasKey($name, $headers);
                $this->assertEquals($headers[$name], $value);
                return $mockRequest;
            });

        $this->mockHttpClient
            ->expects($this->once())
            ->method('sendRequest')
            ->willReturn($mockResponse);

        $result = $this->client->get($url, $headers);

        $this->assertSame($mockResponse, $result);
    }

    public function testGetThrowsHttpExceptionOnClientException(): void
    {
        $url         = 'https://example.com/api/test';
        $mockRequest = $this->createMock(RequestInterface::class);

        // Create a real exception that implements ClientExceptionInterface
        $clientException = new class ('Network error', 0) extends \Exception implements ClientExceptionInterface {
        };

        $this->mockRequestFactory
            ->method('createRequest')
            ->willReturn($mockRequest);

        $mockRequest
            ->method('withHeader')
            ->willReturnSelf();

        $this->mockHttpClient
            ->method('sendRequest')
            ->willThrowException($clientException);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('HTTP GET request failed: Network error');

        $this->client->get($url);
    }

    public function testPostCreatesRequestWithCorrectMethod(): void
    {
        $url  = 'https://example.com/api/submit';
        $body = 'test body content';

        $mockRequest  = $this->createMock(RequestInterface::class);
        $mockStream   = $this->createMock(StreamInterface::class);
        $mockResponse = $this->createMock(ResponseInterface::class);

        $this->mockRequestFactory
            ->expects($this->once())
            ->method('createRequest')
            ->with('POST', $url)
            ->willReturn($mockRequest);

        $this->mockStreamFactory
            ->expects($this->once())
            ->method('createStream')
            ->with($body)
            ->willReturn($mockStream);

        $mockRequest
            ->expects($this->once())
            ->method('withBody')
            ->with($mockStream)
            ->willReturnSelf();

        $this->mockHttpClient
            ->expects($this->once())
            ->method('sendRequest')
            ->willReturn($mockResponse);

        $result = $this->client->post($url, $body);

        $this->assertSame($mockResponse, $result);
    }

    public function testPostWithArrayBodyEncodesJson(): void
    {
        $url          = 'https://example.com/api/submit';
        $bodyArray    = ['key' => 'value', 'number' => 42];
        $expectedJson = json_encode($bodyArray);

        $mockRequest  = $this->createMock(RequestInterface::class);
        $mockStream   = $this->createMock(StreamInterface::class);
        $mockResponse = $this->createMock(ResponseInterface::class);

        $this->mockRequestFactory
            ->method('createRequest')
            ->willReturn($mockRequest);

        $this->mockStreamFactory
            ->expects($this->once())
            ->method('createStream')
            ->with($expectedJson)
            ->willReturn($mockStream);

        $mockRequest
            ->method('withBody')
            ->willReturnSelf();

        // Expect Content-Type header to be added automatically
        $mockRequest
            ->expects($this->once())
            ->method('withHeader')
            ->with('Content-Type', 'application/json')
            ->willReturnSelf();

        $this->mockHttpClient
            ->method('sendRequest')
            ->willReturn($mockResponse);

        $this->client->post($url, $bodyArray);
    }

    public function testPostWithArrayBodyAndCustomContentType(): void
    {
        $url       = 'https://example.com/api/submit';
        $bodyArray = ['data' => 'test'];
        $headers   = ['Content-Type' => 'application/x-custom'];

        $mockRequest  = $this->createMock(RequestInterface::class);
        $mockStream   = $this->createMock(StreamInterface::class);
        $mockResponse = $this->createMock(ResponseInterface::class);

        $this->mockRequestFactory
            ->method('createRequest')
            ->willReturn($mockRequest);

        $this->mockStreamFactory
            ->method('createStream')
            ->willReturn($mockStream);

        $mockRequest
            ->method('withBody')
            ->willReturnSelf();

        // Should use custom Content-Type, not add application/json
        $mockRequest
            ->expects($this->once())
            ->method('withHeader')
            ->with('Content-Type', 'application/x-custom')
            ->willReturnSelf();

        $this->mockHttpClient
            ->method('sendRequest')
            ->willReturn($mockResponse);

        $this->client->post($url, $bodyArray, $headers);
    }

    public function testPostWithCustomHeaders(): void
    {
        $url     = 'https://example.com/api/submit';
        $body    = 'content';
        $headers = [
            'X-API-Key'  => 'secret',
            'User-Agent' => 'TestClient/1.0'
        ];

        $mockRequest  = $this->createMock(RequestInterface::class);
        $mockStream   = $this->createMock(StreamInterface::class);
        $mockResponse = $this->createMock(ResponseInterface::class);

        $this->mockRequestFactory
            ->method('createRequest')
            ->willReturn($mockRequest);

        $this->mockStreamFactory
            ->method('createStream')
            ->willReturn($mockStream);

        $mockRequest
            ->method('withBody')
            ->willReturnSelf();

        $mockRequest
            ->expects($this->exactly(2))
            ->method('withHeader')
            ->willReturnCallback(function ($name, $value) use ($mockRequest, $headers) {
                $this->assertArrayHasKey($name, $headers);
                $this->assertEquals($headers[$name], $value);
                return $mockRequest;
            });

        $this->mockHttpClient
            ->method('sendRequest')
            ->willReturn($mockResponse);

        $this->client->post($url, $body, $headers);
    }

    public function testPostThrowsHttpExceptionOnClientException(): void
    {
        $url         = 'https://example.com/api/submit';
        $mockRequest = $this->createMock(RequestInterface::class);
        $mockStream  = $this->createMock(StreamInterface::class);

        // Create a real exception that implements ClientExceptionInterface
        $clientException = new class ('Connection timeout', 0) extends \Exception implements ClientExceptionInterface {
        };

        $this->mockRequestFactory
            ->method('createRequest')
            ->willReturn($mockRequest);

        $this->mockStreamFactory
            ->method('createStream')
            ->willReturn($mockStream);

        $mockRequest
            ->method('withBody')
            ->willReturnSelf();

        $mockRequest
            ->method('withHeader')
            ->willReturnSelf();

        $this->mockHttpClient
            ->method('sendRequest')
            ->willThrowException($clientException);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('HTTP POST request failed: Connection timeout');

        $this->client->post($url, 'body');
    }

    public function testPostThrowsHttpExceptionOnInvalidJson(): void
    {
        $url = 'https://example.com/api/test';

        // Create an array containing invalid UTF-8 sequence
        // json_encode will return false for this data
        $invalidBody = ['data' => "\xB1\x31"];

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Failed to encode request body as JSON');

        // The exception should be thrown before any PSR objects are created,
        // so we don't need to set up mocks for this test
        $this->client->post($url, $invalidBody);
    }
}
