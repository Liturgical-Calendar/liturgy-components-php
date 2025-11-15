<?php

namespace LiturgicalCalendar\Components\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * HTTP Client Interface
 *
 * Defines the contract for HTTP operations in the liturgy-components library.
 * Implementations can use PSR-18 HTTP clients or fallback to native PHP functions.
 */
interface HttpClientInterface
{
    /**
     * Perform HTTP GET request
     *
     * @param string $url The URL to fetch
     * @param array<string,string> $headers Optional request headers
     * @return ResponseInterface PSR-7 HTTP response
     * @throws HttpException When the HTTP request fails
     */
    public function get(string $url, array $headers = []): ResponseInterface;

    /**
     * Perform HTTP POST request
     *
     * @param string $url The URL to post to
     * @param array<string,mixed>|string $body Request body (array will be JSON encoded)
     * @param array<string,string> $headers Optional request headers
     * @return ResponseInterface PSR-7 HTTP response
     * @throws HttpException When the HTTP request fails
     */
    public function post(string $url, array|string $body, array $headers = []): ResponseInterface;
}
