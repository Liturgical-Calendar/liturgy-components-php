<?php

namespace LiturgicalCalendar\Components\Tests\Http;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use LiturgicalCalendar\Components\Http\RetryHttpClient;
use LiturgicalCalendar\Components\Http\HttpClientInterface;
use LiturgicalCalendar\Components\Http\HttpException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Test suite for RetryHttpClient middleware
 */
class RetryHttpClientTest extends TestCase
{
    /** @var MockObject&HttpClientInterface */
    private $mockClient;

    /** @var MockObject&LoggerInterface */
    private $mockLogger;

    protected function setUp(): void
    {
        $this->mockClient = $this->createMock(HttpClientInterface::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
    }

    public function testSuccessfulRequestWithoutRetry(): void
    {
        $url          = 'https://example.com/api/data';
        $mockResponse = $this->createMockResponse(200);

        // Should only be called once (no retries)
        $this->mockClient->expects($this->once())
            ->method('get')
            ->with($url, [])
            ->willReturn($mockResponse);

        $retryClient = new RetryHttpClient($this->mockClient, 3, 100, true, [], $this->mockLogger);
        $response    = $retryClient->get($url);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testRetryOnException(): void
    {
        $url          = 'https://example.com/api/data';
        $mockResponse = $this->createMockResponse(200);

        // Fail twice, then succeed
        $this->mockClient->expects($this->exactly(3))
            ->method('get')
            ->with($url, [])
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new HttpException('Network error')),
                $this->throwException(new HttpException('Network error')),
                $mockResponse
            );

        $retryClient = new RetryHttpClient($this->mockClient, 3, 10, false, [], $this->mockLogger);
        $response    = $retryClient->get($url);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testMaxRetriesExceeded(): void
    {
        $url = 'https://example.com/api/data';

        // Always fail
        $this->mockClient->expects($this->exactly(4)) // Initial + 3 retries
            ->method('get')
            ->with($url, [])
            ->willThrowException(new HttpException('Network error'));

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Network error');

        $retryClient = new RetryHttpClient($this->mockClient, 3, 10, false, [], $this->mockLogger);
        $retryClient->get($url);
    }

    public function testRetryOnRetryableStatusCode(): void
    {
        $url             = 'https://example.com/api/data';
        $mockResponse503 = $this->createMockResponse(503); // Service Unavailable
        $mockResponse200 = $this->createMockResponse(200);

        $this->mockClient->expects($this->exactly(2))
            ->method('get')
            ->with($url, [])
            ->willReturnOnConsecutiveCalls($mockResponse503, $mockResponse200);

        $retryClient = new RetryHttpClient(
            $this->mockClient,
            3,
            10,
            false,
            [503], // Retry on 503
            $this->mockLogger
        );

        $response = $retryClient->get($url);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testNoRetryOnNonRetryableStatusCode(): void
    {
        $url             = 'https://example.com/api/data';
        $mockResponse404 = $this->createMockResponse(404); // Not Found

        // Should only be called once (404 is not in retry list)
        $this->mockClient->expects($this->once())
            ->method('get')
            ->with($url, [])
            ->willReturn($mockResponse404);

        $retryClient = new RetryHttpClient(
            $this->mockClient,
            3,
            10,
            false,
            [500, 503], // Only retry on 500, 503
            $this->mockLogger
        );

        $response = $retryClient->get($url);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testPostRequest(): void
    {
        $url          = 'https://example.com/api/data';
        $body         = ['key' => 'value'];
        $mockResponse = $this->createMockResponse(200);

        // Fail once, then succeed
        $this->mockClient->expects($this->exactly(2))
            ->method('post')
            ->with($url, $body, [])
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new HttpException('Network error')),
                $mockResponse
            );

        $retryClient = new RetryHttpClient($this->mockClient, 3, 10, false, [], $this->mockLogger);
        $response    = $retryClient->post($url, $body);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testExponentialBackoff(): void
    {
        $url          = 'https://example.com/api/data';
        $mockResponse = $this->createMockResponse(200);

        $this->mockClient->expects($this->exactly(3))
            ->method('get')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new HttpException('Error 1')),
                $this->throwException(new HttpException('Error 2')),
                $mockResponse
            );

        // Track sleep delays instead of using real timing
        $sleepDelays = [];
        $mockSleep   = function (int $delayMs) use (&$sleepDelays): void {
            $sleepDelays[] = $delayMs;
        };

        // Use exponential backoff with 100ms initial delay
        // Expected delays: 100ms (2^0), 200ms (2^1)
        $retryClient = new RetryHttpClient(
            $this->mockClient,
            3,
            100, // 100ms initial delay
            true, // exponential backoff
            [],
            $this->mockLogger,
            $mockSleep
        );

        $response = $retryClient->get($url);

        $this->assertEquals(200, $response->getStatusCode());
        // Verify exponential backoff delays: 100ms, 200ms
        $this->assertEquals([100, 200], $sleepDelays);
    }

