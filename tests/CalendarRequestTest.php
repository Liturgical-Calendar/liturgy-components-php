<?php

namespace LiturgicalCalendar\Components\Tests;

use LiturgicalCalendar\Components\CalendarRequest;
use LiturgicalCalendar\Components\ApiClient;
use PHPUnit\Framework\TestCase;

class CalendarRequestTest extends TestCase
{
    private const API_URL = 'https://litcal.johnromanodorazio.com/api/dev';

    protected function setUp(): void
    {
        // Reset singletons before each test
        ApiClient::resetForTesting();
    }

    public function testGetRequestUrlForGeneralCalendar(): void
    {
        $request = new CalendarRequest(apiUrl: self::API_URL);
        $url     = $request->getRequestUrl();

        $this->assertEquals(
            self::API_URL . '/calendar',
            $url,
            'Request URL for general calendar should be base URL + /calendar'
        );
    }

    public function testGetRequestUrlForNationalCalendar(): void
    {
        $request = new CalendarRequest(apiUrl: self::API_URL);
        $url     = $request->nation('US')->getRequestUrl();

        $this->assertEquals(
            self::API_URL . '/calendar/nation/US',
            $url,
            'Request URL for national calendar should include nation code'
        );
    }

    public function testGetRequestUrlForDiocesanCalendar(): void
    {
        $request = new CalendarRequest(apiUrl: self::API_URL);
        $url     = $request->diocese('boston_us')->getRequestUrl();

        $this->assertEquals(
            self::API_URL . '/calendar/diocese/boston_us',
            $url,
            'Request URL for diocesan calendar should include diocese ID'
        );
    }

    public function testGetRequestUrlWithYear(): void
    {
        $request = new CalendarRequest(apiUrl: self::API_URL);
        $url     = $request->year(2024)->getRequestUrl();

        $this->assertEquals(
            self::API_URL . '/calendar/2024',
            $url,
            'Request URL with year should include year in path'
        );
    }

    public function testGetRequestUrlForNationalCalendarWithYear(): void
    {
        $request = new CalendarRequest(apiUrl: self::API_URL);
        $url     = $request->nation('US')->year(2025)->getRequestUrl();

        $this->assertEquals(
            self::API_URL . '/calendar/nation/US/2025',
            $url,
            'Request URL should include nation and year'
        );
    }

    public function testGetRequestUrlForDiocesanCalendarWithYear(): void
    {
        $request = new CalendarRequest(apiUrl: self::API_URL);
        $url     = $request->diocese('boston_us')->year(2025)->getRequestUrl();

        $this->assertEquals(
            self::API_URL . '/calendar/diocese/boston_us/2025',
            $url,
            'Request URL should include diocese and year'
        );
    }

    public function testGetRequestUrlEncodesSpecialCharacters(): void
    {
        $request = new CalendarRequest(apiUrl: self::API_URL);
        $url     = $request->diocese('test diocese')->getRequestUrl();

        $this->assertEquals(
            self::API_URL . '/calendar/diocese/test%20diocese',
            $url,
            'Request URL should properly encode special characters'
        );
    }

    public function testGetRequestUrlTrimsTrailingSlash(): void
    {
        $request = new CalendarRequest(apiUrl: self::API_URL . '/');
        $url     = $request->getRequestUrl();

        $this->assertEquals(
            self::API_URL . '/calendar',
            $url,
            'Request URL should handle trailing slash in base URL'
        );
    }

    public function testLocaleRejectsCarriageReturn(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid locale value');
        $this->expectExceptionMessage('header injection');

        $request = new CalendarRequest(apiUrl: self::API_URL);
        $request->locale("en\rMalicious-Header: value");
    }

    public function testLocaleRejectsLineFeed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid locale value');
        $this->expectExceptionMessage('header injection');

