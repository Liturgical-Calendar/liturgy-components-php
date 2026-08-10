<?php

namespace LiturgicalCalendar\Components;

/**
 * Enum for the liturgical rite.
 *
 * The cases and their string values match the `Rite` enum in
 * liturgy-components-js and the values the API serves, exactly. Anything that
 * crosses the wire or appears in a URL path segment uses these values, so they
 * are never abbreviated or capitalized.
 *
 * The properties hung off the enum are structural facts about the rite — not
 * user preferences, and not properties of a diocese. They are exposed through
 * methods rather than a parallel constant array so there is one place to look
 * when a rite gains a property.
 *
 * @package LiturgicalCalendar\Components
 * @author John Romano D'Orazio <priest@johnromanodorazio.com>
 */
enum Rite: string
{
    case ROMAN     = 'roman';
    case AMBROSIAN = 'ambrosian';

    /**
     * Resolves a rite given as either a case or its string value.
     *
     * Every public method in the library that takes a rite takes `Rite|string`,
     * because the components already mix the two styles — an enum where a caller
     * has one to hand, a validated string where the value arrived from a form
     * post or a URL. Each of them needs the same three lines to normalize it, so
     * the three lines live here instead of in each of them.
     *
     * **Not to be replaced by the built-in `Rite::from()`.** A backed enum
     * already has one, and it throws a `\ValueError` that does not name the
     * valid values — which is precisely why every caller hand-rolled this
     * instead of using it. The message below is asserted by the tests of each
     * component that refuses a rite, so it is observable behaviour: preserve it
     * verbatim.
     *
     * @param Rite|string $rite A `Rite` case, or its string value.
     *
     * @return self The resolved rite.
     *
     * @throws \Exception If the string is not a valid rite.
     */
    public static function resolve(Rite|string $rite): self
    {
        if ($rite instanceof self) {
            return $rite;
        }
        $resolved = self::tryFrom($rite);
        if (null === $resolved) {
            $valid = implode(', ', array_map(fn(self $case) => $case->value, self::cases()));
            throw new \Exception("Invalid rite: {$rite}, valid values are: {$valid}");
        }
        return $resolved;
    }

    /**
     * Whether the rite has national calendars at all.
     *
     * The Ambrosian rite has none: it is the rite of a handful of sees in
     * Lombardy and Ticino, with no national tier above them. A CalendarSelect
     * under a rite with no national tier skips the national pass entirely
     * rather than rendering an empty group.
     *
     * @return bool True if the rite has a national tier.
     */
    public function hasNationalTier(): bool
    {
        return match ($this) {
            self::ROMAN     => true,
            self::AMBROSIAN => false,
        };
    }

    /**
     * Whether the rite fixes Epiphany, Ascension, Corpus Christi and the
     * Eternal High Priest in its own books.
     *
     * The reformed Ambrosian Missal fixes Epiphany to 6 January, Ascension to
     * the fortieth day of Easter, and Corpus Domini to the Thursday after
     * Trinity; the Eternal High Priest is not established in the rite at all.
     * The corresponding API parameters are therefore meaningless under it.
     *
     * @return bool True if the rite fixes its own temporal options.
     */
    public function hasFixedTemporalOptions(): bool
    {
        return match ($this) {
            self::ROMAN     => false,
            self::AMBROSIAN => true,
        };
    }

    /**
     * The earliest year the API will serve for the rite.
     *
     * 1970 for the Roman rite, and 1976 for the Ambrosian — the first year of
     * the reformed Ambrosian Missal. Years before that cannot be computed.
     *
     * @return int The first computable year.
     */
    public function minYear(): int
    {
        return match ($this) {
            self::ROMAN     => 1970,
            self::AMBROSIAN => 1976,
        };
    }

    /**
     * The name of the rite-level calendar, as the untranslated gettext msgid.
     *
     * This is what a CalendarSelect's empty option carries in place of a bare
     * `---`: choosing neither a nation nor a diocese is not choosing nothing,
     * it is choosing this calendar, and the option should say so. Callers
     * translate it through the `rite` domain — it is never returned already
     * translated, so that the msgid stays greppable from the .pot file.
     *
     * @return string The untranslated name of the rite-level calendar.
     */
    public function emptyOptionLabel(): string
    {
        return match ($this) {
            self::ROMAN     => 'General Roman Calendar',
            self::AMBROSIAN => 'Ambrosian Calendar',
        };
    }
}
