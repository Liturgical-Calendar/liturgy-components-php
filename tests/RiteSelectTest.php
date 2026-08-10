<?php

namespace LiturgicalCalendar\Components\Tests;

use LiturgicalCalendar\Components\Rite;
use LiturgicalCalendar\Components\RiteSelect;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
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
    public function testTranslatesTheOptionsIntoTheConfiguredLocale(): void
    {
        $this->skipWithoutItalianLocale();

        $html = ( new RiteSelect(['locale' => 'it']) )->label(true)->getSelect();

        // Carried into the failure message: this assertion has failed on a CI
        // runner where a standalone bindtextdomain/setlocale/dgettext probe
        // returned Italian correctly, so when it fails the interesting question
        // is what the catalog resolves to inside *this* process, not whether the
        // environment can translate at all.
        $this->assertStringContainsString('>Rito Romano</option>', $html, $this->catalogDiagnostics());
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
     * even when it throws part-way through — that is what the `finally` is for.
     *
     * The callback is driven through reflection rather than by provoking a real
     * failure in getSelect(): every input that reaches the renderer is validated
     * on the way in, so there is no natural way to make it throw, and an earlier
     * version of this test that tried to force one never threw at all and so
     * asserted nothing the success-path test above had not already covered.
     */
    public function testRestoresTheProcessMessagesLocaleWhenRenderingThrows(): void
    {
        $this->skipWithoutItalianLocale();

        $before = setlocale(LC_MESSAGES, '0');
        $select = new RiteSelect(['locale' => 'it']);

        $method = new \ReflectionMethod($select, 'withRiteMessagesLocale');
        $threw  = false;

        try {
            $method->invoke($select, 'it', static function (): string {
                throw new \RuntimeException('rendering blew up');
            });
        } catch (\RuntimeException $e) {
            $threw = true;
            $this->assertSame('rendering blew up', $e->getMessage());
        }

        // Without this the test would pass on a silently swallowed exception,
        // which is the failure mode it exists to rule out.
        $this->assertTrue($threw, 'Expected the callback exception to propagate.');
        $this->assertSame($before, setlocale(LC_MESSAGES, '0'));
    }

    /**
     * What the rite catalog resolves to in this process, for a failure message.
     *
     * Probes directly rather than through a component, so a mismatch says
     * whether the catalog itself failed to resolve or the component failed to
     * apply the locale it was given.
     */
    private function catalogDiagnostics(): string
    {
        $before = setlocale(LC_MESSAGES, '0');
        $set    = setlocale(LC_MESSAGES, 'it_IT.utf8', 'it_IT.UTF-8', 'it_IT', 'it');
        $direct = dgettext('rite', 'Roman Rite');
        if (false !== $before) {
            setlocale(LC_MESSAGES, $before);
        }

        return sprintf(
            'catalog diagnostics: LC_MESSAGES before=%s, setlocale returned=%s, direct dgettext=%s, '
                . 'bound path=%s, catalog file exists=%s',
            var_export($before, true),
            var_export($set, true),
            var_export($direct, true),
            var_export(bindtextdomain('rite', null), true),
            var_export(file_exists(dirname(__DIR__) . '/src/i18n/it/LC_MESSAGES/rite.mo'), true)
        );
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
