<?php

namespace LiturgicalCalendar\Components\Tests\Http;

use LiturgicalCalendar\Components\Http\LoggingHttpClient;
use LiturgicalCalendar\Components\Http\HttpClientInterface;
use LiturgicalCalendar\Components\Http\HttpException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for LoggingHttpClient
 */
class LoggingHttpClientTest extends TestCase
{
    private HttpClientInterface $mockClient;
    private LoggerInterface $mockLogger;
    private LoggingHttpClient $loggingClient;

    protected function setUp(): void
    {
        $this->mockClient    = $this->createMock(HttpClientInterface::class);
        $this->mockLogger    = $this->createMock(LoggerInterface::class);
        $this->loggingClient = new LoggingHttpClient($this->mockClient, $this->mockLogger);
    }

    public function testImplementsHttpClientInterface(): void
    {
        $this->assertInstanceOf(HttpClientInterface::class, $this->loggingClient);
    }

    public function testGetLogsRequestAndResponse(): void
    {
        $url     = 'https://example.com/api/test';
        $headers = ['Accept' => 'application/json'];

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockBody     = $this->createMock(StreamInterface::class);

        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn($mockBody);
        $mockBody->method('getSize')->willReturn(1024);

        $this->mockClient
            ->expects($this->once())
            ->method('get')
            ->with($url, $headers)
            ->willReturn($mockResponse);

        // Expect info log for request
        $this->mockLogger
            ->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function ($message, $context) use ($url) {
                if ($message === 'HTTP GET request') {
                    $this->assertEquals($url, $context['url']);
                    $this->assertIsArray($context['headers']);
                } elseif ($message === 'HTTP GET response') {
                    $this->assertEquals($url, $context['url']);
                    $this->assertEquals(200, $context['status_code']);
                    $this->assertArrayHasKey('duration_ms', $context);
                    $this->assertEquals(1024, $context['response_size']);
                }
            });

        $result = $this->loggingClient->get($url, $headers);

