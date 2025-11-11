<?php

namespace LiturgicalCalendar\Components;

use LiturgicalCalendar\Components\WebCalendar\LiturgicalEvent;
use LiturgicalCalendar\Components\WebCalendar\Grouping;
use LiturgicalCalendar\Components\WebCalendar\ColorAs;
use LiturgicalCalendar\Components\WebCalendar\Column;
use LiturgicalCalendar\Components\WebCalendar\ColumnOrder;
use LiturgicalCalendar\Components\WebCalendar\ColumnSet;
use LiturgicalCalendar\Components\WebCalendar\DateFormat;
use LiturgicalCalendar\Components\WebCalendar\GradeDisplay;
use LiturgicalCalendar\Components\WebCalendar\LatinInterface;

/**
 * A class to generate a table of liturgical events for a given Liturgical Calendar.
 *
 * The class takes an object from the Liturgical Calendar API as a parameter in its constructor.
 * The object should have the following properties:
 * - __litcal__: a collection of liturgical event objects with date, liturgical color, liturgical common, and liturgical grade properties
 * - __settings__: the settings object from the Liturgical Calendar API
 * - __metadata__: the metadata object from the Liturgical Calendar API
 * - __messages__: the messages object from the Liturgical Calendar API
 *
 * The class provides the following methods:
 * - {@see \LiturgicalCalendar\Components\WebCalendar::id()}  Sets the id of the table element.
 * - {@see \LiturgicalCalendar\Components\WebCalendar::class()} Sets the class of the table element.
 * - {@see \LiturgicalCalendar\Components\WebCalendar::firstColumnGrouping()} Sets the grouping of the first column of the table.
 * - {@see \LiturgicalCalendar\Components\WebCalendar::psalterWeekGrouping()} Sets whether to display grouped psalter weeks.
 * - {@see \LiturgicalCalendar\Components\WebCalendar::removeHeaderRow()} Sets whether to remove the header row of the table.
 * - {@see \LiturgicalCalendar\Components\WebCalendar::removeCaption()} Sets whether to remove the caption of the table.
 * - {@see \LiturgicalCalendar\Components\WebCalendar::dateFormat()} Sets the date format for the date column.
 * - {@see \LiturgicalCalendar\Components\WebCalendar::monthHeader()} Sets whether to display month headers.
 * - {@see \LiturgicalCalendar\Components\WebCalendar::seasonColor()} Sets how the season color is handled (background color, CSS class, or inline block element).
 * - {@see \LiturgicalCalendar\Components\WebCalendar::eventColor()} Sets how the event color is handled (background color, CSS class, or inline block element).
 * - {@see \LiturgicalCalendar\Components\WebCalendar::seasonColorColumns()} Sets which columns to apply the season color to.
 * - {@see \LiturgicalCalendar\Components\WebCalendar::eventColorColumns()} Sets which columns to apply the event color to.
 * - {@see \LiturgicalCalendar\Components\WebCalendar::columnOrder()} Sets the order of the third and fourth columns in the table (liturgical grade and event details).
 * - {@see \LiturgicalCalendar\Components\WebCalendar::gradeDisplay()} Sets how the liturgical grade is displayed (in full, or in abbreviated form).
 * - {@see \LiturgicalCalendar\Components\WebCalendar::getLocale()} Returns the locale that was set when the WebCalendar object was created / buildTable was called.
 * - {@see \LiturgicalCalendar\Components\WebCalendar::buildTable()} Returns an HTML string containing a table of the liturgical events.
 * - {@see \LiturgicalCalendar\Components\WebCalendar::daysCreated()} Returns the number of days created in the table.
 *
 * @author John Romano Dorazio <priest@johnromanodorazio.com>
 * @package LiturgicalCalendar\Components
 */
class WebCalendar
{
    private string $locale                  = 'en-US';
    private string $baseLocale              = 'en';
    private ?string $currentSetLocale       = null;
    private ?string $globalLocale           = null;
    private ?string $currentTextDomainPath  = null;
    private ?string $expectedTextDomainPath = null;
    private ?object $LiturgicalCalendar     = null;
    private ?string $class                  = null;
    private ?string $id                     = null;
    private int $daysCreated                = 0;
    private Grouping $firstColumnGrouping   = Grouping::BY_MONTH;
    private ColorAs $eventColor             = ColorAs::INDICATOR;
    private ColorAs $seasonColor            = ColorAs::BACKGROUND;
    private ColumnSet $seasonColorColumns;
    private ColumnSet $eventColorColumns;
    private ColumnOrder $columnOrder        = ColumnOrder::EVENT_DETAILS_FIRST;
    private DateFormat $dateFormat          = DateFormat::FULL;
    private GradeDisplay $gradeDisplay      = GradeDisplay::FULL;
    private LatinInterface $latinInterface  = LatinInterface::ECCLESIASTICAL;
    private bool $removeHeaderRow           = false;
    private bool $removeCaption             = false;
    private bool $psalterWeekGrouping       = false;
    private bool $monthHeader               = false;
    private \DomDocument $dom;
    private ?\DomElement $lastSeasonCell      = null;
    private ?\DomElement $lastPsalterWeekCell = null;
    private const HIGH_CONTRAST             = ['purple', 'red', 'green'];


    /**
     * An array of Roman numerals to translate the psalter week cycle
     * from arabic numerals to Roman numerals
     */
    private const PSALTER_WEEK = [
        '', 'I', 'II', 'III', 'IV'
    ];

