# Rite Awareness for liturgy-components-php — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task.
> Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bring the PHP component library to parity with liturgy-components-js on the liturgical rite — a `Rite` enum, rite-filtered calendar options, and a `RiteSelect`
component — which also fixes the `lugano_ch` crash that currently makes `LiturgicalCalendarFrontend/usage.php` fatal on every request.

**Architecture:** The rite is a partition applied _before_ the existing nation pass. `CalendarSelect` gains a `rite` (default Roman) and filters dioceses by it, so an Ambrosian
diocese never reaches the code that assumes its nation has a national calendar. A rite with no national tier skips the national pass entirely. `RiteSelect` is a new render-only
component: no linking method, because the library renders once server-side and ships no JavaScript.

**Tech Stack:** PHP >= 8.1 (backed enums, readonly promoted properties), ext-intl, gettext, PHPUnit, phpcs (PSR-12 + custom ruleset), PHPStan.

## Global Constraints

- **PHP floor is 8.1.** No 8.2+ syntax (no readonly classes, no DNF types).
- **Rite values must match the JS enum and the API exactly:** `roman`, `ambrosian`. Never abbreviate or capitalize.
- **`composer.json` has no `version` key.** Packagist reads git tags. The release is the tag `v4.0.0`; there is no version string to edit and **no CHANGELOG.md in this repo** —
  release notes live in the GitHub release.
- **Every task ends green:** `composer test`, `composer lint`, `composer analyse`, `composer parallel-lint`.
- **Missing rite data must not break the library.** An older API that omits `rite` on a diocese, or omits `ambrosian_calendars` entirely, must keep working: absent `rite` means
  `roman`, absent rite-level list means `[]`. This mirrors how the JS library tolerates legacy metadata.
- **Existing style:** enums live beside the component that owns them (`src/CalendarSelect/OptionsType.php`); components are top-level (`src/CalendarSelect.php`). Setters are
  chainable and return `self`. Caller-supplied strings go through `htmlspecialchars($v, ENT_QUOTES, 'UTF-8')`.
- **Target branch:** `feat/rite-awareness`, which already carries the design spec.

## File Structure

| File                                              | Responsibility                                                                          | Task |
| ------------------------------------------------- | --------------------------------------------------------------------------------------- | ---- |
| `src/Models/Index/DiocesanCalendar.php`           | add `rite` to the model + `fromArray`/`toArray`                                         | 1    |
| `src/Models/Index/CalendarIndex.php`              | add `ambrosianCalendars` / `ambrosianCalendarsKeys`                                     | 1    |
| `src/Models/Index/RiteCalendar.php`               | new model for a rite-level calendar entry                                               | 1    |
| `src/Rite.php`                                    | the `Rite` enum and its structural properties                                           | 2    |
| `src/CalendarSelect.php`                          | `rite` option, `rite()` setter, rite partition, `data-calendartype`, empty-option label | 3–6  |
| `src/RiteSelect.php`                              | the new component                                                                       | 7    |
| `src/i18n/rite.pot`                               | shared gettext domain `rite`                                                            | 6    |
| `tests/Models/Index/DiocesanCalendarRiteTest.php` | model rite parsing                                                                      | 1    |
| `tests/RiteTest.php`                              | enum + properties                                                                       | 2    |
| `tests/CalendarSelectRiteTest.php`                | rite option, partition, crash regression, attributes, label                             | 3–6  |
| `tests/RiteSelectTest.php`                        | the new component                                                                       | 7    |

**Why `src/i18n/` rather than a per-component directory:** the existing domains are per-component (`src/ApiOptions/i18n/litcompphp.pot`, `src/WebCalendar/i18n/webcalendar.pot`)
because each serves one component. The rite strings are used by **two** — `CalendarSelect` renders the empty-option label, `RiteSelect` renders the options — so a shared
location avoids one component binding a domain that lives under another.

---

### Task 1: Model plumbing for the rite

The API already serves everything needed and the models ignore all of it. Verified against the live API:

```console
$ curl -sS https://litcal.johnromanodorazio.com/api/dev/calendars
top-level keys: [ambrosian_calendars, ambrosian_calendars_keys, diocesan_calendars, ...]
sample diocesan entry: {"calendar_id":"charlo_ca", ..., "rite":"roman"}
ambrosian_calendars: [{"calendar_id":"ambrosian","rite":"ambrosian","locales":["it","la"]}]
```

Nothing downstream can filter by rite until this lands.

**Files:**

- Modify: `src/Models/Index/DiocesanCalendar.php` (constructor ~21-28, `fromArray` ~109, `toArray` ~145)
- Modify: `src/Models/Index/CalendarIndex.php` (constructor ~25-32, `fromArray` ~68)
- Create: `src/Models/Index/RiteCalendar.php`
- Test: `tests/Models/Index/DiocesanCalendarRiteTest.php`

**Interfaces:**

- Produces: `DiocesanCalendar::$rite` (`string`, `'roman'` when absent); `CalendarIndex::$ambrosianCalendars` (`RiteCalendar[]`), `CalendarIndex::$ambrosianCalendarsKeys`
  (`string[]`); `RiteCalendar::__construct(string $calendarId, string $rite, array $locales)` and `RiteCalendar::fromArray(array $data): self`.
