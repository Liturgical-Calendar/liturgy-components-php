<?php

namespace LiturgicalCalendar\Components\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * HTTP Client decorator implementing Circuit Breaker pattern
 *
 * Prevents cascading failures by failing fast after consecutive errors.
 * Implements three states: CLOSED (normal), OPEN (failing fast), HALF_OPEN (testing recovery).
 *
 * @see https://martinfowler.com/bliki/CircuitBreaker.html
 */
class CircuitBreakerHttpClient implements HttpClientInterface
{
    private const STATE_CLOSED    = 'closed';      // Normal operation
    private const STATE_OPEN      = 'open';          // Failing fast
    private const STATE_HALF_OPEN = 'half_open'; // Testing recovery

    /** @var array<int> Default HTTP status codes that should trip the circuit breaker */
    private const DEFAULT_FAILURE_STATUS_CODES = [];

    private string $state         = self::STATE_CLOSED;
    private int $failureCount     = 0;
    private int $successCount     = 0;
    private ?int $lastFailureTime = null;

    /** @var array<int> */
    private array $failureStatusCodes;

    /**
     * @param HttpClientInterface $client Underlying HTTP client
     * @param int $failureThreshold Number of failures before opening circuit (default: 5)
     * @param int $recoveryTimeout Time in seconds before attempting recovery (default: 60)
     * @param int $successThreshold Number of successes in HALF_OPEN before closing circuit (default: 2)
     * @param LoggerInterface $logger PSR-3 logger for circuit breaker events
     * @param callable(): int $timeProvider Function that returns current Unix timestamp (for testing)
     * @param array<int> $failureStatusCodes HTTP status codes that should trip the circuit breaker (default: [] - only exceptions)
     */
    public function __construct(
        private HttpClientInterface $client,
        private int $failureThreshold = 5,
        private int $recoveryTimeout = 60,
        private int $successThreshold = 2,
        private LoggerInterface $logger = new NullLogger(),
        private $timeProvider = null,
        array $failureStatusCodes = self::DEFAULT_FAILURE_STATUS_CODES
    ) {
        $this->timeProvider       = $timeProvider ?? time(...);
        $this->failureStatusCodes = $failureStatusCodes;
    }

    /**
     * @param string $url
     * @param array<string, string> $headers
     * @return ResponseInterface
     * @throws HttpException
     */
    public function get(string $url, array $headers = []): ResponseInterface
    {
        $this->updateState();

        if ($this->state === self::STATE_OPEN) {
            $this->logger->warning('Circuit breaker OPEN - request blocked', [
                'url'           => $url,
                'failure_count' => $this->failureCount,
            ]);
            throw new HttpException('Service temporarily unavailable (circuit breaker open)');
        }

        try {
            $response = $this->client->get($url, $headers);

            // Check if status code should be treated as a failure
            $statusCode = $response->getStatusCode();
            if (in_array($statusCode, $this->failureStatusCodes, true)) {
                $this->logger->warning('Circuit breaker: failure status code detected', [
                    'url'         => $url,
                    'status_code' => $statusCode,
                    'state'       => $this->state,
                ]);
                $this->onFailure();
                // Still return the response to the caller
                return $response;
            }

            $this->onSuccess();
            return $response;
        } catch (HttpException $e) {
            $this->onFailure();
            throw $e;
        }
    }

    /**
     * @param string $url
     * @param array<string, mixed>|string $body
     * @param array<string, string> $headers
     * @return ResponseInterface
     * @throws HttpException
     */
    public function post(string $url, array|string $body, array $headers = []): ResponseInterface
    {
        $this->updateState();

        if ($this->state === self::STATE_OPEN) {
            $this->logger->warning('Circuit breaker OPEN - request blocked', [
                'url'           => $url,
                'failure_count' => $this->failureCount,
            ]);
            throw new HttpException('Service temporarily unavailable (circuit breaker open)');
        }

        try {
            $response = $this->client->post($url, $body, $headers);

            // Check if status code should be treated as a failure
            $statusCode = $response->getStatusCode();
            if (in_array($statusCode, $this->failureStatusCodes, true)) {
                $this->logger->warning('Circuit breaker: failure status code detected', [
                    'url'         => $url,
                    'status_code' => $statusCode,
                    'state'       => $this->state,
                ]);
                $this->onFailure();
                // Still return the response to the caller
                return $response;
            }

            $this->onSuccess();
            return $response;
        } catch (HttpException $e) {
            $this->onFailure();
            throw $e;
        }
    }