    /**
     * Constructs a new WebCalendar object.
     *
     * This constructor initializes the WebCalendar instance by validating
     * the provided Liturgical Calendar object or array, which must contain specific properties or keys.
     * If the provided Liturgical Calendar is an array, it will cast it to an object.
     * It ensures that 'litcal', 'settings', 'metadata', 'messages', and
     * 'locale' properties exist in the provided object (or resulting object if an array was passed).
     * Each item in the 'litcal' object is converted to a LiturgicalEvent object.
     *
     * @param object|array $LiturgicalCalendar The object or associative array containing the
     *                                  Liturgical Calendar data with required properties or keys.
     *
     * @throws \Exception If any of the required properties or keys are missing from the object or array.
     */
    public function __construct(object|array $LiturgicalCalendar)
    {
        if (is_array($LiturgicalCalendar)) {
            $LiturgicalCalendar = json_decode(json_encode($LiturgicalCalendar));
        }

        if (false === property_exists($LiturgicalCalendar, 'litcal')) {
            throw new \Exception("The Liturgical Calendar object or array must contain the property or key 'litcal'.");
        }
        if (false === property_exists($LiturgicalCalendar, 'settings')) {
            throw new \Exception("The Liturgical Calendar object or array must contain the property or key 'settings'.");
        }
        if (false === property_exists($LiturgicalCalendar, 'metadata')) {
            throw new \Exception("The Liturgical Calendar object or array must contain the property or key 'metadata'.");
        }
        if (false === property_exists($LiturgicalCalendar, 'messages')) {
            throw new \Exception("The Liturgical Calendar object or array must contain the property or key 'messages'.");
        }
        if (false === property_exists($LiturgicalCalendar->settings, 'locale')) {
            throw new \Exception("The Liturgical Calendar 'settings' object or array must contain the property or key 'locale'.");
        }

        foreach ($LiturgicalCalendar->litcal as $idx => $value) {
            $LiturgicalCalendar->litcal[$idx] = new LiturgicalEvent($value);
        }

        //header('Content-Type: application/json');
        //die(json_encode($LiturgicalCalendar));
        $this->LiturgicalCalendar = $LiturgicalCalendar;
        $this->seasonColorColumns = new ColumnSet(Column::LITURGICAL_SEASON->value | Column::MONTH->value | Column::DATE->value | Column::PSALTER_WEEK->value);
        $this->eventColorColumns = new ColumnSet(Column::EVENT->value | Column::GRADE->value);
        $this->latinInterface = LatinInterface::ECCLESIASTICAL;
        $this->dom = new \DomDocument();
    }

    /**
     * Sets the class for the component.
     *
     * The class is used in the DOM element representing the component.
     *
     * @param string $class The class to set for the component.
     *
     * @return $this The current instance of the class.
     */
    public function class(string $class): self
    {
        $this->class = $class;
        return $this;
    }

    /**
     * Sets the ID for the component.
     *
     * The ID is used in the DOM element representing the component.
     * @param string $id The ID string to be used.
     * @return $this
     */
    public function id(string $id): self
    {
        $this->id = $id;
        return $this;
    }


    /**
     * Sets the grouping for the first column.
     *
     * @param Grouping $firstColumnGrouping The grouping to use for the first column. Two cases are possible:
     *                                       - Grouping::BY_MONTH
     *                                       - Grouping::BY_LITURGICAL_SEASON
     *
     * @return $this The current instance of the class.
     */
    public function firstColumnGrouping(Grouping $firstColumnGrouping): self
    {
        $this->firstColumnGrouping = $firstColumnGrouping;
        return $this;
    }

    /**
     * Sets whether or not the header row should be removed from the table.
     *
     * This method controls whether or not the header row is
     * generated by the {@see buildTable()} method. The default is true, meaning
     * that the header row should not be generated (= should be removed).
     *
     * @param bool $removeHeaderRow Whether the header row should be removed or not.
     *
     * @return $this The current instance of the class.
     */
    public function removeHeaderRow(bool $removeHeaderRow = true): self
    {
        $this->removeHeaderRow = $removeHeaderRow;
        return $this;
    }

    /**
     * Sets whether or not the caption should be removed from the table.
     *
     * This method controls whether or not the caption element is
     * generated by the {@see buildTable()} method. The default is true, meaning
     * that the caption should not be generated (= should be removed).
     *
     * @param bool $removeCaption Whether the caption should be removed or not.
     *
     * @return $this The current instance of the class.
     */
    public function removeCaption(bool $removeCaption = true): self
    {
        $this->removeCaption = $removeCaption;
        return $this;
    }

    /**
     * Sets whether or not the events should be grouped by psalter week.
     *
     * This method controls the grouping of events by psalter week in the
     * generated table, as part of the {@see buildTable()} method.
     * The default is true, meaning that the grouping should be applied.
     *
     * @param bool $psalterWeekGrouping Whether the psalter week grouping should be applied or not.
     *
     * @return $this The current instance of the class.
     */
    public function psalterWeekGrouping(bool $psalterWeekGrouping = true): self
    {
        $this->psalterWeekGrouping = $psalterWeekGrouping;
        return $this;
    }

    /**
     * Sets the color representation for the liturgical events in the table.
     *
     * This method controls how the color of a liturgical event is represented in the table,
     * as part of the {@see buildTable()} method.
     * The default is ColorAs::INDICATOR, meaning that the color of the event is represented as a 10px inline block element with radius 5px.
     *
     * @param ColorAs $colorAs The color representation to use for the events.
     *
     * @return $this The current instance of the class.
     */
    public function eventColor(ColorAs $colorAs): self
    {
        $this->eventColor = $colorAs;
        return $this;
    }

    /**
     * Sets the color representation for the liturgical seasons in the table.
     *
     * This method controls how the color of a liturgical season is represented in the table,
     * as part of the {@see buildTable()} method.
     * The color can be represented in various ways, such as a background color, a CSS class, or an indicator element.
     * The default is ColorAs::BACKGROUND, meaning that the color of the season is represented as a background color.
     * ColorAs::INDICATOR means that the color of the season is represented as a 10px inline block element with radius 5px.
     *
     * @param ColorAs $colorAs The color representation to use for the seasons.
     *
     * @return $this The current instance of the class.
     */
    public function seasonColor(ColorAs $colorAs): self
    {
        $this->seasonColor = $colorAs;
        return $this;
    }

    /**
     * Sets the column flags that determine which columns will have seasonal colors applied.
     *
     * This method sets the `seasonColorColumns` property based on the provided
     * column flags, allowing the user to specify which columns should have
     * seasonal colors applied to them in the calendar table.
     *
     * @param Column|int $columnsFlag The Column or bitfield of column flags indicating which columns will have seasonal colors.
     * @return $this The current instance of the class.
     * @throws \InvalidArgumentException If an integer bitfield is provided and represents invalid column flags.
     */
    public function seasonColorColumns(Column|int $columnsFlag = Column::NONE): self
    {
        $this->seasonColorColumns->set($columnsFlag);
        return $this;
    }

    /**
     * Sets the column flags that determine which columns will have event colors applied.
     *
     * This method sets the `eventColorColumns` property based on the provided
     * column flags, allowing the user to specify which columns should have
     * event colors applied to them in the calendar table.
     *
     * @param Column|int $columnsFlag The Column or bitfield of column flags indicating which columns will have event colors.
     * @return $this The current instance of the class.
     * @throws \InvalidArgumentException If an integer bitfield is provided and represent invalid column flags.
     */
    public function eventColorColumns(Column|int $columnsFlag = Column::NONE): self
    {
        $this->eventColorColumns->set($columnsFlag);
        return $this;
    }

