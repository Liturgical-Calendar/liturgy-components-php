<?php

namespace LiturgicalCalendar\Components\WebCalendar;

/**
 * Enum of columns that can be displayed in the WebCalendar table.
 *
 * Contains the following constants:
 * - __LITURGICAL_SEASON__: The column showing the liturgical season (1)
 * - __MONTH__: The column showing the month (2)
 * - __DATE__: The column showing the date (4)
 * - __EVENT__: The column showing the event (8)
 * - __GRADE__: The column showing the liturgical grade (16)
 * - __PSALTER_WEEK__: The column showing the psalter week (32)
 * - __ALL__: A bitfield of all the columns (63)
 * - __NONE__: A bitfield of no columns (0)
 *
 * @package LiturgicalCalendar\Components\WebCalendar
 * @author John Romano Dorazio <priest@johnromanodorazio.com>
 */
enum Column: int
{
    /** The column showing the liturgical season */
    case LITURGICAL_SEASON = 1 << 0; // 1

    /** The column showing the month */
    case MONTH = 1 << 1;             // 2

    /** The column showing the date */
    case DATE = 1 << 2;              // 4

    /** The column showing the event details */
    case EVENT = 1 << 3;             // 8

    /** The column showing the liturgical grade */
    case GRADE = 1 << 4;             // 16

    /** The column showing the psalter week */
    case PSALTER_WEEK = 1 << 5;      // 32

    /** A bitfield of all the columns (63) */
    case ALL = self::LITURGICAL_SEASON->value
              | self::MONTH->value
              | self::DATE->value
              | self::EVENT->value
              | self::GRADE->value
              | self::PSALTER_WEEK->value; // 63

    /** A bitfield of no columns (0) */
    case NONE = 0;                    // 0

    /**
     * Checks if any of the valid column flags are set in the provided bitfield.
     *
     * This function evaluates whether the given bitfield of column flags includes
     * at least one of the flags corresponding to the defined columns: LITURGICAL_SEASON,
     * MONTH, DATE, EVENT, GRADE, or PSALTER_WEEK.
     *
     * @param Column|int $columnFlag The bitfield of column flags to check.
     * @return bool True if any valid column flag is set, false otherwise.
     */
    public static function isValid(Column|int $columnFlag): bool
    {
        if (is_int($columnFlag)) {
            return ( $columnFlag & self::ALL->value ) !== self::NONE->value;
        }
        return ( $columnFlag->value & self::ALL->value ) !== self::NONE->value;
    }

    /**
     * Checks if all of the provided column flags are valid.
     *
     * This function evaluates whether each of the provided column flags is a valid
     * column flag, by ORing all of the values of the provided column flags into a single bitfield
     * and then evaluating the bitfield to check if it includes any of the valid column flags.
     *
     * @param Column ...$columnFlags The column flags to check.
     * @return bool True if all of the column flags are valid, false otherwise.
     */
    public static function areValid(Column ...$columnFlags): bool
    {
        $combinedFlags = 0;
        foreach ($columnFlags as $flag) {
            $combinedFlags |= $flag->value;
        }
        return ( $combinedFlags & self::ALL->value ) !== self::NONE->value;
    }
}
