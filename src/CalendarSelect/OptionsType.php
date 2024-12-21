<?php

namespace LiturgicalCalendar\Components\CalendarSelect;

/**
 * Enum for the options type.
 *
 * This enum is used to specify the options for the CalendarSelect component.
 *
 * @package LiturgicalCalendar\Components\CalendarSelect
 * @author John Romano D'Orazio <priest@johnromanodorazio.com>
 */
enum OptionsType: string
{
    case ALL = 'all';
    case DIOCESES = 'dioceses';
    case NATIONS = 'nations';
    case DIOCESES_FOR_NATION = 'dioceses_for_nation';
}
