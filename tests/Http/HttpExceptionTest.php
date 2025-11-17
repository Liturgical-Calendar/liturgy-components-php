<?php

namespace LiturgicalCalendar\Components\Tests\Http;

use LiturgicalCalendar\Components\Http\HttpException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for HttpException
 */
class HttpExceptionTest extends TestCase
{
    public function testConstructorWithMessage(): void
    {
        $exception = new HttpException('Test error message');

        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertEquals('Test error message', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
    }

    public function testConstructorWithMessageAndCode(): void
    {
        $exception = new HttpException('Test error', 404);

        $this->assertEquals('Test error', $exception->getMessage());
        $this->assertEquals(404, $exception->getCode());
    }

    public function testConstructorWithPreviousException(): void
    {
        $previous  = new \RuntimeException('Previous error');
        $exception = new HttpException('HTTP error', 500, $previous);

        $this->assertEquals('HTTP error', $exception->getMessage());
        $this->assertEquals(500, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testExceptionCanBeThrown(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Request failed');
        $this->expectExceptionCode(503);

        throw new HttpException('Request failed', 503);
    }

    public function testExceptionCanBeCaught(): void
    {
        try {
            throw new HttpException('Network error', 0);
        } catch (HttpException $e) {
            $this->assertEquals('Network error', $e->getMessage());
            $this->assertInstanceOf(HttpException::class, $e);
        }
    }
}
