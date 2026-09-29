<?php

namespace LiturgicalCalendar\Components\Tests\Models\Index;

use LiturgicalCalendar\Components\Models\Index\WiderRegion;
use PHPUnit\Framework\TestCase;

/**
 * `national_calendars` are the nations that have a calendar and declare the
 * region; `roster` is every nation eligible to join it (LiturgicalCalendarAPI#1005).
 */
final class WiderRegionTest extends TestCase
{
    public function testReadsNationalCalendarsAndRoster(): void
    {
        $region = WiderRegion::fromArray([
            'name'               => 'Europe',
            'locales'            => ['it_IT', 'nl_NL'],
            'api_path'           => 'http://localhost:8000/data/widerregion/Europe?locale={locale}',
            'national_calendars' => ['HR', 'IT', 'NL'],
            'roster'             => ['AT', 'BE', 'HR', 'IT', 'NL'],
        ]);

        $this->assertSame(['HR', 'IT', 'NL'], $region->nationalCalendars);
        $this->assertSame(['AT', 'BE', 'HR', 'IT', 'NL'], $region->roster);
        $this->assertSame(
            [
                'name'               => 'Europe',
                'locales'            => ['it_IT', 'nl_NL'],
                'api_path'           => 'http://localhost:8000/data/widerregion/Europe?locale={locale}',
                'national_calendars' => ['HR', 'IT', 'NL'],
                'roster'             => ['AT', 'BE', 'HR', 'IT', 'NL'],
            ],
            $region->toArray()
        );
    }

    public function testDefaultsToEmptyListsForAnOlderApi(): void
    {
        $region = WiderRegion::fromArray([
            'name'     => 'Americas',
            'locales'  => ['en_US'],
            'api_path' => 'http://localhost:8000/data/widerregion/Americas?locale={locale}',
        ]);

        $this->assertSame([], $region->nationalCalendars);
        $this->assertSame([], $region->roster);
    }

    public function testRejectsANonStringCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WiderRegion::fromArray([
            'name'     => 'Asia',
            'locales'  => [],
            'api_path' => '',
            'roster'   => ['CN', null],
        ]);
    }
}
