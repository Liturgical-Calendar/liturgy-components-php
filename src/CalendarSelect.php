<?php

namespace LiturgicalCalendar\Components;

class CalendarSelect
{
    private const METADATA_URL = 'https://litcal.johnromanodorazio.com/api/dev/calendars';
    private static ?array $calendarIndex         = null;
    private static array $nationalCalendars      = [];
    private static array $diocesanCalendars      = [];
    private array $nationalCalendarsWithDioceses = [];
    private array $nationOptions                 = [];
    private array $dioceseOptions                = [];
    private array $dioceseOptionsGrouped         = [];
    private string $locale                       = 'en';
    private ?string $metadataUrl                 = null;

    /**
     * Instantiates the calendar select with metadata from the Liturgical Calendar API.
     *
     * @param array $options An associative array that can have the following keys:
     *                        - 'url': The URL of the liturgical calendar metadata API endpoint.
     *                                 Defaults to https://litcal.johnromanodorazio.com/api/dev/calendars.
     *                        - 'locale': The locale to use for the calendar select. Defaults to 'en'.
     *                                     This is the locale that will be used to translate and order the names of the countries.
     *                                     This should be a valid PHP locale string, such as 'en' or 'es' or 'en_US' or 'es_ES'.
     *
     * @throws \Exception If there is an error fetching or decoding the metadata from the Liturgical Calendar API.
     */
    public function __construct($options = [
        'url'    => self::METADATA_URL,
        'locale' => 'en'
    ])
    {
        if (isset($options['locale'])) {
            $options['locale'] = \Locale::canonicalize($options['locale']);
            if (!self::isValidLocale($options['locale'])) {
                throw new \Exception("Invalid locale: {$options['locale']}");
            }
        }

        $this->locale = $options['locale'] ?? 'en';

        $this->metadataUrl = $options['url'] ?? self::METADATA_URL;
        // If we haven't cached the metadata yet, or the request has changed, fetch it from the API
        if ($this->metadataUrl !== self::METADATA_URL || self::$calendarIndex === null) {
            $metadataRaw = file_get_contents($this->metadataUrl);
            if ($metadataRaw === false) {
                throw new \Exception("Error fetching metadata from {$this->metadataUrl}");
            }
            $metadataJSON = json_decode($metadataRaw, true);
            if (JSON_ERROR_NONE !== json_last_error()) {
                throw new \Exception("Error decoding metadata from {$this->metadataUrl}: " . json_last_error_msg());
            }
            if (array_key_exists('litcal_metadata', $metadataJSON) === false) {
                throw new \Exception("Missing 'litcal_metadata' in metadata from {$this->metadataUrl}");
            }
            if (array_key_exists('diocesan_calendars', $metadataJSON['litcal_metadata']) === false) {
                throw new \Exception("Missing 'diocesan_calendars' in metadata from {$this->metadataUrl}");
            }
            if (array_key_exists('national_calendars', $metadataJSON['litcal_metadata']) === false) {
                throw new \Exception("Missing 'national_calendars' in metadata from {$this->metadataUrl}");
            }
            [ 'litcal_metadata' => self::$calendarIndex ] = $metadataJSON;
            [ 'diocesan_calendars' => self::$diocesanCalendars, 'national_calendars' => self::$nationalCalendars ] = self::$calendarIndex;
        }
        $this->buildAllOptions();
    }

    /**
     * Returns true if the given locale is valid, and false otherwise.
     *
     * A locale is valid if it is either 'la' or 'la_VA' (for Latin) or
     * if it is a valid PHP locale string according to the {@link https://www.php.net/manual/en/class.locale.php Locale class}.
     * Note that in order for a locale to be considered valid, it must be installed in the current server environment.
     *
     * @param string $locale The locale to check.
     *
     * @return bool
     */
    public static function isValidLocale($locale)
    {
        $latin = ['la', 'la_VA'];
        $AllAvailableLocales = array_filter(\ResourceBundle::getLocales(''), fn ($value) => strpos($value, 'POSIX') === false);
        return in_array($locale, $latin) || in_array($locale, $AllAvailableLocales);
    }


    /**
     * Returns true if we have already stored a national calendar with dioceses for the given nation,
     * that is when diocesan calendars have the given nation as their calendar_id, and false otherwise.
     *
     * @param string $nation The nation to check.
     *
     * @return bool
     */
    private function hasNationalCalendarWithDioceses($nation)
    {
        return count($this->nationalCalendarsWithDioceses)
                && count(array_filter($this->nationalCalendarsWithDioceses, fn($item) => $item['calendar_id'] === $nation)) > 0;
    }

