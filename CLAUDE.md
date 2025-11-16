# Liturgical Calendar Components - Claude Code Instructions

## Project Overview

This is a PHP library providing reusable frontend components for the Liturgical Calendar API. It includes:
- `CalendarSelect`: Dropdown components for selecting liturgical calendars
- `ApiOptions`: Form inputs for API request parameters
- `WebCalendar`: Display components for liturgical calendar data

## PHP Requirements

- **PHP Version**: >= 8.1 (required for Enum support)
- **Required Extensions**: ext-intl, ext-json
- **Autoloading**: PSR-4 autoloading via composer (`LiturgicalCalendar\Components`)

## Coding Standards

**CRITICAL**: All code must adhere to the phpcs.xml rules defined in this project.

### Core Standards
- **Base Standard**: PSR-12 with modifications
- **Array Syntax**: Use short array syntax `[]` (no `array()`)
- **Indentation**: 4 spaces (not tabs)
- **Array Indentation**: Single 4-space indent for key/value pairs
- **String Quotes**: Double quotes are allowed when not required (no need for single quotes)
- **Spacing**:
  - Space after cast operators
  - Space after spread operator `...`
  - 1 space inside arbitrary parentheses

### Excluded PSR-12 Rules
- Line length is NOT enforced
- Control structure spacing after closing brace is relaxed
- Function declaration argument spacing before equals is relaxed

### Markdown Standards
- **CRITICAL**: All markdown files must adhere to the .markdownlint.yml rules defined in this project
- Key rules include:
  - Maximum line length: 180 characters (excluding code blocks and tables)
  - Use fenced code blocks (triple backticks)
  - Ordered lists use consistent numbering style
  - Inline HTML is allowed for specific elements (img, a, b, table, etc.)

### Before Committing
Always run these commands to ensure code quality:

```bash
composer lint              # Check coding standards (phpcs)
composer lint:fix          # Auto-fix coding standards (phpcbf)
composer analyse           # Run static analysis (phpstan)
composer parallel-lint     # Check PHP syntax
composer test              # Run full test suite
composer test:quick        # Run tests excluding slow tests
```

## Project Structure

- `src/` - Main library code (analyzed by phpcs)
  - Component classes with chainable methods
  - Enum classes for type-safe options (PHP 8.1 feature)
- `tests/` - PHPUnit test suite
- `examples/` - Example implementations
- `vendor/` - Composer dependencies (excluded from phpcs)

## Key Patterns

1. **Chainable Methods**: Most component methods return `$this` for method chaining
2. **Enum Usage**: Use typed enums for options (e.g., `PathType`, `ColorAs`, `Grouping`)
3. **Locale Support**: Components support internationalization via PHP Intl extension
4. **API Integration**: Components consume JSON responses from Liturgical Calendar API

## When Making Changes

1. Maintain backward compatibility - this is a published Composer package
2. Follow existing patterns for method naming and structure
3. Update relevant tests when modifying component behavior
4. Ensure phpcs compliance before committing
5. Use type hints and return types (PHP 8.1+ features)
6. Document public methods with clear docblocks

## Quality Assurance

- **Pre-commit hooks**: Managed via CaptainHook
- **CI/CD**: Ensure all quality checks pass before creating pull requests
- **Code Coverage**: Maintain or improve test coverage with new features

## API Endpoint & Structure

Default API endpoint: `https://litcal.johnromanodorazio.com/api/dev/`
- Components are designed to work with this API structure
- Responses are expected in JSON format
- Locale support matches API's supported locales

### API Schemas

The components consume responses that conform to official JSON schemas maintained in the API repository:

**Schema References** (development branch):
- **OpenAPI Spec**: `https://raw.githubusercontent.com/Liturgical-Calendar/LiturgicalCalendarAPI/refs/heads/development/jsondata/schemas/openapi.json`
- **Common Definitions**: `https://raw.githubusercontent.com/Liturgical-Calendar/LiturgicalCalendarAPI/refs/heads/development/jsondata/schemas/CommonDef.json`
- **Calendar Metadata**: `https://raw.githubusercontent.com/Liturgical-Calendar/LiturgicalCalendarAPI/refs/heads/development/jsondata/schemas/LitCalMetadata.json`
- **Calendar Response**: `https://raw.githubusercontent.com/Liturgical-Calendar/LiturgicalCalendarAPI/refs/heads/development/jsondata/schemas/LitCal.json`

### Key API Endpoints

**CalendarSelect Component** works with:
- `/calendars` - Returns `LitCalMetadata` with:
  - `national_calendars[]` - Array of national calendar objects (calendar_id, locales, settings)
  - `diocesan_calendars[]` - Array of diocesan calendar objects (calendar_id, nation, group)
  - `locales[]` - Supported language locales
  - `diocesan_groups[]` - Groupings of dioceses
  - `wider_regions[]` - Regional definitions

**ApiOptions Component** parameters for:
- `/calendar` - General Roman Calendar
- `/calendar/{year}` - Specific year
- `/calendar/nation/{calendar_id}` - National calendars (IT, US, NL, VA, CA, etc.)
- `/calendar/diocese/{calendar_id}` - Diocesan calendars

**WebCalendar Component** consumes `/calendar*` responses with `LitCal` schema:
- `settings{}` - Calendar configuration (year, locale, epiphany, ascension, corpus_christi, eternal_high_priest)
- `metadata{}` - API version, timestamp, event counts by rank
- `litcal[]` - Array of liturgical events with:
  - `event_key` - Unique event identifier
  - `name` - Localized event name
  - `date` - ISO 8601 date (Unix timestamp)
  - `grade` - LitGrade enum (0-7: weekday to higher solemnity)
  - `color` - Array of LitColor values (white, red, green, purple, rose)
  - `liturgical_season` - Season name
  - `psalter_week` - Psalter week number (1-4)
  - `readings{}` - Lectionary readings structure (varies by celebration type)
  - Vigil masses include `is_vigil_mass: true` and `is_vigil_for` properties
- `messages[]` - Explanatory strings about calculations and decrees

### Important Type Definitions (from CommonDef.json)

- **LitGrade**: Integer 0-7 ranking (0=weekday, 1=commemoration, 2=optional memorial, 3=memorial, 4=feast, 5=feast of the Lord, 6=solemnity, 7=higher solemnity)
- **LitColor**: Array of strings (white, red, green, purple, rose)
- **LitCommon**: Classifications for Common of Saints
- **Locale**: IETF language codes (en, it, la, es, fr, de, pt, etc.)
- **Calendar**: Supported calendar regions (GeneralRoman, National, Diocesan)
- **CalendarSettings**: Mobile feast configurations (epiphany, ascension, corpus_christi, eternal_high_priest)
- **DiocesanCalendarId**: 9-character unique identifier format
- **Readings**: Multiple schema variants for different celebration types (Ferial, Festive, PalmSunday, EasterVigil, Christmas, etc.)

### Output Formats Supported by API

- `application/json` (default)
- `application/xml`
- `application/yaml`
- `text/calendar` (iCalendar format)

Components primarily work with JSON responses and should handle the data structures defined in these schemas.

## Common Tasks

- **Run specific test**: `composer test-filter ClassName::methodName`
- **Check platform requirements**: `composer check-platform-reqs`
- **Local testing**: See examples/webcalendar for local dev setup with .env.local

## Important Notes

- Never commit vendor/ directory
- Always test with both PHP 8.1 and latest PHP versions when possible
- Weblate is used for translations (ApiOptions and WebCalendar)
- Component output should be HTML strings, not echo'd directly
