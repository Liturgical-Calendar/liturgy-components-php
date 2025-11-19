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
}