    /**
     * Controls whether or not month headers are displayed in the table.
     *
     * This method allows the user to specify whether or not to include month headers
     * in the calendar table.
     * The default is true, meaning that month headers should be included.
     *
     * @param bool $monthHeader Whether or not to include month headers in the table.
     *
     * @return $this The current instance of the class.
     */
    public function monthHeader(bool $monthHeader = true): self
    {
        $this->monthHeader = $monthHeader;
        return $this;
    }

    /**
     * Sets the date format for the table.
     *
     * This method sets the format for the dates in the table. The following formats are supported:
     * - FULL: The full date format for the locale, e.g. "Friday, March 3, 2023" or "venerdì 3 marzo 2023".
     * - LONG: The long date format for the locale, e.g. "March 3, 2023" or "3 marzo 2023".
     * - MEDIUM: The medium date format for the locale, e.g. "Mar 3, 2023" or "3 mar 2023".
     * - SHORT: The short date format for the locale, e.g. "3/3/23" or "03/03/23".
     * - DAY_ONLY: Only the day of the month and the weekday, e.g. "3 Friday" or "3 venerdì".
     *
     * The default is DateFormat::FULL.
     *
     * @param DateFormat $dateFormat The date format to use.
     *
     * @return $this The current instance of the class.
     */
    public function dateFormat(DateFormat $dateFormat = DateFormat::FULL): self
    {
        $this->dateFormat = $dateFormat;
        return $this;
    }

    /**
     * Controls the order of the columns.
     *
     * This method allows the user to control the order of the columns in the table.
     * The default is ColumnOrder::GRADE_FIRST, meaning that the grade column is first, followed by the event details.
     * If the user wants the event details to come first, followed by the grade column, they can use ColumnOrder::EVENT_DETAILS_FIRST.
     *
     * The default is ColumnOrder::EVENT_DETAILS_FIRST.
     *
     * @param ColumnOrder $columnOrder The order of the columns.
     *
     * @return $this The current instance of the class.
     */
    public function columnOrder(ColumnOrder $columnOrder = ColumnOrder::EVENT_DETAILS_FIRST): self
    {
        $this->columnOrder = $columnOrder;
        return $this;
    }

    /**
     * Controls how the liturgical grade is displayed.
     *
     * If GradeDisplay::FULL, the grade is displayed with its full rank.
     * If GradeDisplay::ABBREVIATED, the grade is displayed with an abbreviated rank.
     *
     * The default is GradeDisplay::FULL.
     *
     * @param GradeDisplay $gradeDisplay The grade display.
     *
     * @return $this The current instance of the class.
     */
    public function gradeDisplay(GradeDisplay $gradeDisplay = GradeDisplay::FULL): self
    {
        $this->gradeDisplay = $gradeDisplay;
        return $this;
    }

    public function latinInterface(LatinInterface $latinInterface = LatinInterface::ECCLESIASTICAL): self
    {
        $this->latinInterface = $latinInterface;
        return $this;
    }

    /**
     * Sets the locale for the component, for the translations of frontend strings and date information.
     * Called internally by the public method {@see buildTable()}.
     * The locale is set to that of the liturgical calendar that was requested.
     *
     *    1. The global locale is retrieved using `setlocale(LC_ALL, 0)`, so that it can be later restored.
     *    2. The locale for the component is set to the parameter `$locale`, which is passed in by the buildTable method based on the Liturgical Calendar response data.
     *    3. The base locale is set using `Locale::getPrimaryLanguage($locale)`.
     *    4. An array of possible locale strings is created, with the following order of preference:
     *        * The locale string with '.utf8' appended.
     *        * The locale string with '.UTF-8' appended.
     *        * The locale string without any suffix.
     *        * The base locale string with '_' followed by its uppercase version and '.utf8' appended.
     *        * The base locale string with '_' followed by its uppercase version and '.UTF-8' appended.
     *        * The base locale string with '_' followed by its uppercase version without any suffix.
     *        * The base locale string with '.utf8' appended.
     *        * The base locale string with '.UTF-8' appended.
     *        * The base locale string without any suffix.
     *    5. The locale is set to the first of the above that is supported by the system using `setlocale(LC_ALL, $localeArray)`.
     *    6. The path to the textdomain is set to `__DIR__ . "/WebCalendar/i18n"` and bound to the textdomain __webcalendar__.
     *    7. If the textdomain is not bound to the expected path, it will die with an error message.
     *
     * @param string $locale The locale string to be used.
     */
    private function setLocale($locale)
    {
        $this->globalLocale = setlocale(LC_ALL, 0);
        $this->locale = $locale;
        $this->baseLocale = \Locale::getPrimaryLanguage($locale);
        $localeArray = [
            $this->locale . '.utf8',
            $this->locale . '.UTF-8',
            $this->locale,
            $this->baseLocale . '_' . strtoupper($this->baseLocale) . '.utf8',
            $this->baseLocale . '_' . strtoupper($this->baseLocale) . '.UTF-8',
            $this->baseLocale . '_' . strtoupper($this->baseLocale),
            $this->baseLocale . '.utf8',
            $this->baseLocale . '.UTF-8',
            $this->baseLocale
        ];
        $this->currentSetLocale = setlocale(LC_ALL, $localeArray);
        $this->expectedTextDomainPath = __DIR__ . "/WebCalendar/i18n";
        $this->currentTextDomainPath = bindtextdomain("webcalendar", $this->expectedTextDomainPath);
        if ($this->currentTextDomainPath !== $this->expectedTextDomainPath) {
            die("Failed to bind text domain, expected path: {$this->expectedTextDomainPath}, current path: {$this->currentTextDomainPath}");
        }
    }

    /**
     * Resets the global locale back to the original locale that was set before we started tampering with it.
     *
     * This is necessary because the locale is a global setting and we don't want to mess up the locale for other PHP scripts
     * that may be running on the same system.
     */
    private function resetGlobalLocale()
    {
        setlocale(LC_ALL, $this->globalLocale);
    }

