<?php

namespace LiturgicalCalendar\Components\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * HTTP Client Decorator with Logging
 *
 * Wraps an HttpClientInterface and logs all HTTP requests and responses.
 * Provides detailed metrics including duration, status codes, and errors.
 */
class LoggingHttpClient implements HttpClientInterface
{
    /**
     * @param HttpClientInterface $client The underlying HTTP client
     * @param LoggerInterface $logger PSR-3 logger instance
     */
    public function __construct(
        private HttpClientInterface $client,
        private LoggerInterface $logger = new NullLogger()
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $url, array $headers = []): ResponseInterface
    {
        $this->logger->info('HTTP GET request', [
            'url'     => $url,
            'headers' => $this->sanitizeHeaders($headers),
        ]);

        $startTime = microtime(true);

        try {
            $response = $this->client->get($url, $headers);
            $duration = microtime(true) - $startTime;

            $this->logger->info('HTTP GET response', [
                'url'           => $url,
                'status_code'   => $response->getStatusCode(),
                'duration_ms'   => round($duration * 1000, 2),
                'response_size' => $response->getBody()->getSize(),
            ]);

            return $response;
        } catch (HttpException $e) {
            $duration = microtime(true) - $startTime;

            $this->logger->error('HTTP GET failed', [
                'url'         => $url,
                'error'       => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'exception'   => get_class($e),
            ]);

            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function post(string $url, array|string $body, array $headers = []): ResponseInterface
    {
        // Calculate body size in bytes for consistent metrics
        $bodySize = is_string($body)
            ? strlen($body)
            : strlen(json_encode($body) ?: '[]');

        $this->logger->info('HTTP POST request', [
            'url'       => $url,
            'headers'   => $this->sanitizeHeaders($headers),
            'body_size' => $bodySize,
        ]);

        $startTime = microtime(true);

        try {
            $response = $this->client->post($url, $body, $headers);
            $duration = microtime(true) - $startTime;

            $this->logger->info('HTTP POST response', [
                'url'           => $url,
                'status_code'   => $response->getStatusCode(),
                'duration_ms'   => round($duration * 1000, 2),
                'response_size' => $response->getBody()->getSize(),
            ]);

            return $response;
        } catch (HttpException $e) {
            $duration = microtime(true) - $startTime;

            $this->logger->error('HTTP POST failed', [
                'url'         => $url,
                'error'       => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'exception'   => get_class($e),
            ]);

            throw $e;
        }
    }

    /**
     * Sanitize headers to remove sensitive information from logs
     *
     * @param array<string,string> $headers
     * @return array<string,string>
     */
    private function sanitizeHeaders(array $headers): array
    {
        $sanitized        = [];
        $sensitiveHeaders = ['authorization', 'api-key', 'x-api-key', 'cookie', 'set-cookie'];

        foreach ($headers as $name => $value) {
            if (in_array(strtolower($name), $sensitiveHeaders)) {
                $sanitized[$name] = '***REDACTED***';
            } else {
                $sanitized[$name] = $value;
            }
        }

        return $sanitized;
    }
}