- Note: `$rite` is a plain `string` here, not the `Rite` enum. Models mirror the wire format; Task 2's enum is the domain type, and `CalendarSelect` converts. This keeps an
  unknown future rite from throwing inside metadata parsing.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace LiturgicalCalendar\Components\Tests\Models\Index;

use LiturgicalCalendar\Components\Models\Index\DiocesanCalendar;
use PHPUnit\Framework\TestCase;

final class DiocesanCalendarRiteTest extends TestCase
{
    public function testParsesRiteFromMetadata(): void
    {
        $calendar = DiocesanCalendar::fromArray([
            'calendar_id' => 'lugano_ch',
            'diocese'     => 'Lugano',
            'nation'      => 'CH',
            'locales'     => ['it_CH'],
            'timezone'    => 'Europe/Zurich',
            'rite'        => 'ambrosian',
        ]);

        $this->assertSame('ambrosian', $calendar->rite);
    }

    public function testDefaultsToRomanWhenRiteAbsent(): void
    {
        $calendar = DiocesanCalendar::fromArray([
            'calendar_id' => 'romamo_it',
            'diocese'     => 'Roma',
            'nation'      => 'IT',
            'locales'     => ['it_IT'],
            'timezone'    => 'Europe/Rome',
        ]);

        $this->assertSame('roman', $calendar->rite);
    }
}
```

- [ ] **Step 2: Run the test and watch it fail**

Run: `composer test-filter DiocesanCalendarRiteTest`
Expected: FAIL — `Undefined property: DiocesanCalendar::$rite`. If it fails for any other reason, fix that first.

- [ ] **Step 3: Add `rite` to the model**

In `src/Models/Index/DiocesanCalendar.php`, add to the promoted constructor after `$timezone`:

```php
        public readonly string $rite = 'roman',
```

Place it before `$group` only if you also update every positional caller; the safer edit is to append it last and pass it by name. In `fromArray`, add to the `new self(...)` call:

```php
            rite: self::getNullableString($data, 'rite') ?? 'roman',
```

In `toArray`, add `'rite' => $this->rite,` after `'timezone'`, and add `rite: string` to the array shape docblock.

- [ ] **Step 4: Run the test and watch it pass**

Run: `composer test-filter DiocesanCalendarRiteTest`
Expected: PASS, 2 tests.

- [ ] **Step 5: Add the rite-level calendar model**

Create `src/Models/Index/RiteCalendar.php`:

```php
<?php

namespace LiturgicalCalendar\Components\Models\Index;

/**
 * A rite-level calendar: the calendar a rite serves when no nation or diocese
 * is chosen. The API announces these under `{rite}_calendars` — so
 * `ambrosian_calendars` for the Ambrosian rite. The Roman rite has no such key,
 * because its rite-level calendar is the General Roman Calendar itself.
 *
 * @package LiturgicalCalendar\Components\Models\Index
 */
final class RiteCalendar
{
    /**
     * @param string        $calendarId The calendar id, e.g. `ambrosian`.
     * @param string        $rite       The rite this calendar belongs to.
     * @param array<string> $locales    The locales the calendar is served in.
     */
    public function __construct(
        public readonly string $calendarId,
        public readonly string $rite,
        public readonly array $locales
    ) {
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $locales = $data['locales'] ?? [];
        if (!is_array($locales)) {
            throw new \InvalidArgumentException('Expected array for locales');
        }
        /** @var array<string> $locales */

        $calendarId = $data['calendar_id'] ?? null;
        $rite       = $data['rite'] ?? null;
        if (!is_string($calendarId) || !is_string($rite)) {
            throw new \InvalidArgumentException('Expected string for calendar_id and rite');
        }

        return new self(calendarId: $calendarId, rite: $rite, locales: $locales);
    }
}
```

- [ ] **Step 6: Add the rite-level lists to the index**

In `src/Models/Index/CalendarIndex.php`, append two promoted constructor parameters after `$locales`:

```php
        public readonly array $ambrosianCalendars = [],
        public readonly array $ambrosianCalendarsKeys = []
```

In `fromArray`, before the `new self(...)`:

```php
        $ambrosianCalendarsData = $data['ambrosian_calendars'] ?? [];
        if (!is_array($ambrosianCalendarsData)) {
            throw new \InvalidArgumentException('Expected array for ambrosian_calendars');
        }
        $ambrosianCalendars = array_map(
            function ($item) {
                if (!is_array($item)) {
                    throw new \InvalidArgumentException('Expected array item in ambrosian_calendars');
                }
                /** @var array<string,mixed> $item */
                return RiteCalendar::fromArray($item);
            },
            $ambrosianCalendarsData
        );

        $ambrosianCalendarsKeys = $data['ambrosian_calendars_keys'] ?? [];
        if (!is_array($ambrosianCalendarsKeys)) {
            throw new \InvalidArgumentException('Expected array for ambrosian_calendars_keys');
        }
        /** @var array<string> $ambrosianCalendarsKeys */
