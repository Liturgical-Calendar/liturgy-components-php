# Rite awareness for liturgy-components-php — design

Brings the PHP library to parity with liturgy-components-js on the liturgical rite: the `Rite` model,
rite-filtered calendar options, and a `RiteSelect` component. Fixing the `lugano_ch` crash falls out
of the same work.

## Problem

### The library crashes on live API data

`composer test` on `main` reports **240 tests, 11 errors**. All eleven share one root cause:

```text
src/CalendarSelect.php:504 — Undefined array key 0
TypeError: hasNationalCalendarWithDioceses(): Argument #1 ($item) must be of type
NationalCalendar, null given
```

Line 503 filters the national calendars for a diocese's nation and takes `[0]`. When that nation has
no national calendar the filter returns empty, `[0]` is undefined, and `null` reaches a typed
parameter. The Ambrosian diocese `lugano_ch` sits in nation `CH`, which has no national calendar, so
the crash fires against real metadata.

This is the exact analogue of the bug fixed in liturgy-components-js by `2a89aef`, "Fix crash when a
diocese's nation has no national calendar (e.g. lugano_ch/CH)".

The crash is not merely theoretical. LiturgicalCalendarFrontend removed the PHP `CalendarSelect` from
`usage.php` because of it, replacing it with the JS component; the in-code comment there records the
reason. The PHP `CalendarSelect` is currently instantiated nowhere in that frontend.

### The cause is a missing rite partition

The crash is a symptom, not the disease. The code assumes every diocese's nation has a national
calendar. That holds **within the Roman rite** and nowhere else: the Ambrosian rite has no national
tier at all. `grep -rl "rite" src/` returns nothing — the library has no concept of a rite.

So the fix and the feature are one piece of work. Filtering dioceses by rite before the nation pass
means `lugano_ch` never reaches the code that assumes a national calendar.

### Two further divergences from the JS library

| Concern              | liturgy-components-js                          | liturgy-components-php              |
| -------------------- | ---------------------------------------------- | ----------------------------------- |
| `data-calendartype`  | `national` / `diocesan`                        | `nationalcalendar` / `diocesancalendar` |
| Empty option label   | the rite-level calendar name                   | a literal `---`                     |

A third spelling exists and is **not** in scope to change: the API's own URL segments are `nation`
and `diocese` (`/calendar/nation/IT`). That is the API's route naming, not a component convention.

## What parity means here

The PHP library renders once, server-side. It ships no JavaScript and strips `<script>` from
caller-supplied text. JS's `linkToRiteSelect()` is a runtime DOM listener and has no server-side
analogue.

Parity therefore means **rite-correct output**, not interactivity. A `RiteSelect` renders the control;
reacting to a change is the integrator's business — a form submit, a query parameter, a re-render.
Anything interactive belongs in liturgy-components-js, which is what the frontend already reaches for.

## The rite model

A `Rite` enum with the same two cases as JS, and the same structural facts attached. These are
properties of the rite, not of a diocese and not user preferences.

| Property                  | Roman | Ambrosian | Meaning                                                        |
| ------------------------- | ----- | --------- | -------------------------------------------------------------- |
| `hasNationalTier`         | true  | false     | whether the rite has national calendars at all                  |
| `hasFixedTemporalOptions` | false | true      | whether the rite fixes Epiphany, Ascension, Corpus Christi, EHP |
| `minYear`                 | 1970  | 1976      | earliest year the API will serve for the rite                   |
| `emptyOptionLabelKey`     | —     | —         | which label the empty option carries                            |

PHP 8.1 backed enums fit this better than the frozen object JS uses. The enum carries the string value
(`roman`, `ambrosian`) and exposes the properties through a method rather than a parallel constant, so
there is one place to look.

Values must match the JS enum and the API exactly: `roman`, `ambrosian`.

## CalendarSelect

**A `rite` option**, validated the way `nationFilter` already is, plus a chainable `rite()` setter
matching the library's existing style. Defaults to Roman, which preserves today's behaviour for the
national tier.

