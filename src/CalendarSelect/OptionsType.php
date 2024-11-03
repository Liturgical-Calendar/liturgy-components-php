<?php

namespace LiturgicalCalendar\Components\CalendarSelect;

enum OptionsType: string
{
    case ALL = 'all';
    case DIOCESES = 'dioceses';
    case NATIONS = 'nations';
    case DIOCESES_FOR_NATION = 'dioceses_for_nation';
}
