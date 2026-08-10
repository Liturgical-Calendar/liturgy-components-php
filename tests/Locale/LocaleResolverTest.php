<?php

namespace LiturgicalCalendar\Components\Tests\Locale;

use LiturgicalCalendar\Components\Locale\LocaleResolver;
use PHPUnit\Framework\TestCase;

/**
 * Region resolution for a region-less request locale.
 *
 * The ladders these replace guessed the region by uppercasing the language, so
 * `it` produced `it_IT` and `fr` produced `fr_FR` — correct only by coincidence.
 * `en` produced `en_EN`, which is not a locale on any system, so `setlocale()`
 * returned false and the caller silently kept whatever locale the process was
 * already in. CLDR likely subtags give the real region: en => US, pt => BR.
 */
class LocaleResolverTest extends TestCase
{
    public function testResolvesTheLikelyRegionForARegionLessLanguage(): void
    {
        $this->assertSame('US', LocaleResolver::likelyRegion('en'));
        $this->assertSame('IT', LocaleResolver::likelyRegion('it'));
        $this->assertSame('VA', LocaleResolver::likelyRegion('la'));
    }

    public function testTheLikelyRegionIsNotTheUppercasedLanguage(): void
    {
        $this->assertSame(
            'BR',
            LocaleResolver::likelyRegion('pt'),
            'Portuguese maximizes to Brazil, which the lang_LANG guess could never produce'
        );
    }

    public function testReturnsAnEmptyRegionForAnUnknownLanguage(): void
    {
        $this->assertSame('', LocaleResolver::likelyRegion('zzz'));
    }

    public function testCandidatesForEnglishIncludeAnInstallableLocale(): void
    {
        $candidates = LocaleResolver::candidates('en');

        $this->assertContains('en_US.utf8', $candidates);
        $this->assertNotContains('en_EN.utf8', $candidates, 'en_EN is not a locale on any system');
        $this->assertNotContains('en_EN', $candidates);
    }

    public function testCandidatesAreOrderedMostSpecificFirst(): void
    {
        $candidates = LocaleResolver::candidates('en');

        $this->assertSame(
            ['en_US.utf8', 'en_US.UTF-8', 'en_US', 'en.utf8', 'en.UTF-8', 'en'],
            $candidates
        );
    }

    public function testAnExplicitRegionWinsOverTheLikelyOne(): void
    {
        $candidates = LocaleResolver::candidates('pt_PT');

        $this->assertSame('pt_PT.utf8', $candidates[0], 'An explicit region must not be overridden by CLDR');
        $this->assertNotContains('pt_BR.utf8', $candidates);
    }

    public function testCandidatesAreFreeOfDuplicates(): void
    {
        $candidates = LocaleResolver::candidates('it_IT');

        $this->assertSame(array_values(array_unique($candidates)), $candidates);
    }

    public function testCandidatesForEnglishActuallySetTheLocale(): void
    {
        $previous = setlocale(LC_MESSAGES, '0');

        // Guard on whether the machine has *any* English locale, probed directly.
        // Guarding on whether our own ladder works would make the test vacuous:
        // it would skip in exactly the case it exists to catch.
        $installed = setlocale(LC_MESSAGES, 'en_US.utf8', 'en_US.UTF-8', 'en_US', 'en_GB.utf8', 'en_GB');
        $applied   = false === $installed ? null : setlocale(LC_MESSAGES, LocaleResolver::candidates('en'));

        if (false !== $previous) {
            setlocale(LC_MESSAGES, $previous);
        }
        if (false === $installed) {
            $this->markTestSkipped('No English locale installed; the ladder has nothing to resolve to.');
        }

        $this->assertNotFalse(
            $applied,
            'The whole point: a language-only "en" must resolve to an installed system locale'
        );
    }

    public function testAnUnreadableDataFileDegradesInsteadOfThrowing(): void
    {
        $caught = null;
        set_error_handler(static function (int $errno, string $message) use (&$caught): bool {
            $caught = $message;
            return true;
        }, E_USER_WARNING);

        try {
            $load   = new \ReflectionMethod(LocaleResolver::class, 'loadLikelyRegions');
            $result = $load->invoke(null, __DIR__ . '/no-such-file.json');
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $result, 'A missing data file must not throw out of a locale lookup');
        $this->assertIsString($caught);
        $this->assertStringContainsString('likely region', $caught);
    }

    public function testCorruptDataFileDegradesInsteadOfThrowing(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'likely') . '.json';
        file_put_contents($path, '{ this is not json');

        $caught = null;
        set_error_handler(static function (int $errno, string $message) use (&$caught): bool {
            $caught = $message;
            return true;
        }, E_USER_WARNING);

        try {
            $load   = new \ReflectionMethod(LocaleResolver::class, 'loadLikelyRegions');
            $result = $load->invoke(null, $path);
        } finally {
            restore_error_handler();
            unlink($path);
        }

        $this->assertSame([], $result, 'Malformed JSON must not surface as an uncaught JsonException');
        $this->assertIsString($caught);
    }
}
