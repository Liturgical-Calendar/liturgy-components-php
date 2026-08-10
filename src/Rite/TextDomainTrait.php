<?php

namespace LiturgicalCalendar\Components\Rite;

/**
 * Shared gettext handling for the `rite` domain.
 *
 * Used by both {@see \LiturgicalCalendar\Components\CalendarSelect} — which
 * renders the rite-level calendar name in its empty option — and
 * {@see \LiturgicalCalendar\Components\RiteSelect}, which renders the rite
 * option labels. The catalogs live in `src/i18n` rather than under either
 * component, so neither has to bind a domain owned by the other.
 *
 * @package LiturgicalCalendar\Components\Rite
 * @author John Romano D'Orazio <priest@johnromanodorazio.com>
 */
trait TextDomainTrait
{
    /**
     * Binds the `rite` gettext domain.
     *
     * Failure is a warning rather than an exception, matching ApiOptions: an
     * unbound domain means untranslated English, not a broken component.
     *
     * @return void
     */
    private function bindRiteTextDomain(): void
    {
        $expected = dirname(__DIR__) . '/i18n';
        $bound    = bindtextdomain('rite', $expected);
        if (false === $bound || $bound !== $expected) {
            trigger_error(
                "Failed to bind text domain. Expected path: {$expected}, got: " . var_export($bound, true) .
                '. Translations may not be available.',
                E_USER_WARNING
            );
        }
    }

    /**
     * Runs `$render` with `LC_MESSAGES` set to `$locale`, then restores it.
     *
     * Without this the components accepted a locale and then translated in
     * whatever locale the process happened to be in, so `new RiteSelect( [
     * 'locale' => 'it' ] )` rendered English. gettext reads the category, not
     * the argument.
     *
     * Only `LC_MESSAGES` is touched, not `LC_ALL` as WebCalendar does: message
     * lookup is all that is wanted here, and widening it would also change
     * number and date formatting for the caller's whole process.
     *
     * The restore runs in a `finally`, so an exception thrown while rendering
     * cannot leave the caller's process in a locale it never chose.
     *
     * @param string          $locale The canonicalized locale to translate in.
     * @param callable(): string $render Produces the markup; called once.
     *
     * @return string Whatever `$render` returned.
     */
    private function withRiteMessagesLocale(string $locale, callable $render): string
    {
        $previous = setlocale(LC_MESSAGES, '0');
        setlocale(LC_MESSAGES, self::riteMessagesLocaleCandidates($locale));

        try {
            return $render();
        } finally {
            if (false !== $previous) {
                setlocale(LC_MESSAGES, $previous);
            }
        }
    }

    /**
     * Locale strings to try, most specific first.
     *
     * A system carries `it_IT.utf8` but rarely bare `it`, so the language-only
     * forms are the fallback rather than the first choice. Mirrors the ladder
     * ApiOptions and WebCalendar already build.
     *
     * @param string $locale The canonicalized locale, e.g. `it_IT`.
     *
     * @return string[] Candidates for setlocale, in order of preference.
     */
    private static function riteMessagesLocaleCandidates(string $locale): array
    {
        $language = \Locale::getPrimaryLanguage($locale) ?? 'en';
        $region   = \Locale::getRegion($locale);

        $candidates = [
            $locale . '.utf8',
            $locale . '.UTF-8',
            $locale
        ];

        if (null !== $region && '' !== $region) {
            $candidates[] = $language . '_' . $region . '.utf8';
            $candidates[] = $language . '_' . $region . '.UTF-8';
            $candidates[] = $language . '_' . $region;
        }

        $candidates[] = $language . '_' . strtoupper($language) . '.utf8';
        $candidates[] = $language . '_' . strtoupper($language) . '.UTF-8';
        $candidates[] = $language . '_' . strtoupper($language);
        $candidates[] = $language . '.utf8';
        $candidates[] = $language . '.UTF-8';
        $candidates[] = $language;

        return array_values(array_unique($candidates));
    }
}