```

and pass them by name in the `new self(...)` call. Both default to `[]`, so an API that omits them still parses — that is the legacy-metadata constraint.

- [ ] **Step 7: Run the full suite**

Run: `composer test`
Expected: still 11 errors from `CalendarSelectTest` (the crash is untouched), and **no new failures**. The 11 are the gate for Task 4, not this one.

- [ ] **Step 8: Lint and commit**

```bash
composer lint && composer analyse && composer parallel-lint
git add src/Models tests/Models
git commit -m "Parse the rite the API already serves

DiocesanCalendar dropped the rite field and CalendarIndex dropped the
ambrosian_calendars list entirely, so nothing downstream could partition
by rite. Both are present in served metadata.

Absent rite means roman and an absent rite-level list means empty, so an
older API keeps working."
```

---

### Task 2: The `Rite` enum

**Files:**

- Create: `src/Rite.php`
- Test: `tests/RiteTest.php`

**Interfaces:**

- Consumes: nothing.
- Produces: `Rite::ROMAN` (`'roman'`), `Rite::AMBROSIAN` (`'ambrosian'`); `Rite::hasNationalTier(): bool`, `Rite::hasFixedTemporalOptions(): bool`, `Rite::minYear(): int`,
  `Rite::emptyOptionLabel(): string` (returns the **untranslated msgid**; Task 6 translates it).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace LiturgicalCalendar\Components\Tests;

use LiturgicalCalendar\Components\Rite;
use PHPUnit\Framework\TestCase;

final class RiteTest extends TestCase
{
    public function testValuesMatchTheApiAndTheJsLibrary(): void
    {
        $this->assertSame('roman', Rite::ROMAN->value);
        $this->assertSame('ambrosian', Rite::AMBROSIAN->value);
    }

    public function testOnlyTheRomanRiteHasANationalTier(): void
    {
        $this->assertTrue(Rite::ROMAN->hasNationalTier());
        $this->assertFalse(Rite::AMBROSIAN->hasNationalTier());
    }

    public function testOnlyTheAmbrosianRiteFixesItsTemporalOptions(): void
    {
        $this->assertFalse(Rite::ROMAN->hasFixedTemporalOptions());
        $this->assertTrue(Rite::AMBROSIAN->hasFixedTemporalOptions());
    }

    public function testMinYearIsTheFirstYearTheRiteCanBeComputed(): void
    {
        $this->assertSame(1970, Rite::ROMAN->minYear());
        $this->assertSame(1976, Rite::AMBROSIAN->minYear());
    }

    public function testEmptyOptionLabelNamesTheRiteLevelCalendar(): void
    {
        $this->assertSame('General Roman Calendar', Rite::ROMAN->emptyOptionLabel());
        $this->assertSame('Ambrosian Calendar', Rite::AMBROSIAN->emptyOptionLabel());
    }

    public function testUnknownRiteIsRejected(): void
    {
        $this->assertNull(Rite::tryFrom('byzantine'));
    }
}
```

- [ ] **Step 2: Run the test and watch it fail**

Run: `composer test-filter RiteTest`
Expected: FAIL — `Class "LiturgicalCalendar\Components\Rite" not found`.

- [ ] **Step 3: Write the enum**

Create `src/Rite.php`:

```php
<?php

namespace LiturgicalCalendar\Components;

/**
 * A liturgical rite.
 *
 * The cases and their string values match the `Rite` enum in
 * liturgy-components-js and the values the API serves, exactly. Anything that
 * crosses the wire or appears in a URL path segment uses these values.
 *
 * The properties hung off the enum are structural facts about the rite — not
 * user preferences and not properties of a diocese. They are exposed through
 * methods rather than a parallel constant array so there is one place to look.
 *
 * @package LiturgicalCalendar\Components
 * @author John Romano D'Orazio <priest@johnromanodorazio.com>
 */
enum Rite: string
{
    case ROMAN     = 'roman';
    case AMBROSIAN = 'ambrosian';

    /**
     * Whether the rite has national calendars at all.
     *
     * The Ambrosian rite has none: it is a diocesan rite of a handful of sees
     * in Lombardy and Ticino, with no national tier above them. A CalendarSelect
     * under a rite with no national tier skips the national pass entirely
     * rather than rendering an empty group.
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
     * the fortieth day of Easter and Corpus Domini to the Thursday after
     * Trinity; the Eternal High Priest is not established in the rite at all.
     * The corresponding API parameters are therefore meaningless under it.
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
     * `---`: choosing "no nation and no diocese" is not choosing nothing, it is
     * choosing this calendar. Callers translate through the `rite` domain.
     */
    public function emptyOptionLabel(): string
    {
        return match ($this) {
            self::ROMAN     => 'General Roman Calendar',
            self::AMBROSIAN => 'Ambrosian Calendar',
        };
    }
}
```

- [ ] **Step 4: Run the test and watch it pass**

Run: `composer test-filter RiteTest`
Expected: PASS, 6 tests.

- [ ] **Step 5: Lint and commit**

