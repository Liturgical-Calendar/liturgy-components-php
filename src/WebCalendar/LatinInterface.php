<?php

namespace LiturgicalCalendar\Components\WebCalendar;

/**
 * An enumeration that controls Latin day and month naming conventions.
 *
 * When the calendar is displayed in Latin, the days of the week can be shown in two ways:
 *  - __LatinInterface::CIVIL__: e.g. _Dies Solis_, _Dies Lunae_, _Dies Martis_, etc.
 *  - __LatinInterface::ECCLESIASTICAL__: e.g. _Dominica_, _Feria Secunda_, _Feria Tertia_, etc.
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

    public function dayOfTheWeekLatinAbbr(int $dayOfWeek): string
    {
        return match ($this) {
            self::CIVIL => match ($dayOfWeek) {
                0 => 'Sol',
                1 => 'Lun',
                2 => 'Mar',
                3 => 'Mer',
                4 => 'Iov',
                5 => 'Ven',
                6 => 'Sat',
                default => throw new \InvalidArgumentException("Invalid day of the week: $dayOfWeek"),
            },
            self::ECCLESIASTICAL => match ($dayOfWeek) {
                0 => 'Dom',
                1 => 'Sec',
                2 => 'Ter',
                3 => 'Qua',
                4 => 'Qui',
                5 => 'Sex',
                6 => 'Sab',
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

    public function monthLatinAbbr(int $month): string
    {
        if ($month < 1 || $month > 12) {
            throw new \InvalidArgumentException("Invalid month: $month");
        }
        return match ($month) {
            1 => 'Ian',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Apr',
            5 => 'Mai',
            6 => 'Iun',
            7 => 'Iul',
            8 => 'Aug',
            9 => 'Sep',
            10 => 'Oct',
            11 => 'Nov',
            12 => 'Dec',
            default => throw new \InvalidArgumentException("Invalid month: $month"),
        };
    }

    public function formatDate(DateFormat $dateFmt, \DateTime $date): string
    {
        $dayOfTheWeek = (int) $date->format('w'); //w = 0-Sunday to 6-Saturday
        $dayOfTheWeekLatin = $this->dayOfTheWeekLatin($dayOfTheWeek);
        //$dayOfTheWeekLatinAbbr = $this->dayOfTheWeekLatinAbbr($dayOfTheWeek);
        $dayOfTheMonth = (int) $date->format('j');
        $month = (int) $date->format('n'); //n = 1-January to 12-December
        $monthLatin = $this->monthLatin($month);
        $monthLatinAbbr = $this->monthLatinAbbr($month);
        $yearFull = (int) $date->format('Y');
        $yearShort = (int) $date->format('y');
        return match ($dateFmt) {
            DateFormat::FULL => "$dayOfTheWeekLatin, $monthLatin $dayOfTheMonth, $yearFull",
            DateFormat::LONG => "$monthLatin $dayOfTheMonth, $yearFull",
            DateFormat::MEDIUM => "$monthLatinAbbr $dayOfTheMonth, $yearFull",
            DateFormat::SHORT => "$dayOfTheMonth/$month/$yearShort",
            DateFormat::DAY_ONLY => "$dayOfTheMonth $dayOfTheWeekLatin",
            default => throw new \InvalidArgumentException("Invalid date format: $dateFmt"),
        };
    }
}
