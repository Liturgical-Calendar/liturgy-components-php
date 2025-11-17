<?php

namespace LiturgicalCalendar\Components\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-18 HTTP Client Implementation
 *
 * Wraps a PSR-18 HTTP client with PSR-7 request/response handling.
 * Provides a simplified interface for GET and POST operations.
 */
class PsrHttpClient implements HttpClientInterface
{
    /**
     * @param ClientInterface $httpClient PSR-18 HTTP client
     * @param RequestFactoryInterface $requestFactory PSR-17 request factory
     * @param StreamFactoryInterface $streamFactory PSR-17 stream factory
     */
    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $url, array $headers = []): ResponseInterface
    {
        $request = $this->requestFactory->createRequest('GET', $url);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        try {
            return $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new HttpException(
                "HTTP GET request failed: {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function post(string $url, array|string $body, array $headers = []): ResponseInterface
    {
        $request = $this->requestFactory->createRequest('POST', $url);

        // Convert array body to JSON
        $bodyContent = is_array($body) ? json_encode($body) : $body;
        if ($bodyContent === false) {
            throw new HttpException('Failed to encode request body as JSON');
        }

        $stream  = $this->streamFactory->createStream($bodyContent);
        $request = $request->withBody($stream);

        // Auto-add Content-Type for JSON bodies if not specified
        if (is_array($body) && !isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/json';
        }

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        try {
            return $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new HttpException(
                "HTTP POST request failed: {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        }
    }
}