```bash
composer lint && composer analyse && composer parallel-lint
git add src/Rite.php tests/RiteTest.php
git commit -m "Add the Rite enum

Cases and values match the JS enum and the API exactly. The structural
facts hang off the enum as methods rather than a parallel constant, so
there is one place to look when a rite gains a property."
```

---

### Task 3: `CalendarSelect` accepts a rite

Option and setter only — no behaviour change yet. Splitting this from the partition keeps the crash fix (Task 4) reviewable on its own.

**Files:**

- Modify: `src/CalendarSelect.php` (add property near the other private state; constructor ~168 beside the `nationFilter` block; setter after `nationFilter()` ~325)
- Test: `tests/CalendarSelectRiteTest.php`

**Interfaces:**

- Consumes: `Rite` from Task 2.
- Produces: `CalendarSelect::rite(Rite|string $rite): self` (chainable), the `rite` constructor option, and `private Rite $rite = Rite::ROMAN`.
- Accepting `Rite|string` mirrors `setOptions(OptionsType)` (enum) and `nationFilter(string)` (validated string) both existing in this class; a string is validated through
  `Rite::tryFrom()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace LiturgicalCalendar\Components\Tests;

use LiturgicalCalendar\Components\CalendarSelect;
use LiturgicalCalendar\Components\Rite;
use PHPUnit\Framework\TestCase;

final class CalendarSelectRiteTest extends TestCase
{
    public function testDefaultsToTheRomanRite(): void
    {
        $select = new CalendarSelect(['locale' => 'en']);
        $this->assertSame(Rite::ROMAN, $select->getRite());
    }

    public function testAcceptsARiteEnumAndIsChainable(): void
    {
        $select = new CalendarSelect(['locale' => 'en']);
        $this->assertSame($select, $select->rite(Rite::AMBROSIAN));
        $this->assertSame(Rite::AMBROSIAN, $select->getRite());
    }

    public function testAcceptsARiteAsAString(): void
    {
        $select = ( new CalendarSelect(['locale' => 'en']) )->rite('ambrosian');
        $this->assertSame(Rite::AMBROSIAN, $select->getRite());
    }

    public function testAcceptsARiteAsAConstructorOption(): void
    {
        $select = new CalendarSelect(['locale' => 'en', 'rite' => 'ambrosian']);
        $this->assertSame(Rite::AMBROSIAN, $select->getRite());
    }

    public function testRejectsAnUnknownRiteNamingTheValidValues(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Invalid rite: byzantine.*roman.*ambrosian/');
        ( new CalendarSelect(['locale' => 'en']) )->rite('byzantine');
    }
}
```

- [ ] **Step 2: Run the test and watch it fail**

Run: `composer test-filter CalendarSelectRiteTest`
Expected: FAIL — `Call to undefined method ...::getRite()`.

- [ ] **Step 3: Implement the option, setter and getter**

Add the property beside the other private state in `src/CalendarSelect.php`:

```php
    private Rite $rite = Rite::ROMAN;
```

Add `use LiturgicalCalendar\Components\Rite;` to the imports. In the constructor, beside the existing `nationFilter` block:

```php
        if (isset($options['rite'])) {
            $this->rite($options['rite']);
        }
```

Add after `nationFilter()`:

```php
    /**
     * Sets the liturgical rite whose calendars this select offers.
     *
     * Defaults to the Roman rite, which preserves the behaviour of every
     * release before rite awareness existed.
     *
     * @param Rite|string $rite A Rite case, or its string value.
     *
     * @return $this
     * @throws \Exception If the string is not a valid rite.
     */
    public function rite(Rite|string $rite): self
    {
        if (is_string($rite)) {
            $resolved = Rite::tryFrom($rite);
            if (null === $resolved) {
                $valid = implode(', ', array_map(fn(Rite $case) => $case->value, Rite::cases()));
                throw new \Exception("Invalid rite: {$rite}, valid values are: {$valid}");
            }
            $rite = $resolved;
        }
        $this->rite = $rite;
        return $this;
    }

    /**
     * Returns the liturgical rite this select is built for.
     */
    public function getRite(): Rite
    {
        return $this->rite;
    }
```

- [ ] **Step 4: Run the test and watch it pass**

Run: `composer test-filter CalendarSelectRiteTest`
Expected: PASS, 5 tests. `composer test` still shows the same 11 pre-existing errors — untouched.

- [ ] **Step 5: Lint and commit**

```bash
composer lint && composer analyse && composer parallel-lint
git add src/CalendarSelect.php tests/CalendarSelectRiteTest.php
git commit -m "Give CalendarSelect a rite, defaulting to Roman

Option plus chainable setter, validated the way nationFilter is. No
behaviour change yet: the partition that uses it is the next commit, so
the crash fix stays reviewable on its own."
```

---

### Task 4: Partition by rite — the crash fix

This is the task that turns the 11 pre-existing errors green.

**Files:**

- Modify: `src/CalendarSelect.php` — `buildAllOptions()` (~558)
- Test: `tests/CalendarSelectRiteTest.php` (append)

**Interfaces:**

