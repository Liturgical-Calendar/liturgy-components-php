<?php

namespace LiturgicalCalendar\Components\Tests;

use LiturgicalCalendar\Components\ApiOptions\Input;
use LiturgicalCalendar\Components\ApiOptions\Input\Year;
use LiturgicalCalendar\Components\Rite;
use PHPUnit\Framework\TestCase;

final class ApiOptionsYearInputTest extends TestCase
{
    protected function setUp(): void
    {
        Input::resetGlobals();
    }

    protected function tearDown(): void
    {
        Input::resetGlobals();
    }

    public function testFloorDefaultsToTheFirstYearTheApiServes(): void
    {
        $this->assertStringContainsString('min="1970"', ( new Year() )->get());
    }

    public function testMinRaisesTheRenderedFloor(): void
    {
        $html = ( new Year() )->min(1976)->get();

        $this->assertStringContainsString('min="1976"', $html);
        $this->assertStringNotContainsString('min="1970"', $html);
    }

    public function testMinIsChainable(): void
    {
        $this->assertInstanceOf(Year::class, ( new Year() )->min(1976));
    }

    public function testMinRefusesAYearTheApiCannotServe(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Invalid minimum year: 1969.*1970.*9999/');
        ( new Year() )->min(1969);
    }

    public function testMinRefusesAYearAboveTheMaximum(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Invalid minimum year: 10000.*1970.*9999/');
        ( new Year() )->min(10000);
    }

    public function testRiteSetsTheFloorToTheRitesFirstComputableYear(): void
    {
        $this->assertStringContainsString('min="1976"', ( new Year() )->rite(Rite::AMBROSIAN)->get());
        $this->assertStringContainsString('min="1970"', ( new Year() )->rite(Rite::ROMAN)->get());
    }

    public function testRiteAcceptsTheStringValueOfTheRite(): void
    {
        $this->assertStringContainsString('min="1976"', ( new Year() )->rite('ambrosian')->get());
    }

    public function testRiteRejectsAnUnknownRiteNamingTheValidValues(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Invalid rite: byzantine.*roman.*ambrosian/');
        ( new Year() )->rite('byzantine');
    }

    public function testRiteIsChainable(): void
    {
        $this->assertInstanceOf(Year::class, ( new Year() )->rite(Rite::AMBROSIAN));
    }

    public function testTheLastFloorSetWins(): void
    {
        $this->assertStringContainsString('min="1970"', ( new Year() )->min(1980)->rite(Rite::ROMAN)->get());
        $this->assertStringContainsString('min="1980"', ( new Year() )->rite(Rite::ROMAN)->min(1980)->get());
    }

    public function testASelectedYearBelowTheFloorIsClampedUpToIt(): void
    {
        $html = ( new Year() )->rite(Rite::AMBROSIAN)->selectedValue(1970)->get();

        $this->assertStringContainsString('value="1976"', $html);
    }

    public function testASelectedYearAtOrAboveTheFloorIsLeftAlone(): void
    {
        $html = ( new Year() )->rite(Rite::AMBROSIAN)->selectedValue(1976)->get();

        $this->assertStringContainsString('value="1976"', $html);
        $this->assertStringContainsString('value="2026"', ( new Year() )->rite(Rite::AMBROSIAN)->selectedValue(2026)->get());
    }

    public function testANumericStringSelectedYearBelowTheFloorIsAlsoClamped(): void
    {
        $html = ( new Year() )->rite(Rite::AMBROSIAN)->selectedValue('1970')->get();

        $this->assertStringContainsString('value="1976"', $html);
    }

    /**
     * A year is a whole number. Casting these would silently turn a nonsensical
     * value into a plausible one — `'9999.9'` into 9999 — rather than falling
     * back the way every other unusable value does.
     *
     * @param string $selectedValue
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('unusableNumericStrings')]
    public function testAYearThatIsNotAWholeNumberFallsBackToTheCurrentYear(string $selectedValue): void
    {
        $html = ( new Year() )->selectedValue($selectedValue)->get();

        $this->assertStringContainsString('value="' . date('Y') . '"', $html);
    }

    /**
     * @return array<string,string[]>
     */
    public static function unusableNumericStrings(): array
    {
        return [
            'fractional'     => ['9999.9'],
            'fractional too' => ['1976.5'],
            'signed'         => ['+1976'],
            'padded'         => [' 1976'],
            'exponential'    => ['1.976e3']
        ];
    }

    public function testTheDefaultYearIsUnaffectedByTheFloorWhenItIsAboveIt(): void
    {
        $html = ( new Year() )->rite(Rite::AMBROSIAN)->get();

        $this->assertStringContainsString('value="' . date('Y') . '"', $html);
    }
}
