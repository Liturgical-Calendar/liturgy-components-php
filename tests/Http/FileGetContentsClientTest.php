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
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Failed to fetch URL');

        // Use a URL scheme that doesn't exist to force failure
        $this->client->get('invalid-scheme://nonexistent.test/path');
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
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Failed to post to URL');

        // Use a URL scheme that doesn't exist to force failure
        $this->client->post('invalid-scheme://nonexistent.test/path', 'data');
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
}