- Consumes: `CalendarSelect::$rite` (Task 3), `DiocesanCalendar::$rite` (Task 1), `Rite::hasNationalTier()` (Task 2).
- Produces: no new public surface.

- [ ] **Step 1: Write the failing regression test**

Append to `tests/CalendarSelectRiteTest.php`:

```php
    public function testRendersWithADioceseWhoseNationHasNoNationalCalendar(): void
    {
        // lugano_ch sits in nation CH, which has no national calendar. Before
        // the rite partition this threw:
        //   TypeError: hasNationalCalendarWithDioceses(): Argument #1 ($item)
        //   must be of type NationalCalendar, null given
        $select = new CalendarSelect(['locale' => 'en']);
        $html   = $select->getSelect();

        $this->assertStringContainsString('<select', $html);
    }

    public function testAmbrosianDiocesesAreAbsentUnderTheRomanRite(): void
    {
        $html = ( new CalendarSelect(['locale' => 'en']) )->getSelect();

        $this->assertStringNotContainsString('lugano_ch', $html);
        $this->assertStringNotContainsString('milano_it', $html);
    }

    public function testAmbrosianDiocesesArePresentUnderTheAmbrosianRite(): void
    {
        $html = ( new CalendarSelect(['locale' => 'en', 'rite' => 'ambrosian']) )->getSelect();

        $this->assertStringContainsString('lugano_ch', $html);
        $this->assertStringContainsString('milano_it', $html);
    }

    public function testNoNationalTierUnderARiteThatHasNone(): void
    {
        $html = ( new CalendarSelect(['locale' => 'en', 'rite' => 'ambrosian']) )->getSelect();

        // Vatican is a national calendar with no dioceses; under a rite with no
        // national tier no national option may appear at all.
        $this->assertStringNotContainsString('data-calendartype="national"', $html);
        $this->assertStringNotContainsString('value="VA"', $html);
    }
```

- [ ] **Step 2: Run the test and watch it fail**

Run: `composer test-filter CalendarSelectRiteTest`
Expected: the first test FAILS with the `TypeError` above — the exact crash. The others fail on the assertions. If the first passes, the fixture no longer contains `lugano_ch`
and the test is worthless: stop and check what metadata the suite loads.

- [ ] **Step 3: Apply the partition**

In `buildAllOptions()`, replace the opening diocese loop:

```php
        foreach ($this->calendarIndex->diocesanCalendars as $diocesanCalendar) {
            if (!$this->hasNationalCalendarWithDioceses($diocesanCalendar->nation)) {
                // we add all nations with dioceses to the nations list
                $this->addNationalCalendarWithDioceses($diocesanCalendar->nation);
            }
            $this->addDioceseOption($diocesanCalendar);
        }
```

with:

```php
        // The rite partition comes FIRST, before anything asks which national
        // calendar a diocese's nation has. That question only has an answer
        // within the Roman rite: the Ambrosian rite has no national tier, so
        // its dioceses have nations that own no national calendar at all —
        // lugano_ch in CH is the live example, and reaching the nation pass
        // with it is what threw a TypeError on every render.
        $diocesanCalendars = array_filter(
            $this->calendarIndex->diocesanCalendars,
            fn(DiocesanCalendar $diocesanCalendar) => $diocesanCalendar->rite === $this->rite->value
        );

        foreach ($diocesanCalendars as $diocesanCalendar) {
            if (!$this->hasNationalCalendarWithDioceses($diocesanCalendar->nation)) {
                // we add all nations with dioceses to the nations list
                $this->addNationalCalendarWithDioceses($diocesanCalendar->nation);
            }
            $this->addDioceseOption($diocesanCalendar);
        }
```

**Correction, found by executing this task.** The partition alone is not enough. It stops the crash under the
Roman rite, where `lugano_ch` is filtered out — but under the **Ambrosian** rite that diocese is deliberately
admitted, and the nation _grouping_ then crashes identically, because the nations its dioceses belong to own no
national calendar by definition. So the grouping is conditional too. In the loop above, register a national
calendar only when the rite has a tier, and otherwise open the bucket directly:

```php
            if ($this->rite->hasNationalTier()) {
                if (!$this->hasNationalCalendarWithDioceses($diocesanCalendar->nation)) {
                    $this->addNationalCalendarWithDioceses($diocesanCalendar->nation);
                }
            } elseif (false === isset($this->dioceseOptions[$diocesanCalendar->nation])) {
                $this->dioceseOptions[$diocesanCalendar->nation] = [];
            }
```

and render a flat list instead of optgroups, immediately before the `nationalCalendarsWithDioceses` sort:

```php
        if (false === $this->rite->hasNationalTier()) {
            foreach ($this->dioceseOptions as $optionsForNation) {
                array_push($this->dioceseOptionsGrouped, implode('', $optionsForNation));
            }
            return;
        }
```

Flat is what liturgy-components-js renders for the Ambrosian rite — Lugano, Bergamo, Milano and Novara in one
run, no optgroups.

Then guard the national pass. Wrap the `$sortedNationalCalendars` block and the `foreach ($sortedNationalCalendars ...)` loop that follows it:

