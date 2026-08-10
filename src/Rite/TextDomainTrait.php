<?php

namespace LiturgicalCalendar\Components\Rite;

use LiturgicalCalendar\Components\Locale\ScopedLocale;

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
     * Runs `$render` with `LC_MESSAGES` and `LANGUAGE` set to `$locale`, then restores both.
     *
     * Without this the components accepted a locale and then translated in
     * whatever locale the process happened to be in, so `new RiteSelect( [
     * 'locale' => 'it' ] )` rendered English. gettext reads the category, not
     * the argument — and it reads `LANGUAGE` above the category, which is why
     * {@see ScopedLocale} moves the two together.
     *
     * Only `LC_MESSAGES` is touched, not `LC_ALL` as WebCalendar does: message
     * lookup is all that is wanted here, and widening it would also change
     * number and date formatting for the caller's whole process.
     *
     * @param string          $locale The canonicalized locale to translate in.
     * @param callable(): string $render Produces the markup; called once.
     *
     * @return string Whatever `$render` returned.
     */
    private function withRiteMessagesLocale(string $locale, callable $render): string
    {
        /** @var string */
        return ScopedLocale::within(LC_MESSAGES, $locale, $render);
    }
}
