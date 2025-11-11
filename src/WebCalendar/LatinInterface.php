<?php

namespace LiturgicalCalendar\Components\WebCalendar;

/**
 * An enumeration that indicates how to group the liturgical events.
 *
 * When the calendar is displayed in Latin, the days of the week can be shown in two ways:
 *  - LatinInterface::CIVIL: e.g. Dies Solis, Dies Lunae, Dies Martis, etc.
 *  - LatinInterface::ECCLESIASTICAL: e.g. Dominica, Feria Secunda, Feria Tertia, etc.
 *
 * @package LiturgicalCalendar\Components\WebCalendar
 * @author John Romano Dorazio <priest@johnromanodorazio.com>
 */
enum LatinInterface
{
    /**
     * When displayed in Latin, the calendar interface will use the civil days of the week (Dies Solis etc.).
     */
    case CIVIL;

    /**
     * When displayed in Latin, the calendar interface will use the ecclesiastical days of the week (Dominica etc.).
     */
    case ECCLESIASTICAL;

    public function dayOfTheWeekLatin(int $dayOfWeek): string
    {
        return match ($this) {
            self::CIVIL => match ($dayOfWeek) {
                0 => 'Dies Solis',
                1 => 'Dies Lunæ',
                2 => 'Dies Martis',
                3 => 'Dies Mercurii',
                4 => 'Dies Iovis',
                5 => 'Dies Veneris',
                6 => 'Dies Saturni',
                default => throw new \InvalidArgumentException("Invalid day of the week: $dayOfWeek"),
            },
            self::ECCLESIASTICAL => match ($dayOfWeek) {
                0 => 'Dominica',
                1 => 'Feria Secunda',
                2 => 'Feria Tertia',
                3 => 'Feria Quarta',
                4 => 'Feria Quinta',
                5 => 'Feria Sexta',
                6 => 'Sabbato',
                default => throw new \InvalidArgumentException("Invalid day of the week: $dayOfWeek"),
            },
        };
    }

    public function monthLatin(int $month): string
    {
        if ($month < 1 || $month > 12) {
            throw new \InvalidArgumentException("Invalid month: $month");
        }
        return match ($month) {
            1 => 'Ianuarius',
            2 => 'Februarius',
            3 => 'Martius',
            4 => 'Aprilis',
            5 => 'Maius',
            6 => 'Iunius',
            7 => 'Iulius',
            8 => 'Augustus',
            9 => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December',
            default => throw new \InvalidArgumentException("Invalid month: $month"),
        };
    }
}