        $this->assertSame($mockResponse, $result);
    }

    public function testGetLogsException(): void
    {
        $url       = 'https://example.com/api/test';
        $exception = new HttpException('Network error');

        $this->mockClient
            ->method('get')
            ->willThrowException($exception);

        // Expect info log for request and error log for exception
        $this->mockLogger
            ->expects($this->once())
            ->method('info')
            ->with('HTTP GET request', $this->anything());

        $this->mockLogger
            ->expects($this->once())
            ->method('error')
            ->willReturnCallback(function ($message, $context) use ($url) {
                $this->assertEquals('HTTP GET failed', $message);
                $this->assertEquals($url, $context['url']);
                $this->assertEquals('Network error', $context['error']);
                $this->assertArrayHasKey('duration_ms', $context);
                $this->assertEquals(HttpException::class, $context['exception']);
            });

        $this->expectException(HttpException::class);

        $this->loggingClient->get($url);
    }

    public function testPostLogsRequestAndResponse(): void
    {
        $url     = 'https://example.com/api/submit';
        $body    = ['key' => 'value'];
        $headers = ['Content-Type' => 'application/json'];

        $mockResponse     = $this->createMock(ResponseInterface::class);
        $mockResponseBody = $this->createMock(StreamInterface::class);

        $mockResponse->method('getStatusCode')->willReturn(201);
        $mockResponse->method('getBody')->willReturn($mockResponseBody);
        $mockResponseBody->method('getSize')->willReturn(512);

        $this->mockClient
            ->expects($this->once())
            ->method('post')
            ->with($url, $body, $headers)
            ->willReturn($mockResponse);

        // Expect info logs for request and response
        $this->mockLogger
            ->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function ($message, $context) use ($url, $body) {
                if ($message === 'HTTP POST request') {
                    $this->assertEquals($url, $context['url']);
                    $this->assertEquals(count($body), $context['body_size']);
                } elseif ($message === 'HTTP POST response') {
                    $this->assertEquals($url, $context['url']);
                    $this->assertEquals(201, $context['status_code']);
                    $this->assertArrayHasKey('duration_ms', $context);
                }
            });

        $result = $this->loggingClient->post($url, $body, $headers);

        $this->assertSame($mockResponse, $result);
    }

    public function testPostLogsException(): void
    {
        $url       = 'https://example.com/api/submit';
        $exception = new HttpException('Timeout');

        $this->mockClient
            ->method('post')
            ->willThrowException($exception);

        $this->mockLogger
            ->expects($this->once())
            ->method('info');

        $this->mockLogger
            ->expects($this->once())
            ->method('error')
            ->willReturnCallback(function ($message, $context) use ($url) {
                $this->assertEquals('HTTP POST failed', $message);
                $this->assertEquals($url, $context['url']);
                $this->assertEquals('Timeout', $context['error']);
            });

        $this->expectException(HttpException::class);

        $this->loggingClient->post($url, 'body');
    }

    public function testSanitizesAuthorizationHeader(): void
    {
        $url     = 'https://example.com/api/test';
        $headers = [
            'Authorization' => 'Bearer secret-token',
            'Accept'        => 'application/json'
        ];

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockBody     = $this->createMock(StreamInterface::class);

        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn($mockBody);
        $mockBody->method('getSize')->willReturn(100);

        $this->mockClient
            ->method('get')
            ->willReturn($mockResponse);

        $this->mockLogger
            ->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function ($message, $context) {
                if ($message === 'HTTP GET request') {
                    // Authorization should be redacted
                    $this->assertEquals('***REDACTED***', $context['headers']['Authorization']);
                    $this->assertEquals('application/json', $context['headers']['Accept']);
                }
            });

        $this->loggingClient->get($url, $headers);
    }

    public function testSanitizesApiKeyHeader(): void
    {
        $url     = 'https://example.com/api/test';
        $headers = [
            'X-API-Key'  => 'secret123',
            'User-Agent' => 'TestClient/1.0'
        ];

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockBody     = $this->createMock(StreamInterface::class);

        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn($mockBody);
        $mockBody->method('getSize')->willReturn(100);

        $this->mockClient
            ->method('get')
            ->willReturn($mockResponse);

        $this->mockLogger
            ->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function ($message, $context) {
                if ($message === 'HTTP GET request') {
                    // API key should be redacted
                    $this->assertEquals('***REDACTED***', $context['headers']['X-API-Key']);
                    $this->assertEquals('TestClient/1.0', $context['headers']['User-Agent']);
                }
            });

        $this->loggingClient->get($url, $headers);
    }

    public function testLogsDurationInMilliseconds(): void
    {
        $url = 'https://example.com/api/test';

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockBody     = $this->createMock(StreamInterface::class);

        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn($mockBody);
        $mockBody->method('getSize')->willReturn(100);

        $this->mockClient
            ->method('get')
            ->willReturn($mockResponse);

        $this->mockLogger
            ->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function ($message, $context) {
                if ($message === 'HTTP GET response') {
                    $this->assertArrayHasKey('duration_ms', $context);
                    $this->assertIsNumeric($context['duration_ms']);
                    $this->assertGreaterThanOrEqual(0, $context['duration_ms']);
                }
            });

        $this->loggingClient->get($url);
    }

    public function testPostWithStringBodyLogsSize(): void
    {
        $url  = 'https://example.com/api/submit';
        $body = 'This is a test body';

        $mockResponse     = $this->createMock(ResponseInterface::class);
        $mockResponseBody = $this->createMock(StreamInterface::class);

        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn($mockResponseBody);
        $mockResponseBody->method('getSize')->willReturn(100);

        $this->mockClient
            ->method('post')
            ->willReturn($mockResponse);

        $this->mockLogger
            ->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function ($message, $context) use ($body) {
                if ($message === 'HTTP POST request') {
                    $this->assertEquals(strlen($body), $context['body_size']);
                }
            });

        $this->loggingClient->post($url, $body);
    }
}