    public function testLinearBackoff(): void
    {
        $url          = 'https://example.com/api/data';
        $mockResponse = $this->createMockResponse(200);

        $this->mockClient->expects($this->exactly(3))
            ->method('get')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new HttpException('Error 1')),
                $this->throwException(new HttpException('Error 2')),
                $mockResponse
            );

        // Track sleep delays instead of using real timing
        $sleepDelays = [];
        $mockSleep   = function (int $delayMs) use (&$sleepDelays): void {
            $sleepDelays[] = $delayMs;
        };

        // Use linear backoff with 100ms delay
        // Expected delays: 100ms, 100ms (same every time)
        $retryClient = new RetryHttpClient(
            $this->mockClient,
            3,
            100,
            false, // linear backoff
            [],
            $this->mockLogger,
            $mockSleep
        );

        $response = $retryClient->get($url);

        $this->assertEquals(200, $response->getStatusCode());
        // Verify linear backoff delays: both 100ms
        $this->assertEquals([100, 100], $sleepDelays);
    }

    public function testLogsRetryAttempts(): void
    {
        $url          = 'https://example.com/api/data';
        $mockResponse = $this->createMockResponse(200);

        $this->mockClient->expects($this->exactly(2))
            ->method('get')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new HttpException('Network error')),
                $mockResponse
            );

        // Expect warning log for failure and info log for eventual success
        $this->mockLogger->expects($this->atLeastOnce())
            ->method('warning')
            ->with(
                $this->stringContains('failed, retrying'),
                $this->anything()
            );

        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('succeeded after'),
                $this->anything()
            );

        $retryClient = new RetryHttpClient($this->mockClient, 3, 10, false, [], $this->mockLogger);
        $retryClient->get($url);
    }

    public function testDefaultRetryStatusCodes(): void
    {
        $url = 'https://example.com/api/data';

        // Test some default retry status codes: 500, 502, 503, 504, 408, 429
        $mockResponse503 = $this->createMockResponse(503);
        $mockResponse200 = $this->createMockResponse(200);

        $this->mockClient->expects($this->exactly(2))
            ->method('get')
            ->willReturnOnConsecutiveCalls($mockResponse503, $mockResponse200);

        // Use default retry status codes (don't pass custom array)
        $retryClient = new RetryHttpClient($this->mockClient, 3, 10, false);
        $response    = $retryClient->get($url);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testExhaustedRetriesWithRetryableStatusCode(): void
    {
        $url             = 'https://example.com/api/data';
        $mockResponse503 = $this->createMockResponse(503);

        // All attempts return 503 (initial + 3 retries = 4 total)
        $this->mockClient->expects($this->exactly(4))
            ->method('get')
            ->with($url, [])
            ->willReturn($mockResponse503);

        // Collect all warning messages to verify
        $warningMessages = [];
        $this->mockLogger->expects($this->exactly(4))
            ->method('warning')
            ->willReturnCallback(function (string $message) use (&$warningMessages): void {
                $warningMessages[] = $message;
            });

        // Should NOT log info "succeeded after" - instead should log warning about exhausted retries
        $this->mockLogger->expects($this->never())
            ->method('info');

        $retryClient = new RetryHttpClient(
            $this->mockClient,
            3,          // maxRetries
            10,         // retryDelay
            false,      // no exponential backoff
            [503],      // retry on 503
            $this->mockLogger
        );

        // Should return the 503 response after exhausting retries
        $response = $retryClient->get($url);
        $this->assertEquals(503, $response->getStatusCode());

        // Verify we got the expected warning messages
        $this->assertCount(4, $warningMessages);
        // First 3 should be retry warnings
        $this->assertStringContainsString('returned retryable status 503', $warningMessages[0]);
        $this->assertStringContainsString('returned retryable status 503', $warningMessages[1]);
        $this->assertStringContainsString('returned retryable status 503', $warningMessages[2]);
        // 4th should be the exhaustion warning
        $this->assertStringContainsString('exhausted retries', $warningMessages[3]);
    }

    public function testNegativeMaxRetriesThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('maxRetries must be non-negative, got -1');

        new RetryHttpClient(
            $this->mockClient,
            -1, // Negative maxRetries
            1000,
            false,
            [],
            $this->mockLogger
        );
    }

    public function testNegativeRetryDelayThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('retryDelay must be positive, got -100');

        new RetryHttpClient(
            $this->mockClient,
            3,
            -100, // Negative retryDelay
            false,
            [],
            $this->mockLogger
        );
    }

    public function testZeroRetryDelayThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('retryDelay must be positive, got 0');

        new RetryHttpClient(
            $this->mockClient,
            3,
            0, // Zero retryDelay is invalid
            false,
            [],
            $this->mockLogger
        );
    }

    public function testInvalidRetryStatusCodeTypeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('retryStatusCodes must contain only integers, got string at index 1');

        new RetryHttpClient(
            $this->mockClient,
            3,
            1000,
            false,
            [500, '503', 504], // @phpstan-ignore-line - Testing invalid input intentionally
            $this->mockLogger
        );
    }

    public function testRetryStatusCodeBelowRangeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('retryStatusCodes must be in range 100-599, got 99 at index 0');

        new RetryHttpClient(
            $this->mockClient,
            3,
            1000,
            false,
            [99], // Below valid range
            $this->mockLogger
        );
    }

    public function testRetryStatusCodeAboveRangeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('retryStatusCodes must be in range 100-599, got 600 at index 0');

        new RetryHttpClient(
            $this->mockClient,
            3,
            1000,
            false,
            [600], // Above valid range
            $this->mockLogger
        );
    }

    public function testValidRetryStatusCodesAccepted(): void
    {
        // Test edge cases of valid range
        $retryClient = new RetryHttpClient(
            $this->mockClient,
            3,
            1000,
            false,
            [100, 199, 300, 400, 500, 599], // Valid range boundaries
            $this->mockLogger
        );

        $this->assertInstanceOf(RetryHttpClient::class, $retryClient);
    }

    public function testZeroMaxRetriesIsValid(): void
    {
        $url          = 'https://example.com/api/data';
        $mockResponse = $this->createMockResponse(200);

        $this->mockClient->expects($this->once())
            ->method('get')
            ->with($url, [])
            ->willReturn($mockResponse);

        // maxRetries = 0 should be valid (no retries, just one attempt)
        $retryClient = new RetryHttpClient(
            $this->mockClient,
            0, // Zero maxRetries is valid
            1000,
            false,
            [],
            $this->mockLogger
        );

        $response = $retryClient->get($url);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Helper to create mock response
     */
    private function createMockResponse(int $statusCode): ResponseInterface
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn($statusCode);
        return $mockResponse;
    }
}
