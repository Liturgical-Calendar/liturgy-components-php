<?php

namespace LiturgicalCalendar\Components\Tests;

use LiturgicalCalendar\Components\CalendarSelect;
use LiturgicalCalendar\Components\Rite;
use PHPUnit\Framework\TestCase;

final class CalendarSelectRiteTest extends TestCase
{
    public function testDefaultsToTheRomanRite(): void
    {
        $select = new CalendarSelect(['locale' => 'en']);
        $this->assertSame(Rite::ROMAN, $select->getRite());
    }

    public function testAcceptsARiteEnumAndIsChainable(): void
    {
        $select = new CalendarSelect(['locale' => 'en']);
        $this->assertSame($select, $select->rite(Rite::AMBROSIAN));
        $this->assertSame(Rite::AMBROSIAN, $select->getRite());
    }

    public function testAcceptsARiteAsAString(): void
    {
        $select = ( new CalendarSelect(['locale' => 'en']) )->rite('ambrosian');
        $this->assertSame(Rite::AMBROSIAN, $select->getRite());
    }

    public function testAcceptsARiteAsAConstructorOption(): void
    {
        $select = new CalendarSelect(['locale' => 'en', 'rite' => 'ambrosian']);
        $this->assertSame(Rite::AMBROSIAN, $select->getRite());
    }

    public function testRejectsAnUnknownRiteNamingTheValidValues(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Invalid rite: byzantine.*roman.*ambrosian/');
        ( new CalendarSelect(['locale' => 'en']) )->rite('byzantine');
    }

    /**
     * The regression. Before the rite partition this threw:
     *
     *   TypeError: hasNationalCalendarWithDioceses(): Argument #1 ($item)
     *   must be of type NationalCalendar, null given
     *
     * lugano_ch sits in nation CH, which has no national calendar, so the
     * nation pass took [0] of an empty filter result. It is the only such
     * diocese in served metadata, and this suite reads the live API.
     */
    public function testRendersWithADioceseWhoseNationHasNoNationalCalendar(): void
    {
        $select = new CalendarSelect(['locale' => 'en']);
        $html   = $select->getSelect();

        $this->assertStringContainsString('<select', $html);
    }

    public function testAmbrosianDiocesesAreAbsentUnderTheRomanRite(): void
    {
        $html = ( new CalendarSelect(['locale' => 'en']) )->getSelect();

        $this->assertStringNotContainsString('lugano_ch', $html);
        $this->assertStringNotContainsString('milano_it', $html);
    }

    public function testAmbrosianDiocesesArePresentUnderTheAmbrosianRite(): void
    {
        $html = ( new CalendarSelect(['locale' => 'en', 'rite' => 'ambrosian']) )->getSelect();

        $this->assertStringContainsString('lugano_ch', $html);
        $this->assertStringContainsString('milano_it', $html);
    }

    /**
     * `---` said nothing about what selecting it meant. Choosing neither a
     * nation nor a diocese is not choosing nothing: it is choosing the
     * rite-level calendar, and the option now names it.
     */
    public function testEmptyOptionNamesTheRiteLevelCalendar(): void
    {
        $html = ( new CalendarSelect(['locale' => 'en', 'allowNull' => true]) )->getSelect();

        $this->assertStringContainsString('>General Roman Calendar</option>', $html);
        $this->assertStringNotContainsString('>---</option>', $html);
    }

    public function testEmptyOptionNamesTheAmbrosianCalendarUnderThatRite(): void
    {
        $html = ( new CalendarSelect(['locale' => 'en', 'rite' => 'ambrosian', 'allowNull' => true]) )->getSelect();

        $this->assertStringContainsString('>Ambrosian Calendar</option>', $html);
        $this->assertStringNotContainsString('>General Roman Calendar</option>', $html);
    }

    /**
     * Nothing pinned these values before, which is how the two libraries drifted
     * apart unnoticed. The PHP library emits the attribute in two places and
     * reads it back nowhere, so this is rendered output only.
     */
    public function testEmitsTheSameCalendarTypeValuesAsTheJsLibrary(): void
    {
        $html = ( new CalendarSelect(['locale' => 'en']) )->getSelect();

        $this->assertStringContainsString('data-calendartype="national"', $html);
        $this->assertStringContainsString('data-calendartype="diocesan"', $html);
        $this->assertStringNotContainsString('nationalcalendar', $html);
        $this->assertStringNotContainsString('diocesancalendar', $html);
    }

    public function testNoNationalTierUnderARiteThatHasNone(): void
    {
        $html = ( new CalendarSelect(['locale' => 'en', 'rite' => 'ambrosian']) )->getSelect();

        // Vatican is a national calendar with no dioceses of its own, so it is
        // the one that would survive a half-applied partition.
        $this->assertStringNotContainsString('value="VA"', $html);
    }
}