**Dioceses filtered by rite before the nation pass.** This is the crash fix: an Ambrosian diocese is
partitioned out before any code asks which national calendar its nation has.

**No national tier for a rite that has none.** Under the Ambrosian rite the national pass is skipped
entirely rather than producing an empty group.

**`data-calendartype` becomes `national` / `diocesan`**, matching JS. The PHP library never reads the
attribute back — it emits it in two places and consumes it nowhere — so this is a change to output
only.

**The empty option carries the rite-level calendar name.** `---` becomes "General Roman Calendar" or
"Ambrosian Calendar", localized, which is what `emptyOptionLabelKey` selects and what JS already does.

## RiteSelect

A new component following the conventions the other components already establish: `class`, `id`,
`name`, `label`, `labelText`, `labelClass`, `wrapper`, `disabled`, `selectedOption`, all chainable,
with `__toString()` for rendering.

No linking method. There is nothing to link to server-side.

## Internationalization

The two libraries localize by entirely different means, and this is the least mechanical part of the
work.

JS keeps a `Messages.js` object keyed by locale. PHP uses **gettext** with per-component domains and
`.po`/`.mo` catalogs — `litcompphp` for ApiOptions, `webcalendar` for WebCalendar. `CalendarSelect`
uses **no gettext at all** today; it localizes country names through `\Locale::getDisplayRegion` and
nothing else.

So the rite strings need a **new gettext domain** with its own `.pot`, covering:

- `Roman Rite`, `Ambrosian Rite` — the RiteSelect options
- `Select a rite` — its default label
- `General Roman Calendar`, `Ambrosian Calendar` — the empty option labels

Translations go through Weblate as the project already does for the other domains. English is the
msgid; shipping only English initially is acceptable and consistent with how the JS library seeded
these same strings.

## Breaking changes

Three visible output changes, none of them opt-in:

1. `data-calendartype` values change.
2. Ambrosian dioceses (`milano_it`, `bergam_it`, `novara_it`, `lugano_ch`) no longer appear in the
   default diocese list, because they belong to the Ambrosian rite rather than the Roman one. This is
   a correction — they never belonged there — but it is a visible change for any integrator relying
   on the old rite-unaware list.
3. The empty option's text changes from `---` to a calendar name.

The library is at **v3.3.1**. `data-calendartype` is emitted markup that a third party may read, and
all three changes alter rendered output without a flag to opt out, so this warrants **v4.0.0** rather
than a minor with a note.

## Testing

The suite is red before this work starts, so the first move is to make the failure explicit rather
than to fix it silently.

1. **Reproduce.** A test that constructs a `CalendarSelect` over metadata containing `lugano_ch` and
   asserts it renders, failing with the current `TypeError`. The eleven existing errors turning green
   is the gate for the fix.
2. **Rite filtering.** Ambrosian dioceses absent under Roman and present under Ambrosian; the national
   tier skipped for a rite that has none.
3. **`data-calendartype`.** Assert the emitted values. No test pins them today, which is how the two
   libraries drifted apart unnoticed.
4. **Empty option label.** Assert the rite-appropriate name in at least two locales.
5. **RiteSelect.** Rendering, the chainable setters, `selectedOption`, and validation of an unknown
   rite.

## Out of scope

Each of these is its own sub-project, independently shippable, and none blocks this one:

- **`linkToRiteSelect()`** — requires client-side behaviour the library deliberately does not have.
- **`PathBuilder`** — absent from PHP, unrelated to rites.
- **`LiturgyOfTheDay` / `LiturgyOfAnyDay`** — absent from PHP, the largest remaining gap.
- **`Utils`** — absent from PHP.

The richer HTTP stack, `Cache`, `MetadataProvider` and typed `Models` that PHP has and JS does not are
not divergences to correct. Server-side and browser-side libraries legitimately differ there, and
parity is about the component surface.
