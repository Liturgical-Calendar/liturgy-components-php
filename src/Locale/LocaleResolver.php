<?php

namespace LiturgicalCalendar\Components\Locale;

/**
 * Resolves a request locale to system locale names that can actually be set.
 *
 * Borrowed from the API's `LocaleConfigurator`, which solved this first and more
 * thoroughly. The ladders this replaces guessed a region by uppercasing the
 * language — `it` => `it_IT`, `fr` => `fr_FR` — which is right only because those
 * happen to be real locale names. `en` produced `en_EN`, which exists nowhere, so
 * `setlocale()` returned false and the caller silently went on rendering in
 * whatever locale the process already held. CLDR likely subtags give the real
 * answer: `en` => `US`, `pt` => `BR`.
 *
 * @package LiturgicalCalendar\Components\Locale
 */
final class LocaleResolver
{
    /** @var array<string,string>|null Cached language => region map. */
    private static ?array $likelyRegions = null;

    /**
     * System locale candidates for a request locale, most specific first.
     *
     * Each region-bearing form is offered as `.utf8`, `.UTF-8` and bare, because
     * distributions disagree on the spelling of the codeset suffix. The
     * language-only forms come last as a genuine fallback rather than a first
     * guess, since a system carries `it_IT.utf8` far more often than bare `it`.
     *
     * @param string $requestLocale The request locale, e.g. `en`, `it_IT`, `pt_PT`.
     * @return string[] Candidates for `setlocale()`, most specific first.
     */
    public static function candidates(string $requestLocale): array
    {
        $canonical = \Locale::canonicalize($requestLocale);
        if (null === $canonical || '' === $canonical) {
            $canonical = $requestLocale;
        }

        $language = \Locale::getPrimaryLanguage($canonical);
        if (null === $language || '' === $language) {
            $language = $canonical;
        }

        // An explicit region is the caller's stated intent and outranks CLDR's
        // guess: pt_PT must not be quietly turned into pt_BR.
        $region = \Locale::getRegion($canonical);
        if (null === $region || '' === $region) {
            $region = self::likelyRegion($language);
        }

        $candidates = [];
        if ('' !== $region) {
            $langRegion = $language . '_' . $region;
            $candidates = [$langRegion . '.utf8', $langRegion . '.UTF-8', $langRegion];
        }

        $candidates[] = $language . '.utf8';
        $candidates[] = $language . '.UTF-8';
        $candidates[] = $language;

        return array_values(array_unique($candidates));
    }

    /**
     * The CLDR likely region subtag for a language, or `''` when unknown.
     *
     * Only the region is taken: glibc rejects script-bearing names like
     * `en_Latn_US`, so callers compose `language_REGION` themselves.
     *
     * @param string $language A primary language subtag, e.g. `en`.
     * @return string The region subtag, e.g. `US`, or `''` if the language is unknown.
     */
    public static function likelyRegion(string $language): string
    {
        if (null === self::$likelyRegions) {
            self::$likelyRegions = self::loadLikelyRegions(__DIR__ . '/likelyRegions.json');
        }

        return self::$likelyRegions[$language] ?? '';
    }

    /**
     * Read the language => region map, degrading to an empty map on any failure.
     *
     * A shipped data file that cannot be read or parsed means a broken install,
     * not a broken request, and this sits on the render path of every component.
     * Throwing a `JsonException` out of a locale lookup would turn a packaging
     * problem into an unhandled error in the caller's page, so this warns and
     * returns nothing — matching how the components already treat an unbindable
     * text domain. Callers then fall back to the language-only candidates, which
     * is the pre-CLDR behaviour rather than a new failure mode.
     *
     * The empty map is cached like any other, so a broken file warns once rather
     * than on every lookup.
     *
     * @param string $path Absolute path to the JSON map.
     * @return array<string,string> The language => region map, or `[]` on failure.
     */
    private static function loadLikelyRegions(string $path): array
    {
        $json = @file_get_contents($path);
        if (false === $json) {
            trigger_error(
                "Failed to read the likely region map at {$path}. Locales without an explicit region "
                . 'will fall back to language-only candidates.',
                E_USER_WARNING
            );
            return [];
        }

        try {
            $data = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            trigger_error(
                "Failed to parse the likely region map at {$path}: {$e->getMessage()}. Locales without "
                . 'an explicit region will fall back to language-only candidates.',
                E_USER_WARNING
            );
            return [];
        }

        if (false === is_array($data) || false === isset($data['likelyRegions']) || false === is_array($data['likelyRegions'])) {
            trigger_error(
                "The likely region map at {$path} has no likelyRegions object. Locales without an "
                . 'explicit region will fall back to language-only candidates.',
                E_USER_WARNING
            );
            return [];
        }

        /** @var array<string,string> $regions */
        $regions = $data['likelyRegions'];
        return $regions;
    }
}
