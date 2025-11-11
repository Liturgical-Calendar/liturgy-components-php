<?php

namespace LiturgicalCalendar\Components\WebCalendar;

/**
 * Similar to the Liturgical Event class used in the Liturgical Calendar API,
 *  except that this class converts a PHP Timestamp to a DateTime object
 *  and does not implement JsonSerializeable or the comparator function
 * @author  John R. D'Orazio <priest@johnromanodorazio.com>
 * @package LiturgicalCalendar\Components\WebCalendar
 **/
class LiturgicalEvent
{
    public int $event_idx;
    public string $event_key;
    public string $name;
    public \DateTimeImmutable $date;
    public array $color;
    public array $color_lcl;
    public string $type;
    public int $grade;
    public string $grade_lcl;
    public string $grade_abbr;
    public ?string $grade_display;
    public array $common;
    public string $common_lcl;
    public string $liturgical_year;
    public string $liturgical_season;
    public string $liturgical_season_lcl;
    public int $psalter_week;
    public bool $is_vigil_mass;
    public string $is_vigil_for;

    /**
     * Construct a new LiturgicalEvent object from a given array or object.
     *
     * @param array|object $LitEvent An associative array or object with the following keys:
     *                               - 'event_idx': The unique index of the liturgical event.
     *                               - 'event_key': The unique ID of the liturgical event.
     *                               - 'name': The name of the liturgical event.
     *                               - 'date': The date of the liturgical event formatted in RFC 3339 (ISO 8601) format.
     *                               - 'color': The color of the liturgical event as an array of possible liturgical colors.
     *                               - 'color_lcl': The color of the liturgical event as an array of possible liturgical colors translated to the current locale.
     *                               - 'type': The type of the liturgical event, such as 'mobile' or 'fixed'.
     *                               - 'grade': The grade of the liturgical event:
     *                                          * 0 = WEEKDAY
     *                                          * 1 = COMMEMORATION
     *                                          * 2 = OPTIONAL MEMORIAL
     *                                          * 3 = MEMORIAL
     *                                          * 4 = FEAST
     *                                          * 5 = FEAST_LORD
     *                                          * 6 = SOLEMNITY
     *                                          * 7 = HIGHER_SOLEMNITY
     *                               - 'grade_lcl': The grade of the liturgical event translated to the current locale.
     *                               - 'grade_abbr': The abbreviated form of the liturgical grade.
     *                               - 'grade_display': If not null, the grade of the liturgical event as it should be displayed.
     *                               - 'common': The common of the liturgical event (if applicable).
     *                               - 'common_lcl': The common of the liturgical event translated to the current locale (if applicable).
     *                               - 'liturgical_year': The liturgical cycle (festive A, B, or C; or weekday I or II) of the liturgical event.
     *                               - 'liturgical_season': The liturgical season of the liturgical event.
     *                               - 'liturgical_season_lcl': The liturgical season of the liturgical event translated to the current locale.
     * @throws \Exception If the given array or object does not contain the required keys.
     */
    public function __construct(array|object $LitEvent)
    {
        if (is_array($LitEvent)) {
            $LitEvent = (object) $LitEvent;
        }
        $this->event_idx             = $LitEvent->event_idx;
        $this->event_key             = $LitEvent->event_key;
        $this->name                  = $LitEvent->name;
        $date                        = \DateTimeImmutable::createFromFormat(\DateTime::ATOM, $LitEvent->date);
        $this->color                 = $LitEvent->color;
        $this->color_lcl             = $LitEvent->color_lcl;
        $this->type                  = $LitEvent->type;
        $this->grade                 = $LitEvent->grade;
        $this->grade_lcl             = $LitEvent->grade_lcl;
        $this->grade_abbr            = $LitEvent->grade_abbr;
        $this->grade_display         = $LitEvent->grade_display;
        $this->common                = $LitEvent->common;
        $this->common_lcl            = $LitEvent->common_lcl;
        $this->liturgical_year       = $LitEvent->liturgical_year ?? '';
        $this->liturgical_season     = $LitEvent->liturgical_season;
        $this->liturgical_season_lcl = $LitEvent->liturgical_season_lcl;
        $this->psalter_week          = $LitEvent->psalter_week ?? 0;
        if (property_exists($LitEvent, 'is_vigil_mass')) {
            $this->is_vigil_mass = $LitEvent->is_vigil_mass;
        }
        if (property_exists($LitEvent, 'is_vigil_for')) {
            $this->is_vigil_for = $LitEvent->is_vigil_for;
        }
        if ($date === false) {
            throw new \Exception('Failed to parse date field. Expected RFC 3339 (ISO 8601) format, got: ' . $LitEvent->date);
        }
        $this->date = $date;
    }
}