    /**
     * Adds a national calendar to the list of national calendars with dioceses.
     * This will also initialize diocese select options for the given nation.
     *
     * @param string $nation The nation for which we should add the national calendar.
     */
    private function addNationalCalendarWithDioceses($nation)
    {
        $nationalCalendar = array_values(array_filter(self::$nationalCalendars, fn($item) => $item['calendar_id'] === $nation));
        array_push($this->nationalCalendarsWithDioceses, $nationalCalendar[0]);
        $this->dioceseOptions[$nation] = [];
    }

    /**
     * Adds a select option for a national calendar to the list of national calendar select options.
     * This will generate an <option> element with the data of the given national calendar.
     *
     * @param array $nationalCalendar The national calendar for which we will add a select options.
     *                                This should be an associative array with the following keys:
     *                                - 'calendar_id': The ID for the calendar (ISO 3166-1 alpha-2 code for the country).
     * @param bool $selected Whether or not the option should be selected by default.
     */
    private function addNationOption($nationalCalendar, $selected = false)
    {
        $selectedStr = $selected ? ' selected' : '';
        $optionOpenTag = "<option data-calendartype=\"nationalcalendar\" value=\"{$nationalCalendar['calendar_id']}\"{$selectedStr}>";
        $optionContents = \Locale::getDisplayRegion('-' . $nationalCalendar['calendar_id'], $this->locale);
        $optionCloseTag = "</option>";
        $optionHtml = "{$optionOpenTag}{$optionContents}{$optionCloseTag}";
        array_push($this->nationOptions, $optionHtml);
    }

    /**
     * Adds a select option for a diocesan calendar to the list of diocese select options for the given diocesan calendar.
     * This will generate an <option> element with the data of the given diocesan calendar.
     *
     * @param array $item The diocesan calendar for which we will add a select options.
     *                    This should be an associative array with the following keys:
     *                    - 'calendar_id': The ID for the calendar (corresponding to the uppercase name of the diocese).
     *                    - 'nation': The nation that the diocesan calendar belongs to.
     *                    - 'diocese': The name of the diocese.
     */
    private function addDioceseOption($item)
    {
        $optionOpenTag = "<option data-calendartype=\"diocesancalendar\" value=\"{$item['calendar_id']}\">";
        $optionContents = $item['diocese'];
        $optionCloseTag = "</option>";
        $optionHtml = "{$optionOpenTag}{$optionContents}{$optionCloseTag}";
        array_push($this->dioceseOptions[$item['nation']], $optionHtml);
    }

    /**
     * Builds all options for the calendar select.
     *
     * @param array $diocesan_calendars An array of associative arrays, each representing a diocesan calendar.
     *                                  Each associative array should have the following keys:
     *                                  - 'calendar_id': The ID for the calendar (corresponding to the uppercase name of the diocese).
     *                                  - 'nation': The nation that the diocesan calendar belongs to.
     *                                  - 'diocese': The name of the diocese.
     * @param array $national_calendars An array of associative arrays, each representing a national calendar.
     *                                  Each associative array should have the following keys:
     *                                  - 'calendar_id': The ID for the calendar (ISO 3166-1 alpha-2 code for the country).
     */
    private function buildAllOptions()
    {
        $col = \Collator::create($this->locale);
        $col->setStrength(\Collator::PRIMARY); // only compare base characters; not accents, lower/upper-case, ...

        foreach (self::$diocesanCalendars as $diocesanCalendar) {
            if (!$this->hasNationalCalendarWithDioceses($diocesanCalendar['nation'])) {
                // we add all nations with dioceses to the nations list
                $this->addNationalCalendarWithDioceses($diocesanCalendar['nation']);
            }
            $this->addDioceseOption($diocesanCalendar);
        }
        usort(self::$nationalCalendars, fn($a, $b) => $col->compare(
            \Locale::getDisplayRegion("-" . $a['calendar_id'], $this->locale),
            \Locale::getDisplayRegion("-" . $b['calendar_id'], $this->locale)
        ));
        foreach (self::$nationalCalendars as $nationalCalendar) {
            if (!$this->hasNationalCalendarWithDioceses($nationalCalendar['calendar_id'])) {
                // This is the first time we call CalendarSelect::addNationOption().
                // This will ensure that the VATICAN (or any other nation without any diocese) will be added as the first option(s).
                // We also ensure that the VATICAN is always the default selected option
                if ('VA' === $nationalCalendar['calendar_id']) {
                    $this->addNationOption($nationalCalendar, true);
                } else {
                    $this->addNationOption($nationalCalendar);
                }
            }
        }

        // now we can add the options for the nations in the #calendarNationsWithDiocese list
        // that is to say, nations that have dioceses
        usort($this->nationalCalendarsWithDioceses, fn($a, $b) => $col->compare(
            \Locale::getDisplayRegion('-' . $a['calendar_id'], $this->locale),
            \Locale::getDisplayRegion('-' . $b['calendar_id'], $this->locale)
        ));
        foreach ($this->nationalCalendarsWithDioceses as $nationalCalendar) {
            $this->addNationOption($nationalCalendar);
            $optgroupLabel = \Locale::getDisplayRegion("-" . $nationalCalendar['calendar_id'], $this->locale);
            $optgroupOpenTag = "<optgroup label=\"{$optgroupLabel}\">";
            $optgroupContents = implode('', $this->dioceseOptions[$nationalCalendar['calendar_id']]);
            $optgroupCloseTag = "</optgroup>";
            array_push($this->dioceseOptionsGrouped, "{$optgroupOpenTag}{$optgroupContents}{$optgroupCloseTag}");
        }
    }

