<?php

namespace LiturgicalCalendar\Components\Tests;

use LiturgicalCalendar\Components\ApiClient;
use LiturgicalCalendar\Components\CalendarRequest;
use LiturgicalCalendar\Components\Rite;
use PHPUnit\Framework\TestCase;

/**
 * The rite segment in a CalendarRequest URL.
 *
 * The API routes a rite as a bare segment named by the rite itself, between
 * `calendar` and any nation or diocese pair: `/calendar/ambrosian/diocese/lugano_ch/2026`.
 * There is no `/calendar/rite/{rite}` spelling, and `/calendar/diocese/lugano_ch`
 * without the prefix is a 400 — which is what a rite-aware CalendarSelect used
 * to hand a rite-blind CalendarRequest.
 *
 * The segment appears whenever a rite is set explicitly, `roman` included. A
 * request that never mentions a rite keeps the historic prefix-free URL.
 */
class CalendarRequestRiteTest extends TestCase
{
    private const API_URL = 'https://litcal.johnromanodorazio.com/api/dev';

    protected function setUp(): void
    {
        ApiClient::resetForTesting();
        ApiClient::getInstance([
            'apiUrl' => self::API_URL
        ]);
    }

    public function testOmitsTheRiteSegmentWhenNoRiteIsSet(): void
    {
        $url = ( new CalendarRequest() )->nation('IT')->year(2026)->getRequestUrl();

        $this->assertEquals(
            self::API_URL . '/calendar/nation/IT/2026',
            $url,
            'A request that never mentions a rite should keep the prefix-free URL'
        );
    }

    public function testAddsTheRomanSegmentWhenTheRomanRiteIsSetExplicitly(): void
    {
        $url = ( new CalendarRequest() )->rite(Rite::ROMAN)->getRequestUrl();

        $this->assertEquals(
            self::API_URL . '/calendar/roman',
            $url,
            'An explicit Roman rite should be named in the path, not inferred away'
        );
    }

    public function testAddsTheRomanSegmentBeforeNationAndYear(): void
    {
        $url = ( new CalendarRequest() )->rite(Rite::ROMAN)->nation('IT')->year(2026)->getRequestUrl();

        $this->assertEquals(
            self::API_URL . '/calendar/roman/nation/IT/2026',
            $url,
            'The rite segment belongs between calendar and the nation pair'
        );
    }

    public function testAddsTheAmbrosianSegment(): void
    {
        $url = ( new CalendarRequest() )->rite(Rite::AMBROSIAN)->year(2026)->getRequestUrl();

        $this->assertEquals(
            self::API_URL . '/calendar/ambrosian/2026',
            $url,
            'The Ambrosian rite-level calendar is /calendar/ambrosian/{year}'
        );
    }

    public function testAddsTheAmbrosianSegmentBeforeDioceseAndYear(): void
    {
        $url = ( new CalendarRequest() )->rite(Rite::AMBROSIAN)->diocese('lugano_ch')->year(2026)->getRequestUrl();

        $this->assertEquals(
            self::API_URL . '/calendar/ambrosian/diocese/lugano_ch/2026',
            $url,
            'An Ambrosian diocese is unroutable without its rite prefix'
        );
    }

    public function testAcceptsARiteAsAString(): void
    {
        $url = ( new CalendarRequest() )->rite('ambrosian')->getRequestUrl();

        $this->assertEquals(
            self::API_URL . '/calendar/ambrosian',
            $url,
            'A string rite should resolve through Rite::tryFrom(), as CalendarSelect does'
        );
    }

    public function testRejectsAnUnknownRite(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Invalid rite: byzantine.*roman.*ambrosian/');
        ( new CalendarRequest() )->rite('byzantine');
    }

    public function testRiteIsChainable(): void
    {
        $request = new CalendarRequest();

        $this->assertSame(
            $request,
            $request->rite(Rite::AMBROSIAN),
            'rite() should return $this so it composes with the other setters'
        );
    }

    public function testGetRiteReturnsNullUntilARiteIsSet(): void
    {
        $request = new CalendarRequest();

        $this->assertNull(
            $request->getRite(),
            'An unset rite is distinguishable from an explicit Roman rite'
        );

        $this->assertSame(
            Rite::ROMAN,
            $request->rite(Rite::ROMAN)->getRite(),
            'An explicit Roman rite reads back as Rite::ROMAN'
        );
    }

    public function testEncodesTheRiteSegment(): void
    {
        $url = ( new CalendarRequest() )->rite(Rite::AMBROSIAN)->diocese('test diocese')->getRequestUrl();

        $this->assertEquals(
            self::API_URL . '/calendar/ambrosian/diocese/test%20diocese',
            $url,
            'The rite segment should not disturb the encoding of the segments after it'
        );
    }
}