```php
        if ($this->rite->hasNationalTier()) {
            $sortedNationalCalendars = $this->calendarIndex->nationalCalendars;
            // ... existing usort and foreach, unchanged ...
        }
```

Leave the `nationalCalendarsWithDioceses` block below it alone: under a tierless rite that array is empty, because the partition above admitted no dioceses whose nation carries
a national calendar.

- [ ] **Step 4: Run the tests and watch them pass**

Run: `composer test-filter CalendarSelectRiteTest` — expected PASS.
Run: `composer test` — expected **240+ tests, 0 errors**. The 11 `CalendarSelectTest` errors are the gate and must now be green. If any remain, do not proceed.

- [ ] **Step 5: Lint and commit**

```bash
composer lint && composer analyse && composer parallel-lint
git add src/CalendarSelect.php tests/CalendarSelectRiteTest.php
git commit -m "Partition calendars by rite, fixing the lugano_ch crash

The nation pass assumed every diocese's nation owns a national calendar.
That holds within the Roman rite and nowhere else, so an Ambrosian
diocese reaching it took array_filter()[0] on an empty result and passed
null to a NationalCalendar parameter.

Filtering by rite first means it never reaches that code, and a rite with
no national tier skips the national pass entirely rather than rendering
an empty group. Closes the 11 errors the suite has been carrying."
```

---

### Task 5: Align `data-calendartype` with the JS library

**Files:**

- Modify: `src/CalendarSelect.php` — `addNationOption()` (line ~520), `addDioceseOption()` (line ~539)
- Test: `tests/CalendarSelectRiteTest.php` (append)

**Interfaces:** no new surface. Output-only change.

- [ ] **Step 1: Write the failing test**

```php
    public function testEmitsTheSameCalendarTypeValuesAsTheJsLibrary(): void
    {
        $html = ( new CalendarSelect(['locale' => 'en']) )->getSelect();

        $this->assertStringContainsString('data-calendartype="national"', $html);
        $this->assertStringContainsString('data-calendartype="diocesan"', $html);
        $this->assertStringNotContainsString('nationalcalendar', $html);
        $this->assertStringNotContainsString('diocesancalendar', $html);
    }
```

- [ ] **Step 2: Run the test and watch it fail**

Run: `composer test-filter testEmitsTheSameCalendarTypeValuesAsTheJsLibrary`
Expected: FAIL — the emitted values are still `nationalcalendar` / `diocesancalendar`.

- [ ] **Step 3: Change the two emissions**

`src/CalendarSelect.php` line ~520:

```php
        $optionOpenTag  = "<option data-calendartype=\"national\" value=\"{$nationalCalendar->calendarId}\"{$selectedStr}>";
```

line ~539:

```php
        $optionOpenTag  = "<option data-calendartype=\"diocesan\" value=\"{$diocesanCalendar->calendarId}\"{$selectedStr}>";
```

- [ ] **Step 4: Run the tests and watch them pass**

Run: `composer test`
Expected: PASS. If an existing test in `tests/CalendarSelectTest.php` asserts the old values, update it — that is the intended breaking change, not a regression.

- [ ] **Step 5: Lint and commit**

```bash
composer lint && composer analyse && composer parallel-lint
git add src/CalendarSelect.php tests/
git commit -m "Emit data-calendartype as national / diocesan

Matches liturgy-components-js. The library emits this attribute in two
places and reads it back nowhere, so this changes rendered output only —
which is precisely why nothing caught the drift: no test pinned it.

The API's own /calendar/nation/ and /calendar/diocese/ route segments are
a separate spelling and deliberately unchanged."
```

---

### Task 6: The rite gettext domain and the empty-option label

**Files:**

- Create: `src/i18n/rite.pot`
- Modify: `src/CalendarSelect.php` — bind the domain in `locale()`/constructor, and `getSelect()` (line ~688)
- Test: `tests/CalendarSelectRiteTest.php` (append)

**Interfaces:**

- Consumes: `Rite::emptyOptionLabel()` (Task 2).
- Produces: gettext domain `rite`, bound to `__DIR__ . '/i18n'`, containing five msgids: `Roman Rite`, `Ambrosian Rite`, `Select a rite`, `General Roman Calendar`, `Ambrosian
Calendar`.
- Task 7 binds the same domain. Follow `src/ApiOptions.php:261` for the `bindtextdomain` + failure-warning pattern.

- [ ] **Step 1: Write the failing test**

```php
    public function testEmptyOptionNamesTheRiteLevelCalendar(): void
    {
        $html = ( new CalendarSelect(['locale' => 'en', 'allowNull' => true]) )->getSelect();

        $this->assertStringContainsString('>General Roman Calendar</option>', $html);
        $this->assertStringNotContainsString('>---</option>', $html);
    }

    public function testEmptyOptionNamesTheAmbrosianCalendarUnderThatRite(): void
    {
        $html = ( new CalendarSelect(['locale' => 'en', 'rite' => 'ambrosian', 'allowNull' => true]) )->getSelect();

        $this->assertStringContainsString('>Ambrosian Calendar</option>', $html);
    }
```

