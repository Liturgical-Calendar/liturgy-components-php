<?php

namespace LiturgicalCalendar\Components\Tests\Locale;

use LiturgicalCalendar\Components\Locale\ScopedLocale;
use PHPUnit\Framework\TestCase;

/**
 * Applying and restoring the process-global locale state, `LANGUAGE` included.
 *
 * glibc's gettext reads the `LANGUAGE` environment variable *above* `LC_MESSAGES`,
 * so setting the category alone is not enough: a host that exports `LANGUAGE`
 * silently wins over every `setlocale()` the library makes. Composer exports
 * `LANGUAGE=C`, which is why the translated-output tests skipped under
 * `composer test` and CI while passing under a bare `vendor/bin/phpunit`.
 *
 * Being a library, this must also put back exactly what it found — locale and
 * `LANGUAGE` alike, including the case where `LANGUAGE` was never set.
 */
class ScopedLocaleTest extends TestCase
{
    private const CATALOG = __DIR__ . '/../../src/i18n';

    protected function setUp(): void
    {
        bindtextdomain('rite', self::CATALOG);
    }

    private function skipWithoutItalianLocale(): void
    {
        $previous = setlocale(LC_MESSAGES, '0');
        $italian  = setlocale(LC_MESSAGES, 'it_IT.utf8', 'it_IT.UTF-8', 'it_IT', 'it');
        if (false !== $previous) {
            setlocale(LC_MESSAGES, $previous);
        }
        if (false === $italian) {
            $this->markTestSkipped('No Italian locale installed.');
        }
    }

    public function testTranslatesEvenWhenTheHostExportsAHostileLanguage(): void
    {
        $this->skipWithoutItalianLocale();
        putenv('LANGUAGE=C'); // exactly what Composer does to the tests it runs

        $translated = ScopedLocale::within(LC_MESSAGES, 'it', fn(): string => dgettext('rite', 'Roman Rite'));

        putenv('LANGUAGE');
        $this->assertSame(
            'Rito Romano',
            $translated,
            'LANGUAGE outranks LC_MESSAGES, so it has to be set too or nothing translates'
        );
    }

    public function testRestoresAPreviouslySetLanguage(): void
    {
        putenv('LANGUAGE=C');

        ScopedLocale::within(LC_MESSAGES, 'it', fn(): string => '');

        $restored = getenv('LANGUAGE');
        putenv('LANGUAGE');
        $this->assertSame('C', $restored, "The host's LANGUAGE must survive the call");
    }

    public function testRestoresAnUnsetLanguageToUnset(): void
    {
        putenv('LANGUAGE');

        ScopedLocale::within(LC_MESSAGES, 'it', fn(): string => '');

        $this->assertFalse(
            getenv('LANGUAGE'),
            'A LANGUAGE the host never set must not exist afterwards'
        );
    }

    public function testRestoresTheLocaleCategory(): void
    {
        $before = setlocale(LC_MESSAGES, '0');

        ScopedLocale::within(LC_MESSAGES, 'it', fn(): string => '');

        $this->assertSame($before, setlocale(LC_MESSAGES, '0'));
    }

    public function testRestoresEvenWhenTheCallableThrows(): void
    {
        $before = setlocale(LC_MESSAGES, '0');
        putenv('LANGUAGE=C');

        try {
            ScopedLocale::within(LC_MESSAGES, 'it', function (): string {
                throw new \RuntimeException('rendering blew up');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $restoredLanguage = getenv('LANGUAGE');
        putenv('LANGUAGE');
        $this->assertSame($before, setlocale(LC_MESSAGES, '0'), 'Locale must be restored on the failure path');
        $this->assertSame('C', $restoredLanguage, 'LANGUAGE must be restored on the failure path');
    }

    public function testAppliesLanguageWhileInsideTheCallable(): void
    {
        $this->skipWithoutItalianLocale();

        $inside = ScopedLocale::within(LC_MESSAGES, 'it', fn(): string => (string) getenv('LANGUAGE'));

        $this->assertStringContainsString('it', $inside, 'LANGUAGE should name the requested language while rendering');
    }

    public function testApplyAndRestoreWorkAsASeparatePair(): void
    {
        $before = setlocale(LC_MESSAGES, '0');
        putenv('LANGUAGE');

        $scope = ScopedLocale::apply(LC_MESSAGES, 'it');
        $this->assertNotFalse(getenv('LANGUAGE'), 'LANGUAGE is set while the scope is open');
        $scope->restore();

        $this->assertSame($before, setlocale(LC_MESSAGES, '0'));
        $this->assertFalse(getenv('LANGUAGE'));
    }

    public function testPinLanguagePinsWithoutRestoring(): void
    {
        $this->skipWithoutItalianLocale();
        putenv('LANGUAGE=C');
        $previous = setlocale(LC_MESSAGES, '0');

        // The set-and-leave path ApiOptions needs: it translates at render time,
        // long after the locale was configured, so nothing may be put back.
        $applied = setlocale(LC_MESSAGES, \LiturgicalCalendar\Components\Locale\LocaleResolver::candidates('it'));
        ScopedLocale::pinLanguage('it', $applied);
        $translated = dgettext('rite', 'Roman Rite');

        if (false !== $previous) {
            setlocale(LC_MESSAGES, $previous);
        }
        putenv('LANGUAGE');

        $this->assertSame('Rito Romano', $translated, 'pinLanguage must override a hostile inherited LANGUAGE');
    }

    public function testPinLanguageFallsBackToTheRequestLocaleWhenSetlocaleFailed(): void
    {
        putenv('LANGUAGE');

        ScopedLocale::pinLanguage('it', false);
        $pinned = getenv('LANGUAGE');
        putenv('LANGUAGE');

        $this->assertIsString($pinned);
        $this->assertStringContainsString('it', $pinned, 'A failed setlocale still leaves the language known');
    }

    public function testDoesNotWidenBeyondTheRequestedCategory(): void
    {
        $this->skipWithoutItalianLocale();
        $numericBefore = setlocale(LC_NUMERIC, '0');

        ScopedLocale::within(LC_MESSAGES, 'it', fn(): string => '');

        $this->assertSame(
            $numericBefore,
            setlocale(LC_NUMERIC, '0'),
            'LC_MESSAGES only: widening would change the host\'s number formatting'
        );
    }
}
