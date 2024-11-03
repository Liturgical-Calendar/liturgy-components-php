<?php

namespace LiturgicalCalendar\Components;

use LiturgicalCalendar\Components\WebCalendar\LiturgicalEvent;

class WebCalendar
{
    public string $locale                   = 'en-US';
    private string $baseLocale              = 'en';
    private ?string $currentSetLocale       = null;
    private ?string $globalLocale           = null;
    private ?string $currentTextDomainPath  = null;
    private ?string $expectedTextDomainPath = null;
    private ?object $LiturgicalCalendar     = null;
    private array $LitCalKeys               = [];

    public const DAYS_OF_THE_WEEK_LATIN = [
        "dies Solis",
        "dies Lunæ",
        "dies Martis",
        "dies Mercurii",
        "dies Iovis",
        "dies Veneris",
        "dies Saturni"
    ];

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
     * Constructs a new WebCalendar object.
     *
     * This constructor initializes the WebCalendar instance by validating
     * the provided Liturgical Calendar object or array, which must contain specific properties or keys.
     * If the provided Liturgical Calendar is an array, it will cast it to an object.
     * It ensures that 'litcal', 'settings', 'metadata', 'messages', and
     * 'locale' properties exist in the provided or resulting object. Each item in the 'litcal'
     * object is converted to a LiturgicalEvent object.
     *
     * After validation and initialization, it sets the locale using the
     * specified locale from the 'settings' property.
     *
     * @param array $LiturgicalCalendar The object or associative array containing the
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