- [ ] **Step 2: Run the test and watch it fail**

Run: `composer test-filter testEmptyOptionNames`
Expected: FAIL — the option still reads `---`.

- [ ] **Step 3: Create the catalog template**

Create `src/i18n/rite.pot` with the standard header (copy the one in `src/ApiOptions/i18n/litcompphp.pot`, changing `POT-Creation-Date`) followed by:

```text
#: src/Rite.php
msgid "General Roman Calendar"
msgstr ""

#: src/Rite.php
msgid "Ambrosian Calendar"
msgstr ""

#: src/RiteSelect.php
msgid "Roman Rite"
msgstr ""

#: src/RiteSelect.php
msgid "Ambrosian Rite"
msgstr ""

#: src/RiteSelect.php
msgid "Select a rite"
msgstr ""
```

English is the msgid; shipping English only is expected, and translations arrive through Weblate as they do for the other two domains.

- [ ] **Step 4: Bind the domain and use the label**

In `src/CalendarSelect.php`, add a private method and call it from the constructor after the locale is settled:

```php
    /**
     * Binds the `rite` gettext domain.
     *
     * CalendarSelect used no gettext at all before rite awareness — it
     * localized country names through \Locale::getDisplayRegion and nothing
     * else. The domain lives in src/i18n rather than a per-component directory
     * because RiteSelect renders the same five strings.
     */
    private function bindRiteTextDomain(): void
    {
        $expected = __DIR__ . '/i18n';
        $bound    = bindtextdomain('rite', $expected);
        if (false === $bound || $bound !== $expected) {
            trigger_error(
                "Failed to bind text domain. Expected path: {$expected}, got: {$bound}. Translations may not be available.",
                E_USER_WARNING
            );
        }
    }
```

In `getSelect()` (~688), replace:

```php
            $optionsHtml = "<option value=\"\">---</option>{$optionsHtml}";
```

with:

```php
            $emptyLabel  = dgettext('rite', $this->rite->emptyOptionLabel());
            $optionsHtml = "<option value=\"\">{$emptyLabel}</option>{$optionsHtml}";
```

`dgettext` is used rather than `_()` so the lookup names the domain explicitly and cannot be disturbed by whatever domain another component last set as current.

- [ ] **Step 5: Run the tests and watch them pass**

Run: `composer test`
Expected: PASS. Any existing test asserting `---` must be updated — intended breaking change.

- [ ] **Step 6: Lint and commit**

```bash
composer lint && composer analyse && composer parallel-lint
git add src/i18n src/CalendarSelect.php tests/
git commit -m "Name the rite-level calendar in the empty option

--- becomes General Roman Calendar or Ambrosian Calendar, localized.
Choosing neither a nation nor a diocese is not choosing nothing; it is
choosing the rite-level calendar, and the option now says so.

Adds the rite gettext domain. It lives in src/i18n rather than a
per-component directory because RiteSelect renders the same strings, and
uses dgettext so the lookup cannot be disturbed by whichever domain
another component last made current."
```

---

### Task 7: The `RiteSelect` component

**Files:**

- Create: `src/RiteSelect.php`
- Test: `tests/RiteSelectTest.php`

**Interfaces:**

- Consumes: `Rite` (Task 2), the `rite` gettext domain (Task 6).
- Produces: `RiteSelect::__construct(array $options = [])` accepting `locale`, `class`, `id`, `name`, `label`, `labelStr`, `labelClass`, `disabled`, `selectedOption`;
  chainable setters of the same names; `getSelect(): string`; `__toString(): string`.
- **No linking method.** There is nothing to link to server-side — see the spec's "What parity means here".

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace LiturgicalCalendar\Components\Tests;

use LiturgicalCalendar\Components\Rite;
use LiturgicalCalendar\Components\RiteSelect;
use PHPUnit\Framework\TestCase;

final class RiteSelectTest extends TestCase
{
    public function testRendersBothRitesInApiOrder(): void
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

    public function testSelectedOptionMarksThatRite(): void
    {
        $html = ( new RiteSelect(['locale' => 'en']) )->selectedOption(Rite::AMBROSIAN)->getSelect();

        $this->assertMatchesRegularExpression('/<option value="ambrosian"[^>]*selected/', $html);
    }