        $request = new CalendarRequest(apiUrl: self::API_URL);
        $request->locale("en\nMalicious-Header: value");
    }

    public function testLocaleRejectsCRLF(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid locale value');
        $this->expectExceptionMessage('header injection');

        $request = new CalendarRequest(apiUrl: self::API_URL);
        $request->locale("en\r\nMalicious-Header: value");
    }

    public function testLocaleAcceptsValidValue(): void
    {
        $request = new CalendarRequest(apiUrl: self::API_URL);
        $result  = $request->locale('en-US');

        $this->assertSame($request, $result, 'locale() should return self for chaining');
    }

    public function testYearRejectsTooLowValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Year must be between 1970 and 9999');

        $request = new CalendarRequest(apiUrl: self::API_URL);
        $request->year(1969);
    }

    public function testYearRejectsTooHighValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Year must be between 1970 and 9999');

        $request = new CalendarRequest(apiUrl: self::API_URL);
        $request->year(10000);
    }

    public function testYearAcceptsBoundaryValues(): void
    {
        $request = new CalendarRequest(apiUrl: self::API_URL);

        // Test lower boundary
        $result1 = $request->year(1970);
        $this->assertSame($request, $result1, 'year(1970) should return self for chaining');

        // Test upper boundary
        $result2 = $request->year(9999);
        $this->assertSame($request, $result2, 'year(9999) should return self for chaining');
    }

    public function testHeaderRejectsInvalidName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid header name');

        $request = new CalendarRequest(apiUrl: self::API_URL);
        $request->header('Invalid Header!', 'value');
    }

    public function testHeaderRejectsNameWithSpaces(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid header name');

        $request = new CalendarRequest(apiUrl: self::API_URL);
        $request->header('X Custom Header', 'value');
    }

    public function testHeaderRejectsValueWithCarriageReturn(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid header value');
        $this->expectExceptionMessage('header injection');

        $request = new CalendarRequest(apiUrl: self::API_URL);
        $request->header('X-Custom', "value\rMalicious-Header: injected");
    }

    public function testHeaderRejectsValueWithLineFeed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid header value');
        $this->expectExceptionMessage('header injection');

        $request = new CalendarRequest(apiUrl: self::API_URL);
        $request->header('X-Custom', "value\nMalicious-Header: injected");
    }

    public function testHeaderRejectsValueWithCRLF(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid header value');
        $this->expectExceptionMessage('header injection');

        $request = new CalendarRequest(apiUrl: self::API_URL);
        $request->header('X-Custom', "value\r\nMalicious-Header: injected");
    }

    public function testHeaderAcceptsValidNameAndValue(): void
    {
        $request = new CalendarRequest(apiUrl: self::API_URL);

        // Test valid header names
        $result1 = $request->header('X-Custom-Header', 'value1');
        $this->assertSame($request, $result1, 'header() should return self for chaining');

        $result2 = $request->header('Accept', 'application/xml');
        $this->assertSame($request, $result2, 'header() should return self for chaining');

        $result3 = $request->header('X_Custom_Underscore', 'value3');
        $this->assertSame($request, $result3, 'header() should accept underscores in name');
    }

    public function testAcceptLanguageDelegatesToHeaderAndIsChainable(): void
    {
        $request = new CalendarRequest(apiUrl: self::API_URL);

        // Test that acceptLanguage() returns self for chaining
        $result = $request->acceptLanguage('en-US');
        $this->assertSame($request, $result, 'acceptLanguage() should return self for chaining');

        // Test with multiple chained calls
        $result2 = $request
            ->acceptLanguage('fr-FR')
            ->nation('FR')
            ->year(2025);
        $this->assertSame($request, $result2, 'acceptLanguage() should support method chaining');
    }

    public function testAcceptLanguageRejectsInvalidValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid header value');
        $this->expectExceptionMessage('header injection');

        $request = new CalendarRequest(apiUrl: self::API_URL);
        $request->acceptLanguage("en\r\nMalicious-Header: injected");
    }

    public function testCreateCalendarRequestViaApiClient(): void
    {
        // Initialize ApiClient with configuration
        ApiClient::getInstance([
            'apiUrl' => self::API_URL
        ]);

        // Create CalendarRequest via ApiClient (should use shared config)
        $request = ApiClient::createCalendarRequest();

        // Verify it uses the shared API URL configuration
        $url = $request->nation('US')->year(2025)->getRequestUrl();

        $this->assertEquals(
            self::API_URL . '/calendar/nation/US/2025',
            $url,
            'CalendarRequest created via ApiClient should use shared API URL configuration'
        );

        // Verify it's chainable
        $result = $request->locale('en-US');
        $this->assertSame($request, $result, 'CalendarRequest from ApiClient should support method chaining');
    }

    public function testCreateCalendarRequestWithoutApiClientInitialization(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ApiClient must be initialized before creating CalendarRequest');

        // Don't initialize ApiClient - this should throw
        ApiClient::createCalendarRequest();
    }
}