    /**
     * Returns the HTML for the given type of select options.
     *
     * @param string $key The type of select options to return.  Valid values are
     *                    'nations', 'diocesesGrouped', or 'all'.
     *
     * @return string The HTML for the select options.
     */
    public function getOptions(string $key): string
    {
        if ($key === 'nations') {
            return implode('', $this->nationOptions);
        }

        if ($key === 'diocesesGrouped') {
            return implode('', $this->dioceseOptionsGrouped);
        }

        if ($key === 'all') {
            return implode('', $this->nationOptions) . implode('', $this->dioceseOptionsGrouped);
        }

        return "<option>$key</option>";
    }

    /**
     * Returns a complete HTML select element for the given options.
     *
     * @param array $options An associative array of options.  Valid keys are:
     *                       - 'class': The class to apply to the select element.
     *                       - 'id': The id to apply to the select element.
     *                       - 'options': The type of select options to return.  Valid values are
     *                                    'nations', 'diocesesGrouped', or 'all'.
     *                       - 'label': A boolean indicating whether to include a label element or not.
     *                       - 'labelStr': The string to use for the label element.
     *
     * @return string The HTML for the select element.
     */
    public function getSelect(array $options = []): string
    {
        $defaultOptions = [
            'class'    => 'calendarSelect',
            'id'       => 'calendarSelect',
            'options'  => 'all',
            'label'    => false,
            'labelStr' => 'Select a calendar'
        ];
        foreach ($options as $key => $value) {
            if (in_array($key, ['class', 'id', 'options', 'labelStr'])) {
                $options[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            } elseif ($key === 'label') {
                $options[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
        }
        $options = array_merge($defaultOptions, $options);
        $optionsHtml = $this->getOptions($options['options']);
        return ($options['label'] ? "<label for=\"{$options['id']}\">{$options['labelStr']}</label>" : '')
            . "<select id=\"{$options['id']}\" class=\"{$options['class']}\">{$optionsHtml}</select>";
    }

    /**
     * Retrieves the metadata URL used by the calendar select instance.
     *
     * @return string The metadata URL.
     */
    public function getMetadataUrl()
    {
        return $this->metadataUrl;
    }

    /**
     * Returns the locale used by the calendar select instance.
     *
     * @return string The locale, a valid PHP locale string such as 'en' or 'es' or 'en_US' or 'es_ES'.
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * This function is called after the package has been installed.
     * It will print some text to the console.
     */
    public static function postInstall(): void
    {
        printf("\t\033[4m\033[1;44mCatholic Liturgical Calendar components\033[0m\n");
        printf("\t\033[0;33mAd Majorem Dei Gloriam\033[0m\n");
        printf("\t\033[0;36mOremus pro Pontifice nostro Francisco Dominus\n\tconservet eum et vivificet eum et beatum faciat eum in terra\n\tet non tradat eum in animam inimicorum ejus\033[0m\n");
    }
}