    public function testRejectsAnUnknownRite(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Invalid rite: byzantine/');
        ( new RiteSelect(['locale' => 'en']) )->selectedOption('byzantine');
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
}
```

- [ ] **Step 2: Run the test and watch it fail**

Run: `composer test-filter RiteSelectTest`
Expected: FAIL — `Class "LiturgicalCalendar\Components\RiteSelect" not found`.

- [ ] **Step 3: Write the component**

Create `src/RiteSelect.php`, following `src/CalendarSelect.php` for structure: private state, chainable setters that `htmlspecialchars` caller strings, a `getSelect()` that
assembles label + select, and `__toString()`. Bind the `rite` domain exactly as Task 6 did (`bindtextdomain('rite', __DIR__ . '/i18n')`), and translate with `dgettext('rite',
...)`:

```php
    /**
     * The option label for a rite, translated through the `rite` domain.
     */
    private function optionLabel(Rite $rite): string
    {
        return match ($rite) {
            Rite::ROMAN     => dgettext('rite', 'Roman Rite'),
            Rite::AMBROSIAN => dgettext('rite', 'Ambrosian Rite'),
        };
    }
```

The default label text is `dgettext('rite', 'Select a rite')`. Options render in `Rite::cases()` order, which is Roman then Ambrosian — the same order the JS `RiteSelect` uses.

`selectedOption(Rite|string $rite): self` validates a string through `Rite::tryFrom()` and throws `"Invalid rite: {$rite}, valid values are: roman, ambrosian"`, matching Task
3's message.

- [ ] **Step 4: Run the tests and watch them pass**

Run: `composer test-filter RiteSelectTest`
Expected: PASS, 7 tests. Then `composer test` — the whole suite green.

- [ ] **Step 5: Lint and commit**

```bash
composer lint && composer analyse && composer parallel-lint
git add src/RiteSelect.php tests/RiteSelectTest.php
git commit -m "Add the RiteSelect component

Renders the control and nothing more. There is no linking method because
there is nothing to link to server-side: this library renders once and
ships no JavaScript, so reacting to a change is the integrator's business
— a form submit, a query parameter, a re-render. The interactive version
lives in liturgy-components-js."
```

---

### Task 8: Documentation and the v4.0.0 release

**Files:**

- Modify: `README.md` — document `rite` / `rite()` on `CalendarSelect`, add a `RiteSelect` section
- Modify: `docs/superpowers/specs/2026-08-08-rite-awareness-design.md` — none; it is the record of the design, not of the outcome
- Create: nothing

- [ ] **Step 1: Document the two components**

Add `rite(Rite|string $rite)` to the `CalendarSelect` method list in `README.md`, and a `RiteSelect` section mirroring the `CalendarSelect` one: constructor options, chainable
setters, and a worked example. State plainly that there is no linking method and why.

- [ ] **Step 2: Verify the docs**

```bash
composer lint:md && composer format:md
```

Expected: 0 errors, "All matched files use Prettier code style!"

- [ ] **Step 3: Commit**

```bash
git add README.md
git commit -m "Document rite awareness and RiteSelect"
```

- [ ] **Step 4: Open the pull request**

```bash
git push -u origin feat/rite-awareness
gh pr create --title "Rite awareness: the Rite enum, rite-filtered calendars, and RiteSelect" --body "Closes #37. …"
```

The body must state the three breaking output changes explicitly, since none is opt-in:

1. `data-calendartype` values change from `nationalcalendar` / `diocesancalendar` to `national` / `diocesan`.
2. Ambrosian dioceses (`milano_it`, `bergam_it`, `novara_it`, `lugano_ch`) leave the default diocese list — a correction, since they never belonged in the Roman one, but visible
   to anyone relying on the old rite-unaware list.
3. The empty option's text changes from `---` to a calendar name.

- [ ] **Step 5: Tag and release after merge**

`composer.json` carries no `version` key and this repo has **no CHANGELOG.md**, so the release is a tag plus GitHub release notes:

```bash
git checkout main && git pull --ff-only
git tag -a v4.0.0 -m "v4.0.0"
git push origin v4.0.0
gh release create v4.0.0 --title "v4.0.0" --notes-file <notes> --latest
```

- [ ] **Step 6: Unblock the frontend**

`LiturgicalCalendarFrontend/usage.php:7` constructs this component on every request and is currently fatal. After the release, bump `liturgical-calendar/components` in that
repo's `composer.json` to `^4.0` and confirm `/usage` renders. That is the change that actually ends the outage — merging this PR alone does not.

---

## Self-Review

**Spec coverage:**

| Spec requirement                                  | Task      |
| ------------------------------------------------- | --------- |
| `Rite` enum + four properties                     | 2         |
| `rite` option and chainable setter                | 3         |
| Dioceses filtered by rite before the nation pass  | 4         |
| National tier skipped for a rite that has none    | 4         |
| `data-calendartype` → `national` / `diocesan`     | 5         |
| Empty option carries the rite-level calendar name | 6         |
| New gettext domain + `.pot`                       | 6         |
| `RiteSelect` component                            | 7         |
| Test: reproduce the crash first                   | 4, Step 1 |
| Test: rite filtering                              | 4         |
| Test: `data-calendartype`                         | 5         |
| Test: empty option label                          | 6         |
| Test: RiteSelect                                  | 7         |
| v4.0.0, breaking changes stated                   | 8         |

**Gap the spec did not cover:** the models drop `rite` and `ambrosian_calendars` entirely, so there was nothing to filter on. Task 1 exists to close that and is a prerequisite
for Task 4.

**Type consistency:** `DiocesanCalendar::$rite` is a `string` (wire format); `CalendarSelect::$rite` is a `Rite` (domain type); the comparison in Task 4 is
`$diocesanCalendar->rite === $this->rite->value`, string to string. `Rite::emptyOptionLabel()` returns an untranslated msgid, translated by its callers in Tasks 6 and 7 — it is
never returned pre-translated.
