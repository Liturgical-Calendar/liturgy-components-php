<?php

namespace LiturgicalCalendar\Components\Tests;

use LiturgicalCalendar\Components\Rite;
use LiturgicalCalendar\Components\RiteSelect;
use PHPUnit\Framework\TestCase;

final class RiteSelectTest extends TestCase
{
    public function testRendersBothRitesInEnumOrder(): void
    {
        $html = ( new RiteSelect(['locale' => 'en']) )->getSelect();

        $this->assertStringContainsString('<option value="roman"', $html);
        $this->assertStringContainsString('<option value="ambrosian"', $html);
        $this->assertLessThan(strpos($html, 'ambrosian'), strpos($html, 'roman'));
    }

    public function testSettersAreChainable(): void
    {
        $select = new RiteSelect(['locale' => 'en']);
        $this->assertSame($select, $select->class('form-select'));
        $this->assertSame($select, $select->id('riteSelect'));
        $this->assertSame($select, $select->name('rite'));
        $this->assertSame($select, $select->label(true));
        $this->assertSame($select, $select->labelText('Rite'));
        $this->assertSame($select, $select->labelClass('form-label'));
        $this->assertSame($select, $select->disabled(true));
    }

    public function testRendersTheConfiguredAttributes(): void
    {
        $html = ( new RiteSelect(['locale' => 'en']) )
            ->class('form-select')
            ->id('riteSelect')
            ->name('rite')
            ->getSelect();

        $this->assertStringContainsString('id="riteSelect"', $html);
        $this->assertStringContainsString('name="rite"', $html);
        $this->assertStringContainsString('class="form-select"', $html);
    }

    public function testRendersALabelWhenAsked(): void
    {
        $html = ( new RiteSelect(['locale' => 'en']) )
            ->id('riteSelect')
            ->label(true)
            ->labelText('Choose a rite')
            ->labelClass('form-label')
            ->getSelect();

        $this->assertStringContainsString('<label for="riteSelect" class="form-label">Choose a rite</label>', $html);
    }

    public function testRendersNoLabelByDefault(): void
    {
        $html = ( new RiteSelect(['locale' => 'en']) )->getSelect();

        $this->assertStringNotContainsString('<label', $html);
    }

    public function testSelectedOptionMarksThatRite(): void
    {
        $html = ( new RiteSelect(['locale' => 'en']) )->selectedOption(Rite::AMBROSIAN)->getSelect();

        $this->assertMatchesRegularExpression('/<option value="ambrosian"[^>]*selected/', $html);
    }

    public function testSelectedOptionAcceptsAString(): void
    {
        $html = ( new RiteSelect(['locale' => 'en']) )->selectedOption('ambrosian')->getSelect();

        $this->assertMatchesRegularExpression('/<option value="ambrosian"[^>]*selected/', $html);
    }

    public function testRejectsAnUnknownRite(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Invalid rite: byzantine.*roman.*ambrosian/');
        ( new RiteSelect(['locale' => 'en']) )->selectedOption('byzantine');
    }

    public function testDisabledRendersTheAttribute(): void
    {
        $html = ( new RiteSelect(['locale' => 'en']) )->disabled(true)->getSelect();

        $this->assertStringContainsString(' disabled', $html);
    }

    public function testToStringRendersTheSelect(): void
    {
        $select = new RiteSelect(['locale' => 'en']);

        $this->assertSame($select->getSelect(), (string) $select);
    }

    public function testEscapesCallerSuppliedLabelText(): void
    {
        $html = ( new RiteSelect(['locale' => 'en']) )
            ->label(true)
            ->labelText('<script>alert(1)</script>')
            ->getSelect();

        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testRejectsAnInvalidLocale(): void
    {
        $this->expectException(\Exception::class);
        new RiteSelect(['locale' => ' invalid-locale ']);
    }
}
