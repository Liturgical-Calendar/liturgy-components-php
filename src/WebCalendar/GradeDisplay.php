<?php

namespace LiturgicalCalendar\Components\WebCalendar;

/**
 * Enum that represents how the liturgical grade should be displayed.
 *
 * If __GradeDisplay::FULL__, the grade is displayed with its full rank.
 * If __GradeDisplay::ABBREVIATED__, the grade is displayed with an abbreviated rank.
 *
 * The default is __GradeDisplay::FULL__.
 *
 * @package LiturgicalCalendar\Components\WebCalendar
 * @author John Romano Dorazio <priest@johnromanodorazio.com>
 */
enum GradeDisplay
{
    case FULL;
    case ABBREVIATED;
}
