<?php

namespace LiturgicalCalendar\Components\WebCalendar;

/**
 * An enumeration that indicates how to group the liturgical events.
 *
 * The grouping is that of the first column in the table of liturgical events.
 *  - __Grouping::BY_MONTH__: the first column will contain month groupings
 *  - __Grouping::BY_LITURGICAL_SEASON__: the first column will contain liturgical season groupings
 *
 * @package LiturgicalCalendar\Components\WebCalendar
 * @author John Romano Dorazio <priest@johnromanodorazio.com>
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
