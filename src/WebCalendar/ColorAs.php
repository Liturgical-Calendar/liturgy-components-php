<?php

namespace LiturgicalCalendar\Components\WebCalendar;

/**
 * Enum ColorAs
 *
 * Represents different ways to apply color in the Liturgical Calendar web component.
 * This enum provides options to specify color usage as a background, CSS class,
 * indicator, or none.
 *
 * @package LiturgicalCalendar\Components\WebCalendar
 * @author John Romano D'Orazio <priest@johnromanodorazio.com>
 */
enum ColorAs
{
    case BACKGROUND;
    case CSS_CLASS;
    case INDICATOR;
    case NONE;
}
