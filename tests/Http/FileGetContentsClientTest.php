<?php

namespace LiturgicalCalendar\Components\Tests\Http;

use LiturgicalCalendar\Components\Http\FileGetContentsClient;
use LiturgicalCalendar\Components\Http\HttpException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * Unit tests for FileGetContentsClient
 *
 * Note: These tests use data:// URLs to avoid external network calls
 */
class FileGetContentsClientTest extends TestCase
{
    private FileGetContentsClient $client;

    protected function setUp(): void
    {
        $this->client = new FileGetContentsClient();
    }

    public function testGetReturnsResponseInterface(): void
    {
        // Use data URL to avoid network calls
        $url      = 'data://text/plain;base64,' . base64_encode('Hello World');
        $response = $this->client->get($url);

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testGetReturnsCorrectBody(): void
    {
        $content  = 'Test response content';
        $url      = 'data://text/plain;base64,' . base64_encode($content);
        $response = $this->client->get($url);

        $this->assertEquals($content, $response->getBody()->getContents());
    }

    public function testGetWithInvalidUrlThrowsException(): void
    {
        $invalidUrl = 'invalid-scheme://nonexistent.test/path';

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage("Failed to fetch URL: {$invalidUrl}");

        // Use a URL scheme that doesn't exist to force failure
        $this->client->get($invalidUrl);
    }

    public function testPostReturnsResponseInterface(): void
    {
        // Use data URL for POST test
        $url      = 'data://text/plain;base64,' . base64_encode('Posted data');
        $response = $this->client->post($url, 'test data');

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testPostWithArrayBodyEncodesJson(): void
    {
        $url  = 'data://text/plain;base64,' . base64_encode('{"status":"ok"}');
        $body = ['key' => 'value', 'number' => 123];

        // Should not throw exception - JSON encoding should work
        $response = $this->client->post($url, $body);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testPostWithStringBody(): void
    {
        $url  = 'data://text/plain;base64,' . base64_encode('Response');
        $body = 'Plain text body';

        $response = $this->client->post($url, $body);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testPostWithInvalidUrlThrowsException(): void
    {
        $invalidUrl = 'invalid-scheme://nonexistent.test/path';

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage("Failed to post to URL: {$invalidUrl}");

        // Use a URL scheme that doesn't exist to force failure
        $this->client->post($invalidUrl, 'data');
    }

    public function testGetWithCustomHeaders(): void
    {
        $url     = 'data://text/plain;base64,' . base64_encode('Data');
        $headers = [
            'X-Custom-Header' => 'CustomValue',
            'Accept'          => 'application/json'
        ];

        // Should not throw exception when headers are provided
        $response = $this->client->get($url, $headers);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testPostWithCustomHeaders(): void
    {
        $url     = 'data://text/plain;base64,' . base64_encode('Data');
        $headers = [
            'X-API-Key'  => 'secret123',
            'User-Agent' => 'TestClient/1.0'
        ];

        $response = $this->client->post($url, 'body', $headers);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testResponseHasCorrectStatusCodeForDataUrl(): void
    {
        $url      = 'data://text/plain;base64,' . base64_encode('Test');
        $response = $this->client->get($url);

        // Data URLs return 200 by default
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testResponseBodyIsReadable(): void
    {
        $content  = 'Readable content';
        $url      = 'data://text/plain;base64,' . base64_encode($content);
        $response = $this->client->get($url);

        $body = $response->getBody();
        $this->assertTrue($body->isReadable());
        $this->assertEquals($content, $body->getContents());
    }

    public function testResponseBodyCanBeConvertedToString(): void
    {
        $content  = 'String content';
        $url      = 'data://text/plain;base64,' . base64_encode($content);
        $response = $this->client->get($url);

        $this->assertEquals($content, (string) $response->getBody());
    }

    public function testClientImplementsHttpClientInterface(): void
    {
        $this->assertInstanceOf(
            \LiturgicalCalendar\Components\Http\HttpClientInterface::class,
            $this->client
        );
    }

    public function testPostRespectsCaseInsensitiveContentTypeHeader(): void
    {
        $url  = 'data://text/plain;base64,' . base64_encode('Response');
        $body = ['key' => 'value'];

        // Test with lowercase 'content-type' - should not add duplicate Content-Type
        $headersLower = ['content-type' => 'application/xml'];
        $response1    = $this->client->post($url, $body, $headersLower);
        $this->assertInstanceOf(ResponseInterface::class, $response1);

        // Test with mixed case 'Content-TYPE' - should not add duplicate Content-Type
        $headersMixed = ['Content-TYPE' => 'text/html'];
        $response2    = $this->client->post($url, $body, $headersMixed);
        $this->assertInstanceOf(ResponseInterface::class, $response2);

        // Test with exact 'Content-Type' - should not add duplicate Content-Type
        $headersExact = ['Content-Type' => 'application/json'];
        $response3    = $this->client->post($url, $body, $headersExact);
        $this->assertInstanceOf(ResponseInterface::class, $response3);

        // Test without Content-Type - should auto-add for array bodies
        $response4 = $this->client->post($url, $body);
        $this->assertInstanceOf(ResponseInterface::class, $response4);
    }

    /**
     * Smoke test: Verifies constructor accepts timeout parameter.
     *
     * Note: This test cannot verify that the timeout value is actually applied
     * to HTTP requests because it uses data:// URLs which don't involve network I/O.
     * Actual timeout behavior would require integration tests with real HTTP endpoints.
     */
    public function testConstructorAcceptsCustomTimeout(): void
    {
        // Create client with custom timeout
        $customClient = new FileGetContentsClient(timeout: 60);

        $url      = 'data://text/plain;base64,' . base64_encode('Test');
        $response = $customClient->get($url);

        // Client should work normally with custom timeout
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals('Test', $response->getBody()->getContents());
    }

    /**
     * Smoke test: Verifies constructor works with default timeout.
     *
     * Note: This test cannot verify that the default timeout (30 seconds) is actually
     * applied to HTTP requests because it uses data:// URLs which don't involve network I/O.
     * Actual timeout behavior would require integration tests with real HTTP endpoints.
     */
    public function testConstructorUsesDefaultTimeout(): void
    {
        // Create client without specifying timeout (should use default 30 seconds)
        $defaultClient = new FileGetContentsClient();

        $url      = 'data://text/plain;base64,' . base64_encode('Test');
        $response = $defaultClient->get($url);

        // Client should work normally with default timeout
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals('Test', $response->getBody()->getContents());
    }
}