    /**
     * Recursively counts the number of subsequent liturgical events in the same day.
     *
     * @param int $currentEventIdx The current position in the array of liturgical events on the given day.
     * @param int $cd [reference] The count of subsequent liturgical events in the same day.
     */
    private function countSameDayEvents(int $currentEventIdx, int &$cd)
    {
        $EventsObject = $this->LiturgicalCalendar->litcal;
        $currentEvent = $EventsObject[$currentEventIdx];
        $nextEventIdx = $currentEventIdx + 1;
        if ($nextEventIdx < count($EventsObject) ) {
            $nextEvent = $EventsObject[$nextEventIdx];
            // date->format('U'): Seconds since the Unix Epoch (January 1 1970 00:00:00 GMT) aka timestamp
            if ($nextEvent->date->format('U') === $currentEvent->date->format('U')) {
                $cd++;
                $this->countSameDayEvents($nextEventIdx, $cd);
            }
        }
    }

    /**
     * Recursively counts the number of subsequent liturgical events in the same month.
     *
     * @param int $currentEventIdx The current position in the array of liturgical events on the given month.
     * @param int $cm [reference] The count of subsequent liturgical events in the same month.
     */
    private function countSameMonthEvents(int $currentEventIdx, int &$cm)
    {
        $EventsObject = $this->LiturgicalCalendar->litcal;
        $currentEvent = $EventsObject[$currentEventIdx];
        $nextEventIdx = $currentEventIdx + 1;
        if ($nextEventIdx < count($EventsObject)) {
            $nextEvent = $EventsObject[$nextEventIdx];
            // date->format('n'): Numeric representation of a month, without leading zeros, 1-12
            if ($nextEvent->date->format('n') === $currentEvent->date->format('n')) {
                $cm++;
                $this->countSameMonthEvents($nextEventIdx, $cm);
            }
        }
    }

    /**
     * Recursively counts the number of subsequent liturgical events in the same liturgical season.
     *
     * @param int $currentEventIdx The current position in the array of liturgical events on the given liturgical season.
     * @param int $cs [reference] The count of subsequent liturgical events in the same liturgical season.
     */
    private function countSameSeasonEvents(int $currentEventIdx, int &$cs)
    {
        $EventsObject = $this->LiturgicalCalendar->litcal;
        $currentEvent = $EventsObject[$currentEventIdx];
        $nextEventIdx = $currentEventIdx + 1;
        if ($nextEventIdx < count($EventsObject)) {
            $nextEvent = $EventsObject[$nextEventIdx];
            $currentEventLiturgicalSeason = $currentEvent->liturgical_season ?? $this->determineSeason($currentEvent);
            $nextEventLiturgicalSeason = $nextEvent->liturgical_season ?? $this->determineSeason($nextEvent);
            if ($nextEventLiturgicalSeason === $currentEventLiturgicalSeason) {
                $cs++;
                $this->countSameSeasonEvents($nextEventIdx, $cs);
            }
        }
    }

    /**
     * Recursively counts the number of subsequent liturgical events in the same psalter week.
     *
     * @param int $currentEventIdx The current position in the array of liturgical events on the given psalter week.
     * @param int $cw [reference] The count of subsequent liturgical events in the same psalter week.
     */
    private function countSamePsalterWeekEvents(int $currentEventIdx, int &$cw)
    {
        $EventsObject = $this->LiturgicalCalendar->litcal;
        $currentEvent = $EventsObject[$currentEventIdx];
        $nextEventIdx = $currentEventIdx + 1;
        if ($nextEventIdx < count($EventsObject)) {
            $nextEvent = $EventsObject[$nextEventIdx];
            // $cepsw = current event psalter week
            $cepsw = $currentEvent->psalter_week;
            // $nepsw = next event psalter week
            $nepsw = $nextEvent->psalter_week;
            // We try to keep together valid psalter week values,
            // while we break up invalid psalter week values
            // unless the invalid values fall on the same day
            // IF the next event's psalter week is the same as the current event's psalter week
            // AND either:
            //      * the next event's psalter week is within the valid Psalter week values of 1-4 (is not 0)
            //      * OR the next event is on the same day as the current event
            if ($nepsw === $cepsw && ($nepsw !== 0 || $currentEvent->date == $nextEvent->date)) {
                $cw++;
                $this->countSamePsalterWeekEvents($nextEventIdx, $cw);
            }
        }
    }

    /**
     * Given a LiturgicalEvent object, determines which liturgical season it falls into.
     * @param LiturgicalEvent $litevent The liturgical event to determine the liturgical season for
     * @return string The liturgical season of the given event
     */
    private function determineSeason(LiturgicalEvent $litevent)
    {
        if ($litevent->date >= $this->LiturgicalCalendar->litcal->AshWednesday->date && $litevent->date < $this->LiturgicalCalendar->litcal->HolyThurs->date) {
            return 'LENT';
        }
        if ($litevent->date >= $this->LiturgicalCalendar->litcal->HolyThurs->date && $litevent->date < $this->LiturgicalCalendar->litcal->Easter->date) {
            return 'EASTER_TRIDUUM';
        }
        if ($litevent->date >= $this->LiturgicalCalendar->litcal->Easter->date && $litevent->date < $this->LiturgicalCalendar->litcal->Pentecost->date) {
            return 'EASTER';
        }
        if ($litevent->date >= $this->LiturgicalCalendar->litcal->Advent1->date && $litevent->date < $this->LiturgicalCalendar->litcal->Christmas->date) {
            return 'ADVENT';
        }
        if ($litevent->date > $this->LiturgicalCalendar->litcal->BaptismLord->date && $litevent->date < $this->LiturgicalCalendar->litcal->AshWednesday->date) {
            return 'ORDINARY_TIME';
        }
        // We won't have Advent1 if we have requested a LITURGICAL year_type (it will be less than BaptismLord not greater)
        // So to correctly determine ORDINARY_TIME we should check if the date is less than or equal to Saturday of the 34th week of Ordinary Time
        // Seeing that there may be a Memorial on this day, the only way to get this date is by checking the Saturday following Christ the King
        $Saturday34thWeekOrdTime = clone $this->LiturgicalCalendar->litcal->ChristKing->date;
        $Saturday34thWeekOrdTime->modify('next Saturday');
        if ($litevent->date > $this->LiturgicalCalendar->litcal->Pentecost->date && $litevent->date <= $Saturday34thWeekOrdTime) {
            return 'ORDINARY_TIME';
        }
        // When we have requested a LITURGICAL year_type, Advent1_vigil will be a lone event at the start of the calendar.
        // Since we don't have the other events of that day (which would fall under ORDINARY_TIME), we should return ADVENT
        if ($this->LiturgicalCalendar->settings->year_type === 'LITURGICAL') {
            if ($litevent->date == $this->LiturgicalCalendar->litcal->Advent1_vigil->date) {
                return 'ADVENT';
            }
        }
        return 'CHRISTMAS';
    }

