<?php

namespace LiturgicalCalendar\Components\WebCalendar;

/**
 * An enumeration that indicates how to group the liturgical events.
 *
 * The grouping is that of the first column in the table of liturgical events.
 *
 */
enum Grouping
{
    /**
     * Group the liturgical events by month.
     */
    case BY_MONTH;

    /**
     * Group the liturgical events by liturgical season.
     */
    case BY_LITURGICAL_SEASON;
}
