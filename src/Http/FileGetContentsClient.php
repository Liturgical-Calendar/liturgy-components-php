<?php

namespace LiturgicalCalendar\Components\Http;

use Psr\Http\Message\ResponseInterface;
use Nyholm\Psr7\Response;

/**
 * Legacy HTTP Client using file_get_contents()
 *
 * Fallback implementation for environments where PSR-18 clients are not available.
 * Uses native PHP file_get_contents() with stream contexts.
 * Returns PSR-7 Response objects for consistency.
 */
class FileGetContentsClient implements HttpClientInterface
{
    /**
     * @param int $timeout HTTP request timeout in seconds (default: 30)
     */
    public function __construct(
        private int $timeout = 30
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $url, array $headers = []): ResponseInterface
    {
        $context = $this->createContext('GET', $headers);

        // Suppress errors and capture response headers via reference
        $content = @file_get_contents($url, false, $context);

        if ($content === false) {
            throw new HttpException("Failed to fetch URL: {$url}");
        }

        // Parse response headers from $http_response_header magic variable
        // This variable is populated by file_get_contents when using stream context
        // @phpstan-ignore-next-line - $http_response_header is a magic variable
        $responseHeaderLines = isset($http_response_header) ? $http_response_header : [];
        $statusCode          = $this->parseStatusCode($responseHeaderLines, $url);
        $responseHeaders     = $this->parseHeaders($responseHeaderLines);

        return new Response($statusCode, $responseHeaders, $content);
    }

    /**
     * {@inheritDoc}
     */
    public function post(string $url, array|string $body, array $headers = []): ResponseInterface
    {
        $bodyContent = is_array($body) ? json_encode($body) : $body;
        if ($bodyContent === false) {
            throw new HttpException('Failed to encode request body as JSON');
        }

        // Auto-add Content-Type for JSON bodies if not specified (case-insensitive check)
        if (is_array($body) && !$this->hasHeader($headers, 'Content-Type')) {
            $headers['Content-Type'] = 'application/json';
        }

        $context = $this->createContext('POST', $headers, $bodyContent);

        $content = @file_get_contents($url, false, $context);

        if ($content === false) {
            throw new HttpException("Failed to post to URL: {$url}");
        }

        // Parse response headers from $http_response_header magic variable
        // @phpstan-ignore-next-line - $http_response_header is a magic variable
        $responseHeaderLines = isset($http_response_header) ? $http_response_header : [];
        $statusCode          = $this->parseStatusCode($responseHeaderLines, $url);
        $responseHeaders     = $this->parseHeaders($responseHeaderLines);

        return new Response($statusCode, $responseHeaders, $content);
    }

    /**
     * Create stream context for file_get_contents
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param array<string,string> $headers Request headers
     * @param string|null $body Request body
     * @return resource Stream context
     */
    private function createContext(string $method, array $headers, ?string $body = null)
    {
        $options = [
            'http' => [
                'method'        => $method,
                'header'        => $this->formatHeaders($headers),
                'ignore_errors' => true,
                'timeout'       => $this->timeout,
            ]
        ];

        if ($body !== null) {
            $options['http']['content'] = $body;
        }

        return stream_context_create($options);
    }

    /**
     * Check if a header exists (case-insensitive)
     *
     * HTTP header names are case-insensitive per RFC 7230.
     *
     * @param array<string,string> $headers
     * @param string $headerName Header name to check
     * @return bool True if header exists (case-insensitive)
     */
    private function hasHeader(array $headers, string $headerName): bool
    {
        $headerNameLower = strtolower($headerName);
        foreach (array_keys($headers) as $name) {
            if (strtolower($name) === $headerNameLower) {
                return true;
            }
        }
        return false;
    }

    /**
     * Format headers array into HTTP header string
     *
     * @param array<string,string> $headers
     * @return string
     */
    private function formatHeaders(array $headers): string
    {
        $formatted = [];
        foreach ($headers as $name => $value) {
            $formatted[] = "{$name}: {$value}";
        }
        return implode("\r\n", $formatted);
    }

    /**
     * Parse HTTP status code from response headers
     *
     * For HTTP/HTTPS URLs, strictly validates the status line and throws exceptions for malformed responses.
     * For non-HTTP protocols (data://, file://, etc.), returns 200 as they don't provide HTTP headers.
     *
     * @param array<int,string> $headers Response header lines
     * @param string $url The request URL (used to determine protocol strictness)
     * @return int HTTP status code
     * @throws HttpException When HTTP/HTTPS response has invalid or missing status line
     */
    private function parseStatusCode(array $headers, string $url): int
    {
        $isHttpProtocol = preg_match('/^https?:\/\//i', $url);

        if (empty($headers)) {
            if ($isHttpProtocol) {
                throw new HttpException('No HTTP response headers received - unable to determine status code');
            }
            // Non-HTTP protocols (data://, file://, etc.) don't provide HTTP headers
            return 200;
        }

        $statusLine = $headers[0];
        if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $statusLine, $matches)) {
            return (int) $matches[1];
        }

        if ($isHttpProtocol) {
            throw new HttpException("Invalid HTTP status line received: {$statusLine}");
        }

        // Non-HTTP protocols may have unexpected header format
        return 200;
    }

    /**
     * Parse response headers into associative array
     *
     * @param array<int,string> $headers
     * @return array<string,string>
     */
    private function parseHeaders(array $headers): array
    {
        $parsed = [];
        // Skip first line (status line)
        foreach (array_slice($headers, 1) as $header) {
            if (str_contains($header, ':')) {
                [$name, $value]      = explode(':', $header, 2);
                $parsed[trim($name)] = trim($value);
            }
        }
        return $parsed;
    }
}