    /**
     * Determines the liturgical color for the Liturgical Season, to apply to liturgical events within that season.
     *
     * @param LiturgicalEvent $litevent The liturgical event for which the color is determined.
     * @return string The color representing the liturgical season (e.g., "green", "purple", "white").
     */
    private function getSeasonColor(LiturgicalEvent $litevent)
    {
        switch ($litevent->liturgical_season) {
            case 'ADVENT':
            case 'LENT':
            case 'EASTER_TRIDUUM':
                return 'purple';
            case 'EASTER':
            case 'CHRISTMAS':
                return 'white';
            case 'ORDINARY_TIME':
            default:
                return 'green';
        }
    }


    /**
     * Sets the background color of the given table cell, or adds a CSS class
     * representing the color, or adds a small colored circle to the cell,
     * depending on the value of $this->seasonColor.
     * The action will only take place if the Column for which the color should be applied is in the $this->seasonColorColumns array.
     *
     * @param string $seasonColor The color representing the liturgical season
     * @param \DomElement $td The table cell to which the color should be applied
     * @param Column $columnFlag The column for which the color should be applied
     */
    private function handleSeasonColorForColumn(string $seasonColor, \DomElement $td, Column $columnFlag)
    {
        if ($this->seasonColorColumns->has($columnFlag)) {
            switch ($this->seasonColor) {
                case ColorAs::BACKGROUND:
                    $td->setAttribute('style', 'background-color:' . $seasonColor . ';' . (in_array($seasonColor, WebCalendar::HIGH_CONTRAST) ? 'color:white;' : ''));
                    break;
                case ColorAs::CSS_CLASS:
                    $classes = $td->getAttribute('class');
                    $classes .= ' ' . $seasonColor;
                    $td->setAttribute('class', $classes);
                    break;
                case ColorAs::INDICATOR:
                    $colorSpan = $this->dom->createElement('span');
                    $colorSpan->setAttribute('style', "background-color:$seasonColor;width:10px;height:10px;display:inline-block;border:1px solid black;border-radius:5px;margin-right:5px;");
                    $td->appendChild($colorSpan);
                    break;
            }
        }
    }

    /**
     * Sets the background color of the given table cell, or adds a CSS class
     * representing the color, or adds a small colored circle to the cell,
     * depending on the value of $this->eventColor.
     * The action will only take place if the Column for which the color should be applied is in the $this->eventColorColumns array.
     *
     * @param string|array $eventColor The color or colors representing the event
     * @param \DomElement $td The table cell to which the color should be applied
     * @param Column $columnFlag The column for which the color should be applied
     */
    private function handleEventColorForColumn(string|array $eventColor, \DomElement $td, Column $columnFlag)
    {
        if (is_string($eventColor)) {
            $eventColor = [$eventColor];
        }
        if ($this->eventColorColumns->has($columnFlag)) {
            switch ($this->eventColor) {
                case ColorAs::BACKGROUND:
                    $td->setAttribute('style', 'background-color:' . $eventColor[0] . ';' . (in_array($eventColor[0], WebCalendar::HIGH_CONTRAST) ? 'color:white;' : ''));
                    break;
                case ColorAs::CSS_CLASS:
                    $classes = $td->getAttribute('class');
                    $classes .= ' ' . $eventColor[0];
                    $td->setAttribute('class', $classes);
                    break;
                case ColorAs::INDICATOR:
                    foreach ($eventColor as $eventColor) {
                        $colorSpan = $this->dom->createElement('span');
                        $colorSpan->setAttribute('style', "background-color:$eventColor;width:10px;height:10px;display:inline-block;border:1px solid black;border-radius:5px;margin-right:5px;");
                        $td->appendChild($colorSpan);
                    }
                    break;
            }
        }
    }


