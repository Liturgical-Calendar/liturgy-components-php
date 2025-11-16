<?php

namespace LiturgicalCalendar\Components\Tests\Http;

use PHPUnit\Framework\TestCase;
use LiturgicalCalendar\Components\Http\CircuitBreakerHttpClient;
use LiturgicalCalendar\Components\Http\HttpClientInterface;
use LiturgicalCalendar\Components\Http\HttpException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Test suite for CircuitBreakerHttpClient
 */
class CircuitBreakerHttpClientTest extends TestCase
{
    private HttpClientInterface $mockClient;
    private LoggerInterface $mockLogger;
    private int $currentTime = 1000000; // Arbitrary starting timestamp

    protected function setUp(): void
    {
        $this->mockClient = $this->createMock(HttpClientInterface::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->currentTime = 1000000; // Reset time for each test
    }

    /**
     * Create a controllable time provider for testing
     * @return callable(): int
     */
    private function createTimeProvider(): callable
    {
        return fn(): int => $this->currentTime;
    }

    /**
     * Advance the mock time by the given number of seconds
     */
    private function advanceTime(int $seconds): void
    {
        $this->currentTime += $seconds;
    }

    public function testCircuitClosedOnSuccess(): void
    {
        $url          = 'https://example.com/api/data';
        $mockResponse = $this->createMockResponse(200);

        $this->mockClient->expects($this->once())
            ->method('get')
            ->with($url, [])
            ->willReturn($mockResponse);

        $circuitBreaker = new CircuitBreakerHttpClient($this->mockClient, 5, 60, 2, $this->mockLogger);

        $response = $circuitBreaker->get($url);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('closed', $circuitBreaker->getState());
    }

    public function testCircuitOpensAfterFailureThreshold(): void
    {
        $url = 'https://example.com/api/data';

        // Fail 5 times to reach threshold
        $this->mockClient->expects($this->exactly(5))
            ->method('get')
            ->with($url, [])
            ->willThrowException(new HttpException('Network error'));

        $circuitBreaker = new CircuitBreakerHttpClient(
            $this->mockClient,
            5, // failure threshold
            60, // recovery timeout
            2,
            $this->mockLogger
        );

        // First 5 requests should fail
        for ($i = 0; $i < 5; $i++) {
            try {
                $circuitBreaker->get($url);
                $this->fail('Expected HttpException');
            } catch (HttpException $e) {
                $this->assertEquals('Network error', $e->getMessage());
            }
        }

        // Circuit should now be open
        $this->assertEquals('open', $circuitBreaker->getState());
        $this->assertEquals(5, $circuitBreaker->getFailureCount());
    }

    public function testCircuitOpenBlocksRequests(): void
    {
        $url = 'https://example.com/api/data';

        // Fail 5 times to open circuit
        $this->mockClient->expects($this->exactly(5))
            ->method('get')
            ->willThrowException(new HttpException('Network error'));

        $circuitBreaker = new CircuitBreakerHttpClient($this->mockClient, 5, 60, 2, $this->mockLogger);

        // Open the circuit
        for ($i = 0; $i < 5; $i++) {
            try {
                $circuitBreaker->get($url);
            } catch (HttpException $e) {
                // Expected
            }
        }

        // Next request should be blocked without hitting underlying client
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Service temporarily unavailable (circuit breaker open)');

        $circuitBreaker->get($url);
    }

    public function testCircuitEntersHalfOpenAfterTimeout(): void
    {
        $url          = 'https://example.com/api/data';
        $mockResponse = $this->createMockResponse(200);

        // Fail to open circuit
        $this->mockClient->expects($this->exactly(6)) // 5 failures + 1 success
            ->method('get')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new HttpException('Error 1')),
                $this->throwException(new HttpException('Error 2')),
                $this->throwException(new HttpException('Error 3')),
                $this->throwException(new HttpException('Error 4')),
                $this->throwException(new HttpException('Error 5')),
                $mockResponse
            );

        $circuitBreaker = new CircuitBreakerHttpClient(
            $this->mockClient,
            5, // failure threshold
            1, // recovery timeout (1 second)
            2,
            $this->mockLogger,
            $this->createTimeProvider()
        );

        // Open the circuit
        for ($i = 0; $i < 5; $i++) {
            try {
                $circuitBreaker->get($url);
            } catch (HttpException $e) {
                // Expected
            }
        }

        $this->assertEquals('open', $circuitBreaker->getState());

        // Advance time past recovery timeout (no sleep needed!)
        $this->advanceTime(2);

