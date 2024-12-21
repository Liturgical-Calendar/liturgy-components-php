<?php

namespace LiturgicalCalendar\Components\WebCalendar;

/**
 * Enum of columns that can be displayed in the WebCalendar table.
 *
 * Contains the following constants:
 * - {@see __LITURGICAL_SEASON__}: The column showing the liturgical season
 * - {@see __MONTH__}: The column showing the month
 * - {@see __DATE__}: The column showing the date
 * - {@see __EVENT__}: The column showing the event
 * - {@see __GRADE__}: The column showing the liturgical grade
 * - {@see __PSALTER_WEEK__}: The column showing the psalter week
 * - {@see __ALL__}: A bitfield of all the columns
 * - {@see __NONE__}: A bitfield of no columns
 *
 * @package LiturgicalCalendar\Components\WebCalendar
 * @author John Roman Dorazio <priest@johnromanodorazio.com>
 */
enum Column: int
{
    /**
     * The column showing the liturgical season
     * @var int 1
     */
    case LITURGICAL_SEASON  = 1 << 0;
    /**
     * The column showing the month
     * @var int 2
     */
    case MONTH              = 1 << 1;
    /**
     * The column showing the date
     * @var int 4
     */
    case DATE               = 1 << 2;
    /**
     * The column showing the event detauls
     * @var int 8
     */
    case EVENT              = 1 << 3;
    /**
     * The column showing the liturgical grade
     * @var int 16
     */
    case GRADE              = 1 << 4;
    /**
     * The column showing the psalter week
     * @var int 32
     */
    case PSALTER_WEEK       = 1 << 5;
    /**
     * A bitfield of all the columns
     * @var int 63
     */
    case ALL                = self::LITURGICAL_SEASON->value | self::MONTH->value | self::DATE->value | self::EVENT->value | self::GRADE->value | self::PSALTER_WEEK->value;
    /**
     * A bitfield of no columns
     * @var int 0
     */
    case NONE               = 0;

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
            return ($columnFlag & self::ALL->value) !== self::NONE->value;
        }
        return ($columnFlag->value & self::ALL->value) !== self::NONE->value;
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
        return ($combinedFlags & self::ALL->value) !== self::NONE->value;
    }
}
