<?php

namespace LiturgicalCalendar\Components\WebCalendar;

/**
 * Enum representing the order of columns in the WebCalendar table.
 *
 * Provides options for the order of the liturgical grade and event details columns.
 *
 * @package LiturgicalCalendar\Components\WebCalendar
 * @author John Roman Dorazio <priest@johnromanodorazio.com>
 */
enum ColumnOrder
{
    case GRADE_FIRST;
    case EVENT_DETAILS_FIRST;
}
