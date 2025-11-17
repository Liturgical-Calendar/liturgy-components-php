<?php

namespace LiturgicalCalendar\Components\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * HTTP Client decorator that retries failed requests
 *
 * Implements retry logic with exponential backoff for failed HTTP requests.
 * Configurable retry attempts, delay, and which errors to retry.
 */
class RetryHttpClient implements HttpClientInterface
{
    /** @var array<int> Default HTTP status codes to retry */
    public const DEFAULT_RETRY_STATUS_CODES = [408, 429, 500, 502, 503, 504];

    /** @var array<int> */
    private array $retryStatusCodes;

    /**
     * @var callable(int): void
     * Callable that performs sleep with delay in milliseconds
     */
    private $sleepFunction;

    /**
     * @param HttpClientInterface $client Underlying HTTP client
     * @param int $maxRetries Maximum number of retry attempts (default: 3)
     * @param int $retryDelay Initial retry delay in milliseconds (default: 1000)
     * @param bool $useExponentialBackoff Whether to use exponential backoff (default: true)
     * @param array<int> $retryStatusCodes HTTP status codes to retry (default: DEFAULT_RETRY_STATUS_CODES)
     * @param LoggerInterface $logger PSR-3 logger for retry events
     * @param callable(int): void|null $sleepFunction Optional sleep function for testing (receives delay in ms)
     */
    public function __construct(
        private HttpClientInterface $client,
        private int $maxRetries = 3,
        private int $retryDelay = 1000,
        private bool $useExponentialBackoff = true,
        array $retryStatusCodes = self::DEFAULT_RETRY_STATUS_CODES,
        private LoggerInterface $logger = new NullLogger(),
        ?callable $sleepFunction = null
    ) {
        $this->retryStatusCodes = $retryStatusCodes;
        $this->sleepFunction    = $sleepFunction ?? static function (int $delayMs): void {
            usleep($delayMs * 1000);
        };
    }

    /**
     * @param string $url
     * @param array<string, string> $headers
     * @return ResponseInterface
     * @throws HttpException
     */
    public function get(string $url, array $headers = []): ResponseInterface
    {
        return $this->executeWithRetry(
            fn() => $this->client->get($url, $headers),
            'GET',
            $url
        );
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
        return $this->executeWithRetry(
            fn() => $this->client->post($url, $body, $headers),
            'POST',
            $url
        );
    }

    /**
     * Execute request with retry logic
     *
     * @param callable(): ResponseInterface $request
     * @param string $method
     * @param string $url
     * @return ResponseInterface
     * @throws HttpException
     */
    private function executeWithRetry(callable $request, string $method, string $url): ResponseInterface
    {
        $attempt       = 0;
        $lastException = null;

        while ($attempt <= $this->maxRetries) {
            try {
                $response = $request();

                // Check if response status code should trigger a retry
                $statusCode = $response->getStatusCode();
                if ($attempt < $this->maxRetries && in_array($statusCode, $this->retryStatusCodes, true)) {
                    $this->logger->warning("HTTP {$method} returned retryable status {$statusCode}", [
                        'url'         => $url,
                        'attempt'     => $attempt + 1,
                        'max_retries' => $this->maxRetries,
                        'status_code' => $statusCode,
                    ]);

                    $attempt++;
                    $this->sleep($attempt);
                    continue;
                }

                // Success or exhausted retries
                if ($attempt > 0) {
                    // Check if we exhausted retries while still getting a retryable status code
                    if (in_array($statusCode, $this->retryStatusCodes, true)) {
                        $this->logger->warning(
                            "HTTP {$method} exhausted retries, returning response with retryable status {$statusCode}",
                            [
                                'url'         => $url,
                                'attempts'    => $attempt + 1,
                                'status_code' => $statusCode,
                                'max_retries' => $this->maxRetries,
                            ]
                        );
                    } else {
                        $this->logger->info("HTTP {$method} succeeded after {$attempt} retries", [
                            'url'         => $url,
                            'attempts'    => $attempt + 1,
                            'status_code' => $statusCode,
                        ]);
                    }
                }

                return $response;
            } catch (HttpException $e) {
                $lastException = $e;

                if ($attempt >= $this->maxRetries) {
                    $this->logger->error("HTTP {$method} failed after {$this->maxRetries} retries", [
                        'url'      => $url,
                        'attempts' => $attempt + 1,
                        'error'    => $e->getMessage(),
                    ]);
                    throw $e;
                }

                $this->logger->warning("HTTP {$method} failed, retrying", [
                    'url'         => $url,
                    'attempt'     => $attempt + 1,
                    'max_retries' => $this->maxRetries,
                    'error'       => $e->getMessage(),
                ]);

                $attempt++;
                $this->sleep($attempt);
            }
        }

        // Should never reach here, but if we do, throw the last exception
        if ($lastException !== null) {
            throw $lastException;
        }

        throw new HttpException("Request failed after {$this->maxRetries} retries");
    }

    /**
     * Sleep with optional exponential backoff
     *
     * @param int $attempt Current attempt number (1-based)
     * @return void
     */
    private function sleep(int $attempt): void
    {
        if ($this->useExponentialBackoff) {
            // Exponential backoff: delay * (2 ^ (attempt - 1))
            // Attempt 1: delay * 1 = delay
            // Attempt 2: delay * 2 = 2x delay
            // Attempt 3: delay * 4 = 4x delay
            $delayMs = $this->retryDelay * ( 2 ** ( $attempt - 1 ) );
        } else {
            // Linear backoff: same delay every time
            $delayMs = $this->retryDelay;
        }

        $this->logger->debug('Sleeping before retry', [
            'attempt'  => $attempt,
            'delay_ms' => $delayMs,
            'backoff'  => $this->useExponentialBackoff ? 'exponential' : 'linear',
        ]);

        ( $this->sleepFunction )($delayMs);
    }
}