    /**
     * Update circuit breaker state based on time and current state
     *
     * @return void
     */
    private function updateState(): void
    {
        if ($this->state === self::STATE_OPEN && $this->lastFailureTime !== null) {
            $timeSinceLastFailure = ( $this->timeProvider )() - $this->lastFailureTime;

            if ($timeSinceLastFailure >= $this->recoveryTimeout) {
                $this->state        = self::STATE_HALF_OPEN;
                $this->successCount = 0;
                $this->logger->info('Circuit breaker entering HALF_OPEN state', [
                    'time_since_failure' => $timeSinceLastFailure,
                    'recovery_timeout'   => $this->recoveryTimeout,
                ]);
            }
        }
    }

    /**
     * Handle successful request
     *
     * @return void
     */
    private function onSuccess(): void
    {
        if ($this->state === self::STATE_HALF_OPEN) {
            $this->successCount++;

            $this->logger->debug('Circuit breaker success in HALF_OPEN state', [
                'success_count'     => $this->successCount,
                'success_threshold' => $this->successThreshold,
            ]);

            if ($this->successCount >= $this->successThreshold) {
                // Preserve success count for logging before resetting
                $successesAchieved = $this->successCount;

                $this->state           = self::STATE_CLOSED;
                $this->failureCount    = 0;
                $this->successCount    = 0;
                $this->lastFailureTime = null;

                $this->logger->info('Circuit breaker CLOSED - service recovered', ['successes' => $successesAchieved]);
            }
        } elseif ($this->state === self::STATE_CLOSED) {
            // Reset failure count on success in CLOSED state
            if ($this->failureCount > 0) {
                $this->failureCount = 0;
                $this->logger->debug('Circuit breaker failure count reset after success');
            }
        }
    }

    /**
     * Handle failed request
     *
     * @return void
     */
    private function onFailure(): void
    {
        $this->failureCount++;
        $this->lastFailureTime = ( $this->timeProvider )();

        if ($this->state === self::STATE_HALF_OPEN) {
            // Failure in HALF_OPEN state reopens the circuit immediately
            $this->state        = self::STATE_OPEN;
            $this->successCount = 0;

            $this->logger->warning('Circuit breaker reopened due to failure in HALF_OPEN state', [
                'failure_count' => $this->failureCount,
            ]);
        } elseif ($this->state === self::STATE_CLOSED) {
            if ($this->failureCount >= $this->failureThreshold) {
                $this->state = self::STATE_OPEN;

                $this->logger->error('Circuit breaker OPEN - too many failures', [
                    'failure_count'     => $this->failureCount,
                    'failure_threshold' => $this->failureThreshold,
                ]);
            } else {
                $this->logger->debug('Circuit breaker failure recorded', [
                    'failure_count'     => $this->failureCount,
                    'failure_threshold' => $this->failureThreshold,
                ]);
            }
        }
    }

    /**
     * Get current circuit breaker state (for testing/monitoring)
     *
     * @return string
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * Get current failure count (for testing/monitoring)
     *
     * @return int
     */
    public function getFailureCount(): int
    {
        return $this->failureCount;
    }

    /**
     * Reset circuit breaker to initial state (for testing)
     *
     * @return void
     */
    public function reset(): void
    {
        $this->state           = self::STATE_CLOSED;
        $this->failureCount    = 0;
        $this->successCount    = 0;
        $this->lastFailureTime = null;

        $this->logger->info('Circuit breaker manually reset to CLOSED state');
    }
}
