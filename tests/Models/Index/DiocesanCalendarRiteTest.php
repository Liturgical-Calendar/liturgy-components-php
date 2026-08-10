<?php

namespace LiturgicalCalendar\Components\Tests\Models\Index;

use LiturgicalCalendar\Components\Models\Index\DiocesanCalendar;
use PHPUnit\Framework\TestCase;

final class DiocesanCalendarRiteTest extends TestCase
{
    public function testParsesRiteFromMetadata(): void
    {
        $calendar = DiocesanCalendar::fromArray([
            'calendar_id' => 'lugano_ch',
            'diocese'     => 'Lugano',
            'nation'      => 'CH',
            'locales'     => ['it_CH'],
            'timezone'    => 'Europe/Zurich',
            'rite'        => 'ambrosian',
        ]);

        $this->assertSame('ambrosian', $calendar->rite);
    }

    public function testDefaultsToRomanWhenRiteAbsent(): void
    {
        $calendar = DiocesanCalendar::fromArray([
            'calendar_id' => 'romamo_it',
            'diocese'     => 'Roma',
            'nation'      => 'IT',
            'locales'     => ['it_IT'],
            'timezone'    => 'Europe/Rome',
        ]);

        $this->assertSame('roman', $calendar->rite);
    }

    /**
     * Distinct from the absent case: getNullableString() returns null for an
     * explicit null as well as a missing key, and both must land on Roman.
     */
    public function testDefaultsToRomanWhenRiteIsExplicitlyNull(): void
    {
        $calendar = DiocesanCalendar::fromArray([
            'calendar_id' => 'romamo_it',
            'diocese'     => 'Roma',
            'nation'      => 'IT',
            'locales'     => ['it_IT'],
            'timezone'    => 'Europe/Rome',
            'rite'        => null,
        ]);

        $this->assertSame('roman', $calendar->rite);
    }
}
