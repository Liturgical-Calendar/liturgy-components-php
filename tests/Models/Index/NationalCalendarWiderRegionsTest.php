<?php

namespace LiturgicalCalendar\Components\Tests\Models\Index;

use LiturgicalCalendar\Components\Models\Index\NationalCalendar;
use PHPUnit\Framework\TestCase;

/**
 * A national calendar may declare more than one wider region
 * (LiturgicalCalendarAPI#1005). The API sends `wider_regions` on every
 * national calendar, and the deprecated single `wider_region` only when
 * exactly one region is declared; an older API sends only `wider_region`.
 */
final class NationalCalendarWiderRegionsTest extends TestCase
{
    /**
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private static function data(array $extra): array
    {
        return [
            'calendar_id' => 'DK',
            'locales'     => ['da_DK'],
            'missals'     => ['EDITIO_TYPICA_2002'],
            'settings'    => [
                'epiphany'               => 'JAN6',
                'ascension'              => 'THURSDAY',
                'corpus_christi'         => 'SUNDAY',
                'eternal_high_priest'    => false,
                'holydays_of_obligation' => [],
            ],
        ] + $extra;
    }

    public function testReadsSeveralWiderRegionsInDeclaredOrder(): void
    {
        $calendar = NationalCalendar::fromArray(self::data(['wider_regions' => ['Europe', 'Nordic']]));

        $this->assertSame(['Europe', 'Nordic'], $calendar->widerRegions);
        $this->assertNull($calendar->widerRegion, 'the deprecated single region is unset when several are declared');
    }

    public function testSingleRegionPopulatesTheDeprecatedField(): void
    {
        $calendar = NationalCalendar::fromArray(self::data([
            'wider_regions' => ['Europe'],
            'wider_region'  => 'Europe',
        ]));

        $this->assertSame(['Europe'], $calendar->widerRegions);
        $this->assertSame('Europe', $calendar->widerRegion);
    }

    public function testNoRegionIsAnEmptyList(): void
    {
        $calendar = NationalCalendar::fromArray(self::data(['wider_regions' => []]));

        $this->assertSame([], $calendar->widerRegions);
        $this->assertNull($calendar->widerRegion);
    }

    public function testFallsBackToTheLegacySingleRegion(): void
    {
        $calendar = NationalCalendar::fromArray(self::data(['wider_region' => 'Americas']));

        $this->assertSame(['Americas'], $calendar->widerRegions);
        $this->assertSame('Americas', $calendar->widerRegion);
    }

    public function testNeitherFieldIsAnEmptyList(): void
    {
        $calendar = NationalCalendar::fromArray(self::data([]));

        $this->assertSame([], $calendar->widerRegions);
        $this->assertNull($calendar->widerRegion);
    }

    public function testRejectsANonStringRegionName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        NationalCalendar::fromArray(self::data(['wider_regions' => ['Europe', 7]]));
    }

    public function testRejectsALegacyRegionThatContradictsTheList(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        NationalCalendar::fromArray(self::data([
            'wider_regions' => ['Europe', 'Nordic'],
            'wider_region'  => 'Europe',
        ]));
    }

    public function testToArrayMirrorsTheApiShape(): void
    {
        $several = NationalCalendar::fromArray(self::data(['wider_regions' => ['Europe', 'Nordic']]))->toArray();
        $this->assertSame(['Europe', 'Nordic'], $several['wider_regions']);
        $this->assertArrayNotHasKey('wider_region', $several);

        $one = NationalCalendar::fromArray(self::data(['wider_region' => 'Europe']))->toArray();
        $this->assertSame(['Europe'], $one['wider_regions']);
        $this->assertSame('Europe', $one['wider_region']);

        $none = NationalCalendar::fromArray(self::data([]))->toArray();
        $this->assertSame([], $none['wider_regions']);
        $this->assertArrayNotHasKey('wider_region', $none);
    }
}
