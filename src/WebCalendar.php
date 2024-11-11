<?php

namespace LiturgicalCalendar\Components;

use LiturgicalCalendar\Components\WebCalendar\LiturgicalEvent;
use LiturgicalCalendar\Components\WebCalendar\Grouping;

/**
 * A class to generate a table of liturgical events for a given Liturgical Calendar.
 *
 * The class takes an object from the Liturgical Calendar API as a parameter in its constructor.
 * The object should have the following properties:
 * - litcal: an array of liturgical event objects with date, liturgical color, liturgical common, and liturgical grade properties
 * - settings: the settings object from the Liturgical Calendar API
 * - metadata: the metadata object from the Liturgical Calendar API
 * - messages: the messages object from the Liturgical Calendar API
 *
 * The class provides the following methods:
 * - buildTable(): returns an HTML string containing a table of the liturgical events.
 * - id(string $id): sets the id of the table element.
 * - class(string $class): sets the class of the table element.
 * - daysCreated(): returns the number of days created in the table.
 * - getLocale(): returns the locale that was set when the WebCalendar object was created / buildTable was called.
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
    private array $LitCalKeys               = [];
    private ?string $class                  = null;
    private ?string $id                     = null;
    private int $daysCreated                = 0;
    private Grouping $firstColumnGrouping   = Grouping::BY_MONTH;
    private bool $hideHeaderRow             = false;
    private bool $psalterWeekGrouping       = false;
    private \DomDocument $dom;

    /**
     * Latin names for the days of the week.
     * Array indexed 0-Sunday, 1-Monday, 2-Tuesday, 3-Wednesday, 4-Thursday, 5-Friday, 6-Saturday.
     * This is required since systems do not yet support the Latin language.
     * @var string[]
     */
    public const DAYS_OF_THE_WEEK_LATIN = [
        "dies Solis",
        "dies Lunæ",
        "dies Martis",
        "dies Mercurii",
        "dies Iovis",
        "dies Veneris",
        "dies Saturni"
    ];

    /**
     * An array of the months of the year in Latin
     * The index of the array is the month number (1-12)
     * The value is the Latin name of the month.
     * This is required since systems do not yet support the Latin language.
     * @var string[]
     */
    public const MONTHS_LATIN = [
        "",
        "Ianuarius",
        "Februarius",
        "Martius",
        "Aprilis",
        "Maius",
        "Iunius",
        "Iulius",
        "Augustus",
        "September",
        "October",
        "November",
        "December"
    ];

    /**
     * An array of Roman numerals to translate the psalter week cycle
     * from arabic numerals to Roman numerals
     */
    public const PSALTER_WEEK = [
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

        foreach ($LiturgicalCalendar->litcal as $key => $value) {
            $LiturgicalCalendar->litcal->$key = new LiturgicalEvent($value);
            $this->LitCalKeys[] = $key;
        }

        //header('Content-Type: application/json');
        //die(json_encode($LiturgicalCalendar));
        //die(json_encode($this->LitCalKeys));
        $this->LiturgicalCalendar = $LiturgicalCalendar;
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
     * Sets whether the header row should be hidden or not.
     *
     * This method controls the visibility of the header row that is
     * generated by the buildTable() method. The default is true, meaning
     * that the header row should be hidden.
     *
     * @param bool $hideHeaderRow Whether the header row should be hidden or not.
     *
     * @return $this The current instance of the class.
     */
    public function hideHeaderRow(bool $hideHeaderRow = true): self
    {
        $this->hideHeaderRow = $hideHeaderRow;
        return $this;
    }

    public function psalterWeekGrouping(bool $psalterWeekGrouping = true): self
    {
        $this->psalterWeekGrouping = $psalterWeekGrouping;
        return $this;
    }

    /**
     * Sets the locale for the component, for the translations of frontend strings and date information.
     * Called internally by the public method (@see buildTable).
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
     * @param int $currentKeyIndex The current position in the array of liturgical events on the given day.
     * @param int $cd [reference] The count of subsequent liturgical events in the same day.
     */
    private function countSameDayEvents(int $currentKeyIndex, int &$cd)
    {
        $EventsObject = $this->LiturgicalCalendar->litcal;
        $currentEvent = $EventsObject->{$this->LitCalKeys[$currentKeyIndex]};
        if ($currentKeyIndex < count($this->LitCalKeys) - 1) {
            $nextEvent = $EventsObject->{$this->LitCalKeys[$currentKeyIndex + 1]};
            // date->format('U'): Seconds since the Unix Epoch (January 1 1970 00:00:00 GMT) aka timestamp
            if ($nextEvent->date->format('U') === $currentEvent->date->format('U')) {
                $cd++;
                $this->countSameDayEvents($currentKeyIndex + 1, $cd);
            }
        }
    }

    /**
     * Recursively counts the number of subsequent liturgical events in the same month.
     *
     * @param int $currentKeyIndex The current position in the array of liturgical events on the given month.
     * @param int $cm [reference] The count of subsequent liturgical events in the same month.
     */
    private function countSameMonthEvents(int $currentKeyIndex, int &$cm)
    {
        $EventsObject = $this->LiturgicalCalendar->litcal;
        $currentEvent = $EventsObject->{$this->LitCalKeys[$currentKeyIndex]};
        if ($currentKeyIndex < count($this->LitCalKeys) - 1) {
            $nextEvent = $EventsObject->{$this->LitCalKeys[$currentKeyIndex + 1]};
            // date->format('n'): Numeric representation of a month, without leading zeros, 1-12
            if ($nextEvent->date->format('n') === $currentEvent->date->format('n')) {
                $cm++;
                $this->countSameMonthEvents($currentKeyIndex + 1, $cm);
            }
        }
    }

    /**
     * Recursively counts the number of subsequent liturgical events in the same liturgical season.
     *
     * @param int $currentKeyIndex The current position in the array of liturgical events on the given liturgical season.
     * @param int $cs [reference] The count of subsequent liturgical events in the same liturgical season.
     */
    private function countSameSeasonEvents(int $currentKeyIndex, int &$cs)
    {
        $EventsObject = $this->LiturgicalCalendar->litcal;
        $currentEvent = $EventsObject->{$this->LitCalKeys[$currentKeyIndex]};
        if ($currentKeyIndex < count($this->LitCalKeys) - 1) {
            $nextEvent = $EventsObject->{$this->LitCalKeys[$currentKeyIndex + 1]};
            $currentEventLiturgicalSeason = $currentEvent->liturgical_season ?? $this->determineSeason($currentEvent);
            $nextEventLiturgicalSeason = $nextEvent->liturgical_season ?? $this->determineSeason($nextEvent);
            if ($nextEventLiturgicalSeason === $currentEventLiturgicalSeason) {
                $cs++;
                $this->countSameSeasonEvents($currentKeyIndex + 1, $cs);
            }
        }
    }

    private function countSamePsalterWeekEvents(int $currentKeyIndex, int &$cw)
    {
        $EventsObject = $this->LiturgicalCalendar->litcal;
        $currentEvent = $EventsObject->{$this->LitCalKeys[$currentKeyIndex]};
        if ($currentKeyIndex < count($this->LitCalKeys) - 1) {
            $nextEvent = $EventsObject->{$this->LitCalKeys[$currentKeyIndex + 1]};
            $currentEventPsalterWeek = $currentEvent->psalter_week;
            $nextEventPsalterWeek = $nextEvent->psalter_week;
            if ($nextEventPsalterWeek === $currentEventPsalterWeek) { // || $nextEvent->grade === 1 || $nextEvent->grade === 2
                $cw++;
                $this->countSamePsalterWeekEvents($currentKeyIndex + 1, $cw);
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
    ): \DomElement
    {
        $monthFmt = \IntlDateFormatter::create($this->locale, \IntlDateFormatter::FULL, \IntlDateFormatter::FULL, 'UTC', \IntlDateFormatter::GREGORIAN, 'MMMM');
        $dateFmt  = \IntlDateFormatter::create($this->locale, \IntlDateFormatter::FULL, \IntlDateFormatter::FULL, 'UTC', \IntlDateFormatter::GREGORIAN, 'EEEE d MMMM yyyy');
        $highContrast = ['purple', 'red', 'green'];
        $seasonColor = $this->getSeasonColor($litevent);
        $tr = $this->dom->createElement('tr');
        $tr->setAttribute('style', 'background-color:' . $seasonColor . ';' . (in_array($seasonColor, $highContrast) ? 'color:white;' : ''));

        // First column is Month or Liturgical Season
        if ($newMonth || $newSeason) {
            if ($newMonth && $this->firstColumnGrouping === Grouping::BY_MONTH) {
                $firstColRowSpan = $cm + 1;
                $firstColCell = $this->dom->createElement('td');
                $firstColCell->setAttribute('rowspan', $firstColRowSpan);
                $firstColCell->setAttribute('class', 'rotate');
                $textNode = $this->baseLocale === 'la'
                                ? strtoupper(self::MONTHS_LATIN[ (int)$litevent->date->format('n') ])
                                : strtoupper($monthFmt->format($litevent->date->format('U')));
                $div = $this->dom->createElement('div');
                $div->appendChild($this->dom->createTextNode($textNode));
                $firstColCell->appendChild($div);
                $tr->appendChild($firstColCell);
            }
            if ($newSeason && $this->firstColumnGrouping === Grouping::BY_LITURGICAL_SEASON) {
                $firstColRowSpan = $cs + 1;
                $firstColCell = $this->dom->createElement('td');
                $firstColCell->setAttribute('rowspan', $firstColRowSpan);
                $firstColCell->setAttribute('class', 'rotate');
                $div = $this->dom->createElement('div');
                $div->appendChild($this->dom->createTextNode($litevent->liturgical_season_lcl ?? ''));
                $firstColCell->appendChild($div);
                $tr->appendChild($firstColCell);
            }
            $newMonth = false;
            $newSeason = false;
        }

        // Second column is date
        $dateString = "";
        switch ($this->baseLocale) {
            case 'la':
                $dayOfTheWeek = (int)$litevent->date->format('w'); //w = 0-Sunday to 6-Saturday
                $dayOfTheWeekLatin = self::DAYS_OF_THE_WEEK_LATIN[$dayOfTheWeek];
                $month = (int)$litevent->date->format('n'); //n = 1-January to 12-December
                $monthLatin = self::MONTHS_LATIN[$month];
                $dateString = $dayOfTheWeekLatin . ' ' . $litevent->date->format('j') . ' ' . $monthLatin . ' ' . $litevent->date->format('Y');
                break;
            case 'en':
                $dateString = $litevent->date->format('D, F jS, Y');
                break;
            default:
                $dateString = $dateFmt->format($litevent->date->format('U'));
        }

        // We only need to "create" the dateEntry cell on first iteration of events within the same day, or when there is only one event in the day.
        // When there is only one event ($ev===null) we need not set the rowspan.
        // When there are multiple events, we set the rowspan based on the total number of events within the day ($cd).
        if (0 === $ev || null === $ev) {
            $dateCell = $this->dom->createElement('td');
            $dateCell->setAttribute('class', 'dateEntry');
            $dateCell->appendChild($this->dom->createTextNode($dateString));
            if (0 === $ev) {
                $dateCell->setAttribute('rowspan', $cd + 1);
            }
            $tr->appendChild($dateCell);
        }

        // Fourth column contains name of the celebration, liturgical_year cycle, liturgical colors, and liturgical commons
        $currentCycle = property_exists($litevent, 'liturgical_year') && $litevent->liturgical_year !== null && $litevent->liturgical_year !== '' ? ' (' . $litevent->liturgical_year . ')' : '';
        $displayGrade = $litevent->display_grade !== ''
                            ? $litevent->display_grade
                            : ($litevent->grade < 7 ? $litevent->grade_lcl : '');

        $eventDetailsCell = $this->dom->createElement('td');
        $eventDetailsCell->setAttribute('style', 'background-color:' . $litevent->color[0] . ';' . (in_array($litevent->color[0], $highContrast) ? 'color:white;' : 'color:black;'));
        $eventDetailsContents = $this->dom->createDocumentFragment();
        $eventDetailsContents->appendXML($litevent->name . $currentCycle . ' - <i>' . implode(' ' . dgettext('webcalendar', 'or') . ' ', $litevent->color_lcl) . '</i><br /><i>' . $litevent->common_lcl . '</i>');
        $eventDetailsCell->appendChild($eventDetailsContents);
        $tr->appendChild($eventDetailsCell);

        // Fifth column contains the liturgical grade or rank of the celebration
        $liturgicalGradeCell = $this->dom->createElement('td');
        $liturgicalGradeCell->setAttribute('style', 'background-color:' . $litevent->color[0] . ';' . (in_array($litevent->color[0], $highContrast) ? 'color:white;' : 'color:black;'));
        $liturgicalGradeCell->appendChild($this->dom->createTextNode($displayGrade));
        $tr->appendChild($liturgicalGradeCell);

        if ($this->psalterWeekGrouping && $newPsalterWeek) {
            // Sixth column is only created when Psalter week is enabled
            $psalterWeekCell = $this->dom->createElement('td');
            $romNumPsalterWeek = self::PSALTER_WEEK[$litevent->psalter_week];
            $psalterWeekCell->appendChild($this->dom->createTextNode($romNumPsalterWeek));
            $psalterWeekCell->setAttribute('rowspan', $cw + 1);
            $tr->appendChild($psalterWeekCell);
            $newPsalterWeek = false;
        }

        return $tr;
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
        $this->dom->appendChild($table);

        if (false === $this->hideHeaderRow) {
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
            $theadRow->appendChild($th3);

            $th4 = $this->dom->createElement('th');
            $th4->appendChild($this->dom->createTextNode(dgettext('webcalendar', 'Liturgical Grade')));
            $theadRow->appendChild($th4);

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


        $currentMonth = 0; //1=January, ... 12=December; start wit a certain non valid value, so that the first iteration will trigger a new month
        $currentSeason = ''; //ADVENT, LENT, ORDINARY_TIME, EASTER, EASTER_TRIDUUM, CHRISTMAS; start with a certain non valid value, so that the first iteration will trigger a new season
        $currentPsalterWeek = 5; //start with a certain non valid value, so that the first iteration will trigger a new psalter week
        $newMonth = false;
        $newSeason = false;
        $newPsalterWeek = false;

        // Loop through the liturgical event keys
        for ($keyindex = 0; $keyindex < count($this->LitCalKeys); $keyindex++) {
            $this->daysCreated++;
            $keyname = $this->LitCalKeys[$keyindex];
            $litevent = $this->LiturgicalCalendar->litcal->$keyname;

            // Check if the liturgical event has liturgical_season or liturgical_season_lcl properties (unfortunately not all events have them),
            // and if not determine the liturgical season and retrieve the localized form from another event with the same season.
            if (!property_exists($litevent, "liturgical_season") || $litevent->liturgical_season === null || $litevent->liturgical_season === "") {
                $litevent->liturgical_season = $this->determineSeason($litevent);
            }
            if (!property_exists($litevent, "liturgical_season_lcl") || $litevent->liturgical_season_lcl === null || $litevent->liturgical_season_lcl === "") {
                foreach ($this->LiturgicalCalendar->litcal as $item) {
                    if ($item->event_idx !== $litevent->event_idx && $item->liturgical_season === $litevent->liturgical_season) {
                        $litevent->liturgical_season_lcl = $item->liturgical_season_lcl;
                        break;
                    }
                }
            }


            // Check if we are at the start of a new month, and if so count how many events we have in that same month,
            // so we can display the Month table cell with the correct colspan when firstColumnGrouping is BY_MONTH.
            $eventMonth = (int) $litevent->date->format('n');
            if ($eventMonth !== $currentMonth) {
                $newMonth = true;
                $currentMonth = $eventMonth;
                /** @var int $cm Count events in the same month */
                $cm = 0;
                $this->countSameMonthEvents($keyindex, $cm);
            }

            // Check if we are at the start of a new season, and if so count how many events we have in that same season,
            // so we can display the Season table cell with the correct colspan when firstColumnGrouping is BY_LITURGICAL_SEASON.
            if ($litevent->liturgical_season !== $currentSeason) {
                $newSeason = true;
                $currentSeason = $litevent->liturgical_season;
                /** @var int $cs Count events in the same season */
                $cs = 0;
                $this->countSameSeasonEvents($keyindex, $cs);
            }

            // Check if we are at the start of a new Psalter week, and if so count how many events we have with the same Psalter week,
            // so we can display the Psalter week table cell with the correct colspan
            if ($litevent->psalter_week !== $currentPsalterWeek) {
                $newPsalterWeek = true;
                /** @var int $cw Count events in the same psalter week */
                $cw = 0;
                $currentPsalterWeek = $litevent->psalter_week;
                $this->countSamePsalterWeekEvents($keyindex, $cw);
            }

            // Check if we have more than one event on the same day, such as optional memorials,
            // so we can display the Date table cell with the correct colspan.
            /** @var int $cd Count events in the same day */
            $cd = 0;
            $this->countSameDayEvents($keyindex, $cd);

            if ($cd > 0) {
                /** @var int $ev Index of the liturgical event within the same day $cd count. */
                for ($ev = 0; $ev <= $cd; $ev++) {
                    $keyname = $this->LitCalKeys[$keyindex];
                    $litevent = $this->LiturgicalCalendar->litcal->$keyname;

                    // Check if we are at the start of a new season, and if so count how many events we have in that same season,
                    // so we can display the Season table cell with the correct colspan when firstColumnGrouping is BY_LITURGICAL_SEASON.
                    if ($litevent->liturgical_season !== $currentSeason) {
                        $newSeason = true;
                        $currentSeason = $litevent->liturgical_season;
                        /** @var int $cs Count events in the same season */
                        $cs = 0;
                        $this->countSameSeasonEvents($keyindex, $cs);
                    }

                    // Check if we are at the start of a new Psalter week, and if so count how many events we have with the same Psalter week,
                    // so we can display the Psalter week table cell with the correct colspan
                    if ($litevent->psalter_week !== $currentPsalterWeek) {
                        $newPsalterWeek = true;
                        /** @var int $cw Count events in the same psalter week */
                        $cw = 0;
                        $currentPsalterWeek = $litevent->psalter_week;
                        $this->countSamePsalterWeekEvents($keyindex, $cw);
                    }

                    $tr = $this->buildTableRow($litevent, $newMonth, $newSeason, $newPsalterWeek, $cd, $cm, $cs, $cw, $ev);
                    $tbody->appendChild($tr);
                    $keyindex++;
                }
                $keyindex--;
            } else {
                // Only a single liturgical event on this day. No need for an event index.
                $tr = $this->buildTableRow($litevent, $newMonth, $newSeason, $newPsalterWeek, $cd, $cm, $cs, $cw, null);
                $tbody->appendChild($tr);
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
