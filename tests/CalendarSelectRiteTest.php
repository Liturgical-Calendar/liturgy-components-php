<?php

namespace LiturgicalCalendar\Components\Tests;

use LiturgicalCalendar\Components\CalendarSelect;
use LiturgicalCalendar\Components\Rite;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
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

    /**
     * Runs in a separate process deliberately.
     *
     * glibc will not always re-resolve a gettext domain once it has been looked
     * up under another locale in the same process, and a suite that renders in
     * English before it renders in Italian hits exactly that: on a CI runner a
     * direct dgettext returned 'Roman Rite' here while the identical probe in a
     * fresh process on the same machine returned 'Rito Romano'. Locally the
     * switch happens to work, so the behaviour is glibc-version dependent.
     *
     * This is a harness artefact rather than a product defect — a real request
     * renders in one locale in one process, which is what this now reproduces.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testEmptyOptionIsTranslatedIntoTheConfiguredLocale(): void
    {
        // Bind first: the probe may run before any component has, and an
        // unbound domain returns the msgid for reasons that say nothing
        // about whether the catalog resolves.
        bindtextdomain('rite', dirname(__DIR__) . '/src/i18n');
        $before  = setlocale(LC_MESSAGES, '0');
        $italian = setlocale(LC_MESSAGES, 'it_IT.utf8', 'it_IT.UTF-8', 'it_IT', 'it');
        $direct  = false === $italian ? null : dgettext('rite', 'Roman Rite');
        if (false !== $before) {
            setlocale(LC_MESSAGES, $before);
        }
        if (false === $italian) {
            $this->markTestSkipped('No Italian locale installed; cannot assert translated output.');
        }
        // See RiteSelectTest::skipWithoutWorkingItalianCatalog() for why a
        // process can hold the locale and still fail to resolve the catalog.
        if ('Rito Romano' !== $direct) {
            $this->markTestSkipped('gettext cannot resolve the rite catalog in this process.');
        }

        $html = ( new CalendarSelect(['locale' => 'it', 'allowNull' => true]) )->getSelect();

        $this->assertStringContainsString('>Calendario Romano Generale</option>', $html);
    }

    public function testNoNationalTierUnderARiteThatHasNone(): void
    {
        $html = ( new CalendarSelect(['locale' => 'en', 'rite' => 'ambrosian']) )->getSelect();

        // Vatican is a national calendar with no dioceses of its own, so it is
        // the one that would survive a half-applied partition.
        $this->assertStringNotContainsString('value="VA"', $html);
    }
}