    /**
     * Outputs a table row for the given liturgical event from the requested Liturgical Calendar
     *
     * @param LiturgicalEvent $litevent The liturgical event to display
     * @param bool $newMonth [reference] Whether we are starting a new month
     * @param bool $newSeason [reference] Whether we are starting a new liturgical season
     * @param int $cd Count of Celebrations within the same day
     * @param int $cm Count of Celebrations within the same month
     * @param int $cs Count of Celebrations within the same liturgical season
     * @param ?int $ev Index of liturgical events within the same day.
     *                  If zero, then we are creating the row for the first event and we need to set the rowspan based on the count of events within the given day.
     *                  If null, then there is only one event for the day and we need not set the rowspan at all.
     * @return array The table row for the given liturgical event, or rows if a month header row is needed
     */
    private function buildTableRow(
        LiturgicalEvent $litevent,
        bool &$newMonth,
        bool &$newSeason,
        bool &$newPsalterWeek,
        int $cd,
        int $cm,
        int $cs,
        int $cw,
        ?int $ev
    ): array
    {
        $monthFmt = \IntlDateFormatter::create($this->locale, \IntlDateFormatter::FULL, \IntlDateFormatter::NONE, 'UTC', \IntlDateFormatter::GREGORIAN, 'MMMM');
        switch ($this->dateFormat) {
            case DateFormat::FULL:
                $dateFmt = \IntlDateFormatter::create($this->locale, \IntlDateFormatter::FULL, \IntlDateFormatter::NONE, 'UTC', \IntlDateFormatter::GREGORIAN);
                break;
            case DateFormat::LONG:
                $dateFmt  = \IntlDateFormatter::create($this->locale, \IntlDateFormatter::LONG, \IntlDateFormatter::NONE, 'UTC', \IntlDateFormatter::GREGORIAN);
                break;
            case DateFormat::MEDIUM:
                $dateFmt  = \IntlDateFormatter::create($this->locale, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE, 'UTC', \IntlDateFormatter::GREGORIAN);
                break;
            case DateFormat::SHORT:
                $dateFmt  = \IntlDateFormatter::create($this->locale, \IntlDateFormatter::SHORT, \IntlDateFormatter::NONE, 'UTC', \IntlDateFormatter::GREGORIAN);
                break;
            case DateFormat::DAY_ONLY:
                $dateFmt  = \IntlDateFormatter::create($this->locale, \IntlDateFormatter::FULL, \IntlDateFormatter::NONE, 'UTC', \IntlDateFormatter::GREGORIAN, 'd EEEE');
                break;
        }
        $seasonColor = $this->getSeasonColor($litevent);
        $monthHeaderRow = null;
        $tr = $this->dom->createElement('tr');

        // First column is Month or Liturgical Season
        if ($newMonth || $newSeason) {
            if ($newMonth && $this->firstColumnGrouping === Grouping::BY_MONTH) {
                $firstColRowSpan = $cm + 1;
                if ($this->monthHeader) {
                    $firstColRowSpan++;
                    $monthHeaderRow = $this->dom->createElement('tr');
                    $monthHeaderCell = $this->dom->createElement('td');
                    $monthHeaderCell->setAttribute('colspan', 3);
                    $monthHeaderCell->setAttribute('class', 'monthHeader');
                    $monthHeaderCell->appendChild($this->dom->createTextNode($monthFmt->format($litevent->date->format('U'))));
                }
                $firstColCell = $this->dom->createElement('td');
                $firstColCell->setAttribute('rowspan', $firstColRowSpan);
                $firstColCell->setAttribute('class', 'rotate month');
                $this->handleSeasonColorForColumn($seasonColor, $firstColCell, Column::MONTH);
                $this->handleEventColorForColumn($litevent->color, $firstColCell, Column::MONTH);
                $textNode = $this->baseLocale === 'la'
                    ? strtoupper($this->latinInterface->monthLatinFull( (int) $litevent->date->format('n') ))
                    : strtoupper($monthFmt->format($litevent->date->format('U')));
                $div = $this->dom->createElement('div');
                $div->appendChild($this->dom->createTextNode($textNode));
                $firstColCell->appendChild($div);
                if ($this->monthHeader) {
                    $monthHeaderRow->appendChild($firstColCell);
                    $monthHeaderRow->appendChild($monthHeaderCell);
                } else {
                    $tr->appendChild($firstColCell);
                }
            }
            if ($newSeason && $this->firstColumnGrouping === Grouping::BY_LITURGICAL_SEASON) {
                $firstColRowSpan = $cs + 1;
                $firstColCell = $this->dom->createElement('td');
                if ($this->monthHeader) {
                    $this->lastSeasonCell = $firstColCell;
                }
                $firstColCell->setAttribute('rowspan', $firstColRowSpan);
                $firstColCell->setAttribute('class', "rotate season $litevent->liturgical_season");
                $this->handleSeasonColorForColumn($seasonColor, $firstColCell, Column::LITURGICAL_SEASON);
                $this->handleEventColorForColumn($litevent->color, $firstColCell, Column::LITURGICAL_SEASON);
                $div = $this->dom->createElement('div');
                $div->appendChild($this->dom->createTextNode($litevent->liturgical_season_lcl ?? ''));
                $firstColCell->appendChild($div);
                if ($this->monthHeader && $newMonth) {
                    $firstColRowSpan++;
                    $firstColCell->setAttribute('rowspan', $firstColRowSpan);
                    $monthHeaderRow = $this->dom->createElement('tr');
                    $monthHeaderCell = $this->dom->createElement('td');
                    $monthHeaderCell->setAttribute('colspan', 3);
                    $monthHeaderCell->setAttribute('class', 'monthHeader');
                    $monthHeaderCell->appendChild($this->dom->createTextNode($monthFmt->format($litevent->date->format('U'))));
                    $monthHeaderRow->appendChild($firstColCell);
                    $monthHeaderRow->appendChild($monthHeaderCell);
                } else {
                    $tr->appendChild($firstColCell);
                }
            }
            if (false === $newSeason && $newMonth && $this->monthHeader && $this->firstColumnGrouping === Grouping::BY_LITURGICAL_SEASON) {
                $firstColCellRowSpan = $this->lastSeasonCell->getAttribute('rowspan');
                $this->lastSeasonCell->setAttribute('rowspan', (int)$firstColCellRowSpan + 1);
                $monthHeaderRow = $this->dom->createElement('tr');
                $monthHeaderCell = $this->dom->createElement('td');
                $monthHeaderCell->setAttribute('colspan', 3);
                $monthHeaderCell->setAttribute('class', 'monthHeader');
                $monthHeaderCell->appendChild($this->dom->createTextNode($monthFmt->format($litevent->date->format('U'))));
                $monthHeaderRow->appendChild($monthHeaderCell);
            }
            $newMonth = false;
            $newSeason = false;
        }

        // Second column is date
        $dateString = "";
        switch ($this->baseLocale) {
            case 'la':
                $dateString = $this->latinInterface->formatDate($this->dateFormat, $litevent->date);
                break;
            default:
                $dateString = $dateFmt->format($litevent->date->format('U'));
        }

        // We only need to "create" the dateEntry cell on first iteration of events within the same day (0 === $ev),
        // or when there is only one event for the day (null === $ev).
        // When there is only one event for the day, we need not set the rowspan.
        // When there are multiple events, we set the rowspan based on the total number of events within the day ($cd "count day events").
        if (0 === $ev || null === $ev) {
            $dateCell = $this->dom->createElement('td');
            $dateCell->setAttribute('class', 'dateEntry');
            $this->handleSeasonColorForColumn($seasonColor, $dateCell, Column::DATE);
            $this->handleEventColorForColumn($litevent->color, $dateCell, Column::DATE);
            $dateCell->appendChild($this->dom->createTextNode($dateString));
            if (0 === $ev) {
                $dateCell->setAttribute('rowspan', $cd + 1);
            }
            $tr->appendChild($dateCell);
        }

        // Third column contains the event details such as name of the celebration, liturgical_year cycle, liturgical colors, and liturgical commons
        $currentCycle = property_exists($litevent, 'liturgical_year') && $litevent->liturgical_year !== null && $litevent->liturgical_year !== ''
                            ? ' (' . $litevent->liturgical_year . ')'
                            : '';
        $eventDetailsCell = $this->dom->createElement('td');
        $eventDetailsCell->setAttribute('class', 'eventDetails liturgicalGrade_' . $litevent->grade);
        $this->handleSeasonColorForColumn($seasonColor, $eventDetailsCell, Column::EVENT);
        $this->handleEventColorForColumn($litevent->color, $eventDetailsCell, Column::EVENT);
        $eventDetailsContents = $this->dom->createDocumentFragment();
        $eventDetailsContents->appendXML($litevent->name . $currentCycle . ' - <i>' . implode(' ' . dgettext('webcalendar', 'or') . ' ', $litevent->color_lcl) . '</i><br /><i>' . $litevent->common_lcl . '</i>');
        $eventDetailsCell->appendChild($eventDetailsContents);

        // Fourth column contains the liturgical grade or rank of the celebration
        $displayGrade = $litevent->grade_display !== null
                            ? $litevent->grade_display
                            : $litevent->grade_lcl;
        if ($this->gradeDisplay === GradeDisplay::ABBREVIATED && $litevent->grade_display !== '') {
            $displayGrade = $litevent->grade_abbr;
        }
        $liturgicalGradeCell = $this->dom->createElement('td');
        $liturgicalGradeCell->setAttribute('class', 'liturgicalGrade liturgicalGrade_' . $litevent->grade);
        $this->handleSeasonColorForColumn($seasonColor, $liturgicalGradeCell, Column::GRADE);
        $this->handleEventColorForColumn($litevent->color, $liturgicalGradeCell, Column::GRADE);
        $liturgicalGradeCell->appendChild($this->dom->createTextNode($displayGrade));

        // Third and fourth column order depends on value of $this->columnOrder
        switch ($this->columnOrder) {
            case ColumnOrder::GRADE_FIRST:
                $tr->appendChild($liturgicalGradeCell);
                $tr->appendChild($eventDetailsCell);
                break;
            case ColumnOrder::EVENT_DETAILS_FIRST:
                $tr->appendChild($eventDetailsCell);
                $tr->appendChild($liturgicalGradeCell);
                break;
        }

        // Fifth column contains the Psalter week if Psalter week grouping is enabled
        if ($this->psalterWeekGrouping && false === $newPsalterWeek && null !== $monthHeaderRow) {
            $psalterWeekCellRowSpan = $this->lastPsalterWeekCell->getAttribute('rowspan');
            $this->lastPsalterWeekCell->setAttribute('rowspan', (int)$psalterWeekCellRowSpan + 1);
        }
        if ($this->psalterWeekGrouping && $newPsalterWeek) {
            $psalterWeekCell = $this->dom->createElement('td');
            $psalterWeekCell->setAttribute('class', 'psalterWeek');
            $this->lastPsalterWeekCell = $psalterWeekCell;
            /** @var string $romNumPsalterWeek The Roman numeral version of the Psalter week */
            $romNumPsalterWeek = WebCalendar::PSALTER_WEEK[$litevent->psalter_week];
            $this->handleSeasonColorForColumn($seasonColor, $psalterWeekCell, Column::PSALTER_WEEK);
            $this->handleEventColorForColumn($litevent->color, $psalterWeekCell, Column::PSALTER_WEEK);
            $psalterWeekCell->appendChild($this->dom->createTextNode($romNumPsalterWeek));
            $psalterWeekCellRowspan = $cw + 1;
            if (null !== $monthHeaderRow) {
                $psalterWeekCellRowspan++;
                $psalterWeekCell->setAttribute('rowspan', $psalterWeekCellRowspan);
                $monthHeaderRow->appendChild($psalterWeekCell);
            } else {
                $psalterWeekCell->setAttribute('rowspan', $psalterWeekCellRowspan);
                $tr->appendChild($psalterWeekCell);
            }
            $newPsalterWeek = false;
        }
        if (null !== $monthHeaderRow) {
            return [$monthHeaderRow, $tr];
        }
        return [$tr];
    }

