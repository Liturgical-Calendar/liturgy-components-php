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

    /**
     * The component accepted a locale and then translated in whatever locale the
     * process happened to be in, so this rendered English. gettext reads
     * LC_MESSAGES, not the argument.
     */
    public function testTranslatesTheOptionsIntoTheConfiguredLocale(): void
    {
        $this->skipWithoutItalianLocale();

        $html = ( new RiteSelect(['locale' => 'it']) )->label(true)->getSelect();

        $this->assertStringContainsString('>Rito Romano</option>', $html);
        $this->assertStringContainsString('>Rito Ambrosiano</option>', $html);
        $this->assertStringContainsString('>Seleziona un rito</label>', $html);
    }

    public function testRestoresTheProcessMessagesLocaleAfterRendering(): void
    {
        $this->skipWithoutItalianLocale();

        $before = setlocale(LC_MESSAGES, '0');
        ( new RiteSelect(['locale' => 'it']) )->getSelect();
        $after = setlocale(LC_MESSAGES, '0');

        $this->assertSame($before, $after);
    }

    /**
     * Rendering must not strand the caller's process in a locale it never chose,
     * even when it throws part-way through.
     */
    public function testRestoresTheProcessMessagesLocaleWhenRenderingThrows(): void
    {
        $this->skipWithoutItalianLocale();

        $before = setlocale(LC_MESSAGES, '0');
        $select = new RiteSelect(['locale' => 'it']);

        try {
            // A label that is not a string reaches htmlspecialchars() and throws
            // from inside the locale-scoped render.
            $select->label(true)->labelText('ok');
            $reflection = new \ReflectionProperty($select, 'labelStr');
            $reflection->setValue($select, null);
            $select->getSelect();
        } catch (\Throwable) {
            // Whether or not it threw, the locale must be back.
        }

        $this->assertSame($before, setlocale(LC_MESSAGES, '0'));
    }

    /**
     * Skips when the system carries no Italian locale: gettext would fall back
     * to the msgid and the assertion would be about the environment rather than
     * about the component.
     */
    private function skipWithoutItalianLocale(): void
    {
        $current = setlocale(LC_MESSAGES, '0');
        $italian = setlocale(LC_MESSAGES, 'it_IT.utf8', 'it_IT.UTF-8', 'it_IT', 'it');
        if (false !== $current) {
            setlocale(LC_MESSAGES, $current);
        }
        if (false === $italian) {
            $this->markTestSkipped('No Italian locale installed; cannot assert translated output.');
        }
    }
}
