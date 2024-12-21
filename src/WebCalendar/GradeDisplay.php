<?php

namespace LiturgicalCalendar\Components\WebCalendar;

/**
 * Enum that represents how the liturgical grade should be displayed.
 *
 * If GradeDisplay::FULL, the grade is displayed with its full rank.
 * If GradeDisplay::ABBREVIATED, the grade is displayed with an abbreviated rank.
 *
 * The default is GradeDisplay::FULL.
 *
 * @package LiturgicalCalendar\Components\WebCalendar
 * @author John Roman Dorazio <priest@johnromanodorazio.com>
 */
enum GradeDisplay
{
    case FULL;
    case ABBREVIATED;
}