    /**
     * Generates an HTML table representing the liturgical calendar.
     *
     * This method creates a table with headers for "Month", "Date",
     * "Celebration", and "Liturgical Grade". It iterates through
     * the liturgical events, grouping and displaying them by month
     * and day, including handling multiple events on the same day.
     * The table is represented as a string of HTML.
     *
     * @return string The HTML representation of the liturgical calendar table.
     */
    public function buildTable(): string
    {
        $this->setLocale($this->LiturgicalCalendar->settings->locale);

        $table = $this->dom->createElement('table');
        if (null !== $this->id) {
            $table->setAttribute('id', $this->id);
        }
        if (null !== $this->class) {
            $table->setAttribute('class', $this->class);
        }

        $colGroup = $this->dom->createElement('colgroup');
        $colCount = $this->psalterWeekGrouping ? 5 : 4;
        for ($i = 0; $i < $colCount; $i++) {
            $col = $this->dom->createElement('col');
            $col->setAttribute('class', 'col' . ($i + 1));
            $colGroup->appendChild($col);
        }
        $table->appendChild($colGroup);
        $this->dom->appendChild($table);

        if (false === $this->removeCaption) {
            $caption = $this->dom->createElement('caption');
            if (property_exists($this->LiturgicalCalendar->settings, 'diocesan_calendar')) {
                $captionText = sprintf(
                    /**translators: 1. name of the diocese, 2. year */
                    dgettext('webcalendar', 'Liturgical Calendar for the %1$s - %2$s'),
                    $this->LiturgicalCalendar->metadata->diocese_name,
                    $this->LiturgicalCalendar->settings->year
                );
            } elseif (property_exists($this->LiturgicalCalendar->settings, 'national_calendar')) {
                $nation = \Locale::getDisplayRegion("-" . $this->LiturgicalCalendar->settings->national_calendar, $this->locale);
                $captionText = sprintf(
                    /**translators: 1. name of the nation, 2. year */
                    dgettext('webcalendar', 'Liturgical Calendar for %1$s - %2$s'),
                    $nation,
                    $this->LiturgicalCalendar->settings->year
                );
            } else {
                $captionText = sprintf(
                    dgettext('webcalendar', 'General Roman Calendar - %1$s'),
                    $this->LiturgicalCalendar->settings->year
                );
            }
            $caption->appendChild($this->dom->createTextNode($captionText));
            $table->appendChild($caption);
        }

        if (false === $this->removeHeaderRow) {
            $thead = $this->dom->createElement('thead');

            $theadRow = $this->dom->createElement('tr');

            $th1 = $this->dom->createElement('th');
            if ($this->firstColumnGrouping === Grouping::BY_MONTH) {
                $textNode = $this->dom->createTextNode(dgettext('webcalendar', 'Month'));
            } elseif ($this->firstColumnGrouping === Grouping::BY_LITURGICAL_SEASON) {
                /**translators: 'Season' refers to the liturgical season. */
                $textNode = $this->dom->createTextNode(dgettext('webcalendar', 'Season'));
            }
            $th1->appendChild($textNode);
            $theadRow->appendChild($th1);

            $th2 = $this->dom->createElement('th');
            $th2->appendChild($this->dom->createTextNode(dgettext('webcalendar', 'Date')));
            $theadRow->appendChild($th2);

            $th3 = $this->dom->createElement('th');
            $th3->appendChild($this->dom->createTextNode(dgettext('webcalendar', 'Celebration')));

            $th4 = $this->dom->createElement('th');
            $th4->appendChild($this->dom->createTextNode(dgettext('webcalendar', 'Liturgical Grade')));

            // Third and fourth column order depends on value of $this->columnOrder
            switch ($this->columnOrder) {
                case ColumnOrder::GRADE_FIRST:
                    $theadRow->appendChild($th4);
                    $theadRow->appendChild($th3);
                    break;
                case ColumnOrder::EVENT_DETAILS_FIRST:
                    $theadRow->appendChild($th3);
                    $theadRow->appendChild($th4);
                    break;
            }

            if ($this->psalterWeekGrouping) {
                $th5 = $this->dom->createElement('th');
                $th5->appendChild($this->dom->createTextNode(dgettext('webcalendar', 'Psalter')));
                $theadRow->appendChild($th5);
            }

            $thead->appendChild($theadRow);
            $table->appendChild($thead);
        }

        $tbody = $this->dom->createElement('tbody');
        $table->appendChild($tbody);


        $currentMonth = 0; //1=January, ... 12=December; start with a certain non valid value, so that the first iteration will trigger a new month
        $currentSeason = ''; //ADVENT, LENT, ORDINARY_TIME, EASTER, EASTER_TRIDUUM, CHRISTMAS; start with a certain non valid value, so that the first iteration will trigger a new season
        $currentPsalterWeek = 5; //start with a certain non valid value, so that the first iteration will trigger a new psalter week
        $newMonth = false;
        $newSeason = false;
        $newPsalterWeek = false;
        $eventsCount = count($this->LiturgicalCalendar->litcal);

        // Loop through the liturgical events
        // Cannot use foreach here because we are manually manipulating the value of $eventIdx within the loop!
        for ($eventIdx = 0; $eventIdx < $eventsCount; $eventIdx++) {
            $litevent = $this->LiturgicalCalendar->litcal[$eventIdx];
            $this->daysCreated++;

            // Check if we are at the start of a new month, and if so count how many events we have in that same month,
            // so we can display the Month table cell with the correct colspan when firstColumnGrouping is BY_MONTH.
            $eventMonth = (int) $litevent->date->format('n');
            if ($eventMonth !== $currentMonth) {
                $newMonth = true;
                $currentMonth = $eventMonth;
                /** @var int $cm Count events in the same month */
                $cm = 0;
                $this->countSameMonthEvents($eventIdx, $cm);
            }

            // Check if we are at the start of a new season, and if so count how many events we have in that same season,
            // so we can display the Season table cell with the correct colspan when firstColumnGrouping is BY_LITURGICAL_SEASON.
            if ($litevent->liturgical_season !== $currentSeason) {
                $newSeason = true;
                $currentSeason = $litevent->liturgical_season;
                /** @var int $cs Count events in the same season */
                $cs = 0;
                $this->countSameSeasonEvents($eventIdx, $cs);
            }

            // Check if we are at the start of a new Psalter week, and if so count how many events we have with the same Psalter week,
            // so we can display the Psalter week table cell with the correct colspan
            if ($litevent->psalter_week !== $currentPsalterWeek || $litevent->psalter_week === 0) {
                $newPsalterWeek = true;
                /** @var int $cw Count events in the same psalter week */
                $cw = 0;
                $currentPsalterWeek = $litevent->psalter_week;
                $this->countSamePsalterWeekEvents($eventIdx, $cw);
            }

            // Check if we have more than one event on the same day, such as optional memorials,
            // so we can display the Date table cell with the correct colspan.
            /** @var int $cd Count events in the same day */
            $cd = 0;
            $this->countSameDayEvents($eventIdx, $cd);

            if ($cd > 0) {
                /** @var int $ev Index of the liturgical event within the same day $cd count. */
                for ($ev = 0; $ev <= $cd; $ev++) {
                    $litevent = $this->LiturgicalCalendar->litcal[$eventIdx];

                    // Check if we are at the start of a new season, and if so count how many events we have in that same season,
                    // so we can display the Season table cell with the correct colspan when firstColumnGrouping is BY_LITURGICAL_SEASON.
                    if ($litevent->liturgical_season !== $currentSeason) {
                        $newSeason = true;
                        $currentSeason = $litevent->liturgical_season;
                        /** @var int $cs Count events in the same season */
                        $cs = 0;
                        $this->countSameSeasonEvents($eventIdx, $cs);
                    }

                    // Check if we are at the start of a new Psalter week, and if so count how many events we have with the same Psalter week,
                    // so we can display the Psalter week table cell with the correct colspan
                    if ($litevent->psalter_week !== $currentPsalterWeek) {
                        $newPsalterWeek = true;
                        /** @var int $cw Count events in the same psalter week */
                        $cw = 0;
                        $currentPsalterWeek = $litevent->psalter_week;
                        $this->countSamePsalterWeekEvents($eventIdx, $cw);
                    }

                    $trs = $this->buildTableRow($litevent, $newMonth, $newSeason, $newPsalterWeek, $cd, $cm, $cs, $cw, $ev);
                    foreach ($trs as $tr) {
                        $tbody->appendChild($tr);
                    }
                    $eventIdx++;
                }
                $eventIdx--;
            } else {
                // Only a single liturgical event on this day. No need for an event index.
                $trs = $this->buildTableRow($litevent, $newMonth, $newSeason, $newPsalterWeek, $cd, $cm, $cs, $cw, null);
                foreach ($trs as $tr) {
                    $tbody->appendChild($tr);
                }
            }
        }
        $this->resetGlobalLocale();
        return $this->dom->saveHTML();
    }

    /**
     * Returns the locale that was set when the WebCalendar object was created / buildTable was called.
     *
     * @return string|null The locale, or null if no locale was set.
     */
    public function getLocale(): ?string
    {
        return $this->currentSetLocale;
    }

    /**
     * Returns the number of days that have been created in the WebCalendar.
     * When the liturgical calendar is generated with the CIVIL year_type,
     * this should be 366 for non leap years, or 367 for leap years
     * (taking into account the vigil Mass on December 31st of the previous year).
     * When the liturgical calendar is generated with the LITURGICAL year_type,
     * the number of days can vary based on when Advent starts from one year to the next.
     * For example, in the years 2027-2028, Advent starts on Nov. 28th 2027 and then
     * again on Dec. 3rd 2028, making for 371 liturgical days.
     *
     * @return int The count of days created.
     */
    public function daysCreated(): int
    {
        return $this->daysCreated;
    }
}
