<?php

namespace LiturgicalCalendar\Components\WebCalendar;

/**
 * Enum for specifying the date format for the date column of the table.
 *
 * Options are:
 * - __DateFormat::FULL__: The full date format for the locale, e.g. "Friday, March 3, 2023" or "venerdì 3 marzo 2023".
 * - __DateFormat::LONG__: The long date format for the locale, e.g. "March 3, 2023" or "3 marzo 2023".
 * - __DateFormat::MEDIUM__: The medium date format for the locale, e.g. "Mar 3, 2023" or "3 mar 2023".
 * - __DateFormat::SHORT__: The short date format for the locale, e.g. "3/3/23" or "03/03/23".
 * - __DateFormat::DAY_ONLY__: Only the day of the month and the weekday, e.g. "3 Friday" or "3 venerdì".
 *
 * The default is __DateFormat::FULL__.
 */
enum DateFormat
{
    case FULL;
    case LONG;
    case MEDIUM;
    case SHORT;
    case DAY_ONLY;
}