        // Next request should enter HALF_OPEN and succeed
        $response = $circuitBreaker->get($url);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testCircuitClosesAfterSuccessesInHalfOpen(): void
    {
        $url          = 'https://example.com/api/data';
        $mockResponse = $this->createMockResponse(200);

        $this->mockClient->expects($this->exactly(7)) // 5 failures + 2 successes
            ->method('get')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new HttpException('Error 1')),
                $this->throwException(new HttpException('Error 2')),
                $this->throwException(new HttpException('Error 3')),
                $this->throwException(new HttpException('Error 4')),
                $this->throwException(new HttpException('Error 5')),
                $mockResponse,
                $mockResponse
            );

        $circuitBreaker = new CircuitBreakerHttpClient(
            $this->mockClient,
            5, // failure threshold
            1, // recovery timeout
            2, // success threshold
            $this->mockLogger,
            $this->createTimeProvider()
        );

        // Open the circuit
        for ($i = 0; $i < 5; $i++) {
            try {
                $circuitBreaker->get($url);
            } catch (HttpException $e) {
                // Expected
            }
        }

        // Advance time and make 2 successful requests
        $this->advanceTime(2);
        $circuitBreaker->get($url);
        $circuitBreaker->get($url);

        // Circuit should be closed
        $this->assertEquals('closed', $circuitBreaker->getState());
        $this->assertEquals(0, $circuitBreaker->getFailureCount());
    }

    public function testCircuitReopensOnFailureInHalfOpen(): void
    {
        $url = 'https://example.com/api/data';

        $this->mockClient->expects($this->exactly(6)) // 5 to open + 1 in half-open
            ->method('get')
            ->willThrowException(new HttpException('Network error'));

        $circuitBreaker = new CircuitBreakerHttpClient(
            $this->mockClient,
            5,
            1,
            2,
            $this->mockLogger,
            $this->createTimeProvider()
        );

        // Open the circuit
        for ($i = 0; $i < 5; $i++) {
            try {
                $circuitBreaker->get($url);
            } catch (HttpException $e) {
                // Expected
            }
        }

        // Advance time to trigger half-open state
        $this->advanceTime(2);

        // Failure in half-open should reopen circuit
        try {
            $circuitBreaker->get($url);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            // Expected
        }

        $this->assertEquals('open', $circuitBreaker->getState());
    }

    public function testPostRequest(): void
    {
        $url          = 'https://example.com/api/data';
        $body         = ['key' => 'value'];
        $mockResponse = $this->createMockResponse(200);

        $this->mockClient->expects($this->once())
            ->method('post')
            ->with($url, $body, [])
            ->willReturn($mockResponse);

        $circuitBreaker = new CircuitBreakerHttpClient($this->mockClient, 5, 60, 2, $this->mockLogger);

        $response = $circuitBreaker->post($url, $body);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testReset(): void
    {
        $url = 'https://example.com/api/data';

        $this->mockClient->expects($this->exactly(5))
            ->method('get')
            ->willThrowException(new HttpException('Network error'));

        $circuitBreaker = new CircuitBreakerHttpClient($this->mockClient, 5, 60, 2, $this->mockLogger);

        // Open the circuit
        for ($i = 0; $i < 5; $i++) {
            try {
                $circuitBreaker->get($url);
            } catch (HttpException $e) {
                // Expected
            }
        }

        $this->assertEquals('open', $circuitBreaker->getState());
        $this->assertEquals(5, $circuitBreaker->getFailureCount());

        // Reset
        $circuitBreaker->reset();

        $this->assertEquals('closed', $circuitBreaker->getState());
        $this->assertEquals(0, $circuitBreaker->getFailureCount());
    }

    public function testSuccessResetsFailureCountInClosedState(): void
    {
        $url          = 'https://example.com/api/data';
        $mockResponse = $this->createMockResponse(200);

        $this->mockClient->expects($this->exactly(4))
            ->method('get')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new HttpException('Error 1')),
                $this->throwException(new HttpException('Error 2')),
                $this->throwException(new HttpException('Error 3')),
                $mockResponse
            );

        $circuitBreaker = new CircuitBreakerHttpClient($this->mockClient, 5, 60, 2, $this->mockLogger);

        // 3 failures (below threshold)
        for ($i = 0; $i < 3; $i++) {
            try {
                $circuitBreaker->get($url);
            } catch (HttpException $e) {
                // Expected
            }
        }

        $this->assertEquals(3, $circuitBreaker->getFailureCount());
        $this->assertEquals('closed', $circuitBreaker->getState());

        // Success should reset count
        $circuitBreaker->get($url);

        $this->assertEquals(0, $circuitBreaker->getFailureCount());
        $this->assertEquals('closed', $circuitBreaker->getState());
    }

    public function testLogsStateTransitions(): void
    {
        $url          = 'https://example.com/api/data';
        $mockResponse = $this->createMockResponse(200);

        $this->mockClient->method('get')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new HttpException('Error 1')),
                $this->throwException(new HttpException('Error 2')),
                $this->throwException(new HttpException('Error 3')),
                $this->throwException(new HttpException('Error 4')),
                $this->throwException(new HttpException('Error 5')),
                $mockResponse,
                $mockResponse
            );

        // Expect error log when circuit opens
        $this->mockLogger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('OPEN'));

        // Expect info logs for state transitions
        $this->mockLogger->expects($this->atLeastOnce())
            ->method('info');

        $circuitBreaker = new CircuitBreakerHttpClient(
            $this->mockClient,
            5,
            1,
            2,
            $this->mockLogger,
            $this->createTimeProvider()
        );

        // Open circuit
        for ($i = 0; $i < 5; $i++) {
            try {
                $circuitBreaker->get($url);
            } catch (HttpException $e) {
                // Expected
            }
        }

        // Advance time and close circuit
        $this->advanceTime(2);
        $circuitBreaker->get($url);
        $circuitBreaker->get($url);
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