        $this->LiturgicalCalendar = $LiturgicalCalendar;
        $this->setLocale($LiturgicalCalendar->settings->locale);
    }

    /**
     * Sets the locale for the component.
     *
     * The locale is set as follows:
     *    1. The global locale is set using setlocale(LC_ALL, 0).
     *    2. The locale is set to the parameter $locale, which is the locale string to be used.
     *    3. The base locale is set using Locale::getPrimaryLanguage($locale).
     *    4. An array of possible locale strings is created, with the following order of preference:
     *        1. The locale string with '.utf8' appended.
     *        2. The locale string with '.UTF-8' appended.
     *        3. The locale string without any suffix.
     *        4. The base locale string with '_' followed by its uppercase version and '.utf8' appended.
     *        5. The base locale string with '_' followed by its uppercase version and '.UTF-8' appended.
     *        6. The base locale string with '_' followed by its uppercase version without any suffix.
     *        7. The base locale string with '.utf8' appended.
     *        8. The base locale string with '.UTF-8' appended.
     *        9. The base locale string without any suffix.
     *    5. The locale is set to the first of the above that is supported by the system using setlocale(LC_ALL, $localeArray).
     *    6. The path to the textdomain is set to __DIR__ . "/WebCalendar/i18n" and bound to the textdomain "webcalendar".
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
     * @param int $currentKeyIndex The current position in the array of liturgical events.
     * @param int $cc [reference] The count of subsequent liturgical events in the same day.
     */
    private function countSameDayEvents($currentKeyIndex, &$cc)
    {
        $EventsObject = $this->LiturgicalCalendar->litcal;
        $currentEvent = $EventsObject->{$this->LitCalKeys[$currentKeyIndex]};
        if ($currentKeyIndex < count($this->LitCalKeys) - 1) {
            $nextEvent = $EventsObject->{$this->LitCalKeys[$currentKeyIndex + 1]};
            if ($nextEvent->date == $currentEvent->date) {
                $cc++;
                $this->countSameDayEvents($currentKeyIndex + 1, $cc);
            }
        }
    }

    /**
     * Recursively counts the number of subsequent liturgical events in the same month.
     *
     * @param int $currentKeyIndex
     * @param int $cm [reference] The count of subsequent liturgical events in the same month.
     */
    private function countSameMonthEvents($currentKeyIndex, &$cm)
    {
        $EventsObject = $this->LiturgicalCalendar->litcal;
        $currentEvent = $EventsObject->{$this->LitCalKeys[$currentKeyIndex]};
        if ($currentKeyIndex < count($this->LitCalKeys) - 1) {
            $nextEvent = $EventsObject->{$this->LitCalKeys[$currentKeyIndex + 1]};
            if ($nextEvent->date->format('n') == $currentEvent->date->format('n')) {
                $cm++;
                $this->countSameMonthEvents($currentKeyIndex + 1, $cm);
            }
        }
    }

    /**
     * Determines the liturgical color for the Liturgical Season, to apply to liturgical events within that season.
     *
     * @param LiturgicalEvent $litevent The liturgical event for which the color is determined.
     * @return string The color representing the liturgical season (e.g., "green", "purple", "white").
     */
    private function getSeasonColor(LiturgicalEvent $litevent)
    {
        $seasonColor = 'green';
        if (($litevent->date > $this->LiturgicalCalendar->litcal->Advent1->date  && $litevent->date < $this->LiturgicalCalendar->litcal->Christmas->date) || ($litevent->date > $this->LiturgicalCalendar->litcal->AshWednesday->date && $litevent->date < $this->LiturgicalCalendar->litcal->Easter->date)) {
            $seasonColor = 'purple';
        } elseif ($litevent->date > $this->LiturgicalCalendar->litcal->Easter->date && $litevent->date < $this->LiturgicalCalendar->litcal->Pentecost->date) {
            $seasonColor = 'white';
        } elseif ($litevent->date > $this->LiturgicalCalendar->litcal->Christmas->date || $litevent->date < $this->LiturgicalCalendar->litcal->BaptismLord->date) {
            $seasonColor = 'white';
        }
        return $seasonColor;
    }

    /**
     * Outputs a table row for the given liturgical event from the requested Liturgical Calendar
     *
     * @param LiturgicalEvent $litevent The liturgical event to display
     * @param bool $newMonth Whether we are starting a new month
     * @param int $cc Count of Celebrations on the same day
     * @param int $cm Count of Celebrations on the same month
     * @param int $ev Whether we need to set the rowspan based on the number of liturgical events within the same day. If null, we are displaying only a single liturgical event and we do not need to set rowspan, otherwise we set the rowspan on the the first liturgical event based on how many liturgical events there are in the given day.
     */
    private function buildTableContents(LiturgicalEvent $litevent, &$newMonth, $cc, $cm, $ev = null)
    {
        $monthFmt = \IntlDateFormatter::create($this->locale, \IntlDateFormatter::FULL, \IntlDateFormatter::FULL, 'UTC', \IntlDateFormatter::GREGORIAN, 'MMMM');
        $dateFmt  = \IntlDateFormatter::create($this->locale, \IntlDateFormatter::FULL, \IntlDateFormatter::FULL, 'UTC', \IntlDateFormatter::GREGORIAN, 'EEEE d MMMM yyyy');
        $highContrast = ['purple', 'red', 'green'];
        $seasonColor = $this->getSeasonColor($litevent);
        echo '<tr style="background-color:' . $seasonColor . ';' . (in_array($seasonColor, $highContrast) ? 'color:white;' : '') . '">';
        if ($newMonth) {
            $monthRwsp = $cm + 1;
            echo '<td class="rotate" rowspan = "' . $monthRwsp . '"><div>' . ($this->baseLocale === 'la' ? strtoupper(self::MONTHS_LATIN[ (int)$litevent->date->format('n') ]) : strtoupper($monthFmt->format($litevent->date->format('U'))) ) . '</div></td>';
            $newMonth = false;
        }
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
        if ($ev === null) {
            echo '<td class="dateEntry">' . $dateString . '</td>';
        } elseif ($ev === 0) {
            echo '<td class="dateEntry" rowspan="' . ($cc + 1) . '">' . $dateString . '</td>';
        }
        $currentCycle = property_exists($litevent, "liturgical_year") && $litevent->liturgical_year !== null && $litevent->liturgical_year !== "" ? " (" . $litevent->liturgical_year . ")" : "";
        $displayGrade = $litevent->display_grade !== ''
                            ? $litevent->display_grade
                            : ($litevent->grade < 7 ? $litevent->grade_lcl : '');
        echo '<td style="background-color:' . $litevent->color[0] . ';' . (in_array($litevent->color[0], $highContrast) ? 'color:white;' : 'color:black;') . '">' . $litevent->name . $currentCycle . ' - <i>' . implode(' ' . dgettext('webcalendar', 'or') . ' ', $litevent->color_lcl) . '</i><br /><i>' . $litevent->common_lcl . '</i></td>';
        echo '<td style="background-color:' . $litevent->color[0] . ';' . (in_array($litevent->color[0], $highContrast) ? 'color:white;' : 'color:black;') . '">' . $displayGrade . '</td>';
        echo '</tr>';
    }

    public function buildTable()
    {
        echo '<table id="LitCalTable">';
        echo '<thead><tr><th>' . dgettext('webcalendar', "Month") . '</th><th>' . dgettext('webcalendar', "Date") . '</th><th>' . dgettext('webcalendar', "Celebration") . '</th><th>' . dgettext('webcalendar', "Liturgical Grade") . '</th></tr></thead>';
        echo '<tbody>';


        $dayCnt = 0;
        $currentMonth = 0; //1=January, ... 12=December
        $newMonth = false;

        for ($keyindex = 0; $keyindex < count($this->LitCalKeys); $keyindex++) {
            $dayCnt++;
            $keyname = $this->LitCalKeys[$keyindex];
            $litevent = $this->LiturgicalCalendar->litcal->$keyname;
            //If we are at the start of a new month, count how many events we have in that same month, so we can display the Month table cell
            if ((int) $litevent->date->format('n') !== $currentMonth) {
                $newMonth = true;
                $currentMonth = (int) $litevent->date->format('n');
                $cm = 0;
                $this->countSameMonthEvents($keyindex, $cm);
            }

            //Let's check if we have more than one event on the same day, such as optional memorials...
            $cc = 0;
            $this->countSameDayEvents($keyindex, $cc);
            if ($cc > 0) {
                for ($ev = 0; $ev <= $cc; $ev++) {
                    $keyname = $this->LitCalKeys[$keyindex];
                    $litevent = $this->LiturgicalCalendar->litcal->$keyname;
                    $this->buildTableContents($litevent, $newMonth, $cc, $cm, $ev);
                    $keyindex++;
                }
                $keyindex--;
            } else {
                $this->buildTableContents($litevent, $newMonth, $cc, $cm, null);
            }
        }

        echo '</tbody></table>';

        echo '<div style="text-align:center;border:3px ridge Green;background-color:LightBlue;width:75%;margin:10px auto;padding:10px;">' . $dayCnt . ' event days created</div>';
        $this->resetGlobalLocale();
    }
}
