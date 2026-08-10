<?php

namespace LiturgicalCalendar\Components\Locale;

/**
 * Applies the process-global locale state for a render, and puts it back.
 *
 * Two things have to move together. `setlocale()` sets the category, but glibc's
 * gettext reads the `LANGUAGE` environment variable *above* `LC_MESSAGES`: a host
 * that exports `LANGUAGE` silently overrides every locale this library sets, and
 * `LANGUAGE=C` disables catalog lookup outright. Composer exports exactly that
 * into the scripts it runs, which is why the translated-output tests skipped
 * under `composer test` and in CI while passing under a bare `vendor/bin/phpunit`.
 * The API's `LocaleConfigurator` pins `LANGUAGE` for the same reason.
 *
 * Where this parts company with the API: the API owns its process and pins the
 * locale for the request. This is a library living inside someone else's process,
 * so every change is scoped and reverted — `LANGUAGE` included, and including the
 * case where the host never set it. The restore runs in a `finally`, so an
 * exception while rendering cannot strand the host in a locale it never chose.
 *
 * @package LiturgicalCalendar\Components\Locale
 */
final class ScopedLocale
{
    /**
     * @param int $category The `LC_*` category that was changed.
     * @param string|null $previousLocale The category's prior value, or null if it could not be read.
     * @param string|false $previousLanguage The prior `LANGUAGE`, or false if it was unset.
     * @param string|false $appliedLocale The locale actually set, or false if none of the candidates existed.
     */
    private function __construct(
        private readonly int $category,
        private readonly ?string $previousLocale,
        private readonly string|false $previousLanguage,
        public readonly string|false $appliedLocale
    ) {
    }

    /**
     * Apply `$requestLocale` to `$category` and `LANGUAGE`, returning the scope to restore.
     *
     * Prefer {@see self::within()}; this pair exists for callers that set and
     * restore in separate methods, as WebCalendar does.
     *
     * @param int $category An `LC_*` constant, e.g. `LC_MESSAGES`.
     * @param string $requestLocale The request locale, e.g. `en`, `it_IT`.
     * @return self The open scope; call {@see self::restore()} on it.
     */
    public static function apply(int $category, string $requestLocale): self
    {
        $previousLocale   = setlocale($category, '0');
        $previousLanguage = getenv('LANGUAGE');

        $applied = setlocale($category, LocaleResolver::candidates($requestLocale));
        putenv('LANGUAGE=' . self::languageEnv($requestLocale, $applied));

        return new self(
            $category,
            false === $previousLocale ? null : $previousLocale,
            $previousLanguage,
            $applied
        );
    }

    /**
     * Put the category and `LANGUAGE` back exactly as they were found.
     *
     * @return void
     */
    public function restore(): void
    {
        if (null !== $this->previousLocale) {
            setlocale($this->category, $this->previousLocale);
        }

        if (false === $this->previousLanguage) {
            putenv('LANGUAGE');
        } else {
            putenv('LANGUAGE=' . $this->previousLanguage);
        }
    }

    /**
     * Run `$render` with `$category` and `LANGUAGE` set to `$requestLocale`, then restore both.
     *
     * @template T
     * @param int $category An `LC_*` constant, e.g. `LC_MESSAGES`.
     * @param string $requestLocale The request locale, e.g. `en`, `it_IT`.
     * @param callable(): T $render Produces the result; called once.
     * @return T Whatever `$render` returned.
     */
    public static function within(int $category, string $requestLocale, callable $render): mixed
    {
        $scope = self::apply($category, $requestLocale);

        try {
            return $render();
        } finally {
            $scope->restore();
        }
    }

    /**
     * Pin `LANGUAGE` for a request locale without capturing anything to restore.
     *
     * For the set-and-leave callers — ApiOptions translates at render time, long
     * after the locale was configured, so there is no scope to close. Without
     * this, an inherited `LANGUAGE` (Composer exports `LANGUAGE=C`) silently wins
     * over the `setlocale()` those callers just made.
     *
     * @param string $requestLocale The request locale, e.g. `en`, `it_IT`.
     * @param string|false $appliedLocale The locale `setlocale()` accepted, or false if none did.
     * @return void
     */
    public static function pinLanguage(string $requestLocale, string|false $appliedLocale): void
    {
        putenv('LANGUAGE=' . self::languageEnv($requestLocale, $appliedLocale));
    }

    /**
     * The `LANGUAGE` value to pin for a request locale.
     *
     * A colon-separated preference list, most specific first, ending in `en` so a
     * language with no catalog falls through to the untranslated msgid base rather
     * than to whatever the host happened to prefer.
     *
     * @param string $requestLocale The request locale.
     * @param string|false $appliedLocale The locale `setlocale()` actually accepted, if any.
     * @return string The colon-separated `LANGUAGE` value.
     */
    private static function languageEnv(string $requestLocale, string|false $appliedLocale): string
    {
        $language = \Locale::getPrimaryLanguage($requestLocale);
        if (null === $language || '' === $language) {
            $language = $requestLocale;
        }

        $preferences = [];
        // setlocale(LC_ALL, …) reports a composite — "LC_CTYPE=C;LC_NUMERIC=it_IT.utf8;…" —
        // when the categories disagree. Setting LC_ALL to a single name returns a
        // plain locale name on glibc, so this has not been observed here, but the
        // value goes on to become an environment variable and a composite would
        // make LANGUAGE nonsense. Only a real locale name is admitted.
        if (false !== $appliedLocale && false === str_contains($appliedLocale, '=') && false === str_contains($appliedLocale, ';')) {
            $preferences[] = $appliedLocale;
            // "it_IT.utf8" also has to be offered as "it_IT": gettext matches
            // catalog directory names, which never carry a codeset suffix.
            $withoutCodeset = strtok($appliedLocale, '.');
            if (false !== $withoutCodeset) {
                $preferences[] = $withoutCodeset;
            }
        }
        $preferences[] = $language;
        $preferences[] = 'en';

        return implode(':', array_unique(array_filter($preferences, static fn(string $v): bool => '' !== $v)));
    }
}
