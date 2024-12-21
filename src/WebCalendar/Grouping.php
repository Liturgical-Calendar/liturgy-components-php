<?php

namespace LiturgicalCalendar\Components\WebCalendar;

/**
 * An enumeration that indicates how to group the liturgical events.
 *
 * The grouping is that of the first column in the table of liturgical events.
 *  - Grouping::BY_MONTH: the first column will contain month groupings
 * - Grouping::BY_LITURGICAL_SEASON: the first column will contain liturgical season groupings
 * @package LiturgicalCalendar\Components\WebCalendar
 * @author John Roman Dorazio <priest@johnromanodorazio.com>
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
