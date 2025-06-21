<?php

namespace LiturgicalCalendar\Components;

use LiturgicalCalendar\Components\CalendarSelect\OptionsType;

/**
 * A class to generate a select element for selecting a Liturgical Calendar.
 *
 * This class will generate a select element with options for all the national
 * calendars, as well as the diocesan calendars for each nation.
 *
 * Public Methods:
 * @see LiturgicalCalendar\Components\CalendarSelect::__construct() Initializes the CalendarSelect object with default settings.
 * @see LiturgicalCalendar\Components\CalendarSelect::setUrl() Sets the URL of the liturgical calendar metadata API endpoint.
 * @see LiturgicalCalendar\Components\CalendarSelect::selectedOption() Sets the selected option.
 * @see LiturgicalCalendar\Components\CalendarSelect::locale() Sets the locale for the calendar select.
 * @see LiturgicalCalendar\Components\CalendarSelect::class() Sets the class for the calendar select.
 * @see LiturgicalCalendar\Components\CalendarSelect::id() Sets the ID for the select element.
 * @see LiturgicalCalendar\Components\CalendarSelect::name() Sets the name for the select element.
 * @see LiturgicalCalendar\Components\CalendarSelect::setOptions() Sets the type of option elements for the select element.
 * @see LiturgicalCalendar\Components\CalendarSelect::nationFilter() Sets the nation to filter the diocese options to.
 * @see LiturgicalCalendar\Components\CalendarSelect::label() Configures whether to show the label.
 * @see LiturgicalCalendar\Components\CalendarSelect::labelText() Sets the text for the label.
 * @see LiturgicalCalendar\Components\CalendarSelect::allowNull() Allows or disallows null selection.
 * @see LiturgicalCalendar\Components\CalendarSelect::disabled() Sets whether the select element is disabled.
 * @see LiturgicalCalendar\Components\CalendarSelect::isValidLocale() Checks if the given locale is valid.
 * @see LiturgicalCalendar\Components\CalendarSelect::getSelect() Returns the HTML for the select element.
 * @see LiturgicalCalendar\Components\CalendarSelect::getMetadataUrl() Returns the URL of the liturgical calendar metadata API endpoint.
 * @see LiturgicalCalendar\Components\CalendarSelect::getLocale() Returns the locale used by the calendar select instance.
 * @see LiturgicalCalendar\Components\CalendarSelect::isValidDioceseForNation() Checks if the given diocese is valid for the given nation.
 *
 * @package LiturgicalCalendar\Components
 * @author John Romano D'Orazio <priest@johnromanodorazio.com>
 */
class CalendarSelect
{
    private const METADATA_URL = 'https://litcal.johnromanodorazio.com/api/dev/calendars';
    private static ?array $calendarIndex           = null;
    private static array $nationalCalendars        = [];
    private static array $nationalCalendarsKeys    = [];
    private static array $diocesanCalendars        = [];
    private array $nationalCalendarsWithDioceses   = [];
    private array $nationOptions                   = [];
    private array $dioceseOptions                  = [];
    private array $dioceseOptionsGrouped           = [];
    private string $locale                         = 'en';
    private ?string $metadataUrl                   = null;
    private ?string $nationFilterForDioceseOptions = null;
    private ?string $selectedOption                = null;
    private string $class                          = 'calendarSelect';
    private string $id                             = 'calendarSelect';
    private string $name                           = 'calendarSelect';
    private bool $label                            = false;
    private string $labelStr                       = 'Select a calendar';
    private string $labelClass                     = '';
    private bool $allowNull                        = false;
    private bool $disabled                         = false;
    private OptionsType $optionsType               = OptionsType::ALL;

    /**
     * Creates a new instance of the CalendarSelect class.
     *
     * The options array can contain the following keys:
     * - locale: string, the locale to use, defaults to 'en'
     * - url: string, the URL of the liturgical calendar metadata API endpoint,
     *        defaults to https://litcal.johnromanodorazio.com/api/dev/calendars
     * - class: string, the class to apply to the select element, defaults to 'calendarSelect'
     * - id: string, the id to apply to the select element, defaults to 'calendarSelect'
     * - name: string, the name to apply to the select element, defaults to 'calendarSelect'
     * - nationFilter: string, the nation to filter the diocese options to, defaults to false
     * - setOptions: OptionsType, the type of options to set, defaults to OptionsType::ALL
     * - selectedOption: string, the selected option, defaults to false
     * - label: boolean, whether to include a label element, defaults to false
     * - labelStr: string, the string to use for the label element, defaults to 'Select a calendar'
     * - allowNull: boolean, whether to allow the null value in the select element, defaults to false
     *
     * @param array $options The options for the instance.
     */
    public function __construct($options = ['url' => self::METADATA_URL])
    {
        if (isset($options['locale'])) {
            $this->locale($options['locale']);
        }

        if (isset($options['url']) && $options['url'] !== self::METADATA_URL) {
            $url = filter_var($options['url'], FILTER_VALIDATE_URL);
            if (false === $url) {
                throw new \Exception("Invalid URL: {$options['url']}");
            }
            $url = rtrim($url, '/');
            $this->setUrl($url . '/calendars');
        } else {
            $this->setUrl(self::METADATA_URL);
        }

        if (isset($options['class'])) {
            $this->class = htmlspecialchars($options['class'], ENT_QUOTES, 'UTF-8');
        }

        if (isset($options['id'])) {
            $this->id = htmlspecialchars($options['id'], ENT_QUOTES, 'UTF-8');
        }

        if (isset($options['name'])) {
            $this->name = htmlspecialchars($options['name'], ENT_QUOTES, 'UTF-8');
        }

        if (isset($options['nationFilter'])) {
            if (false === in_array($options['nationFilter'], self::$nationalCalendarsKeys, true)) {
                throw new \Exception("Invalid nation: {$options['nationFilter']}, valid values are: " . implode(', ', self::$nationalCalendarsKeys));
            }
            $this->nationFilterForDioceseOptions = $options['nationFilter'];
        }

        if (isset($options['setOptions'])) {
            $this->setOptions($options['optionsType']);
        }

        if (isset($options['selectedOption'])) {
            $this->selectedOption = htmlspecialchars($options['selectedOption'], ENT_QUOTES, 'UTF-8');
        }

        if (isset($options['label'])) {
            $this->label = filter_var($options['label'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($options['labelStr'])) {
            $this->labelStr = htmlspecialchars($options['labelStr'], ENT_QUOTES, 'UTF-8');
        }

        if (isset($options['labelClass'])) {
            $this->labelClass = htmlspecialchars($options['labelClass'], ENT_QUOTES, 'UTF-8');
        }

        if (isset($options['allowNull'])) {
            $this->allowNull = filter_var($options['allowNull'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($options['disabled'])) {
            $this->disabled = filter_var($options['disabled'], FILTER_VALIDATE_BOOLEAN);
        }
    }

    /**
     * Sets the URL for the metadata API endpoint.
     *
     * This method updates the metadata URL and fetches the metadata
     * using the updated URL.
     *
     * @param string $url The URL of the metadata API endpoint.
     *
     * @return self
     */
    public function setUrl($url): self
    {
        $this->metadataUrl = $url;
        $this->fetchMetadata();
        return $this;
    }

    /**
     * Sets the selected option of the select element.
     *
     * @param string $value The value of the selected option.
     *
     * @return self
     */
    public function selectedOption($value): self
    {
        $this->selectedOption = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        return $this;
    }

    /**
     * Sets the locale to use when generating the select element.
     *
     * The locale is used to translate the names of the calendars.
     * The locale should be a valid locale string as defined by the ICU
     * standards. The locale should be a string in the format of "language_REGION",
     * where language is the language code and REGION is the region code.
     * For example, "en_US" for English in the United States, "fr_FR" for French in France,
     * or "it_IT" for Italian in Italy.
     *
     * @param string $locale the locale to use when generating the select element
     *
     * @throws \Exception if the locale is invalid
     *
     * @return self
     */
    public function locale(string $locale): self
    {
        $locale = \Locale::canonicalize($locale);
        if (!self::isValidLocale($locale)) {
            throw new \Exception("Invalid locale: {$locale}");
        }
        $this->locale = $locale;
        return $this;
    }

    /**
     * Sets the class attribute of the select element.
     *
     * @param string $className the class attribute of the select element
     *
     * @return self
     */
    public function class(string $className): self
    {
        $this->class = htmlspecialchars($className, ENT_QUOTES, 'UTF-8');
        return $this;
    }

    /**
     * Sets the id attribute of the select element.
     *
     * @param string $id the id attribute of the select element
     * @return self
     */
    public function id(string $id): self
    {
        $this->id = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        return $this;
    }

    /**
     * Sets the name attribute of the select element.
     *
     * @param string $name the name attribute of the select element
     * @return $this
     */
    public function name(string $name): self
    {
        $this->name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        return $this;
    }

    /**
     * Sets the type of options to return.
     *
     * @param OptionsType $optionsType
     *
     * @return $this
     *
     * @throws \Exception If the options type is not valid.
     * @throws \Exception If the options type is OptionsType::DIOCESES_FOR_NATION and nationFilter has not been set.
     */
    public function setOptions(OptionsType $optionsType): self
    {
        if (false === in_array($optionsType, OptionsType::cases(), true)) {
            throw new \Exception("Invalid options type: {$optionsType}, valid values are: " . implode(', ', OptionsType::cases()));
        }
        if (
            OptionsType::DIOCESES_FOR_NATION === $optionsType
            && (null === $this->nationFilterForDioceseOptions || empty($this->nationFilterForDioceseOptions))
        ) {
            throw new \Exception('When using the "DIOCESES_FOR_NATION" option, "setOptions" requires "nationFilter" to be set');
        }
        $this->optionsType = $optionsType;
        return $this;
    }

    /**
     * Sets the nation to filter the diocese options by.
     *
     * When the options type is OptionsType::DIOCESES_FOR_NATION, this method must be called to set the nation to filter the diocese options by.
     * If the nation is not a valid nation, an exception will be thrown.
     *
     * @param string $nation The nation to filter the diocese options by.
     *
     * @return $this
     *
     * @throws \Exception If the nation is not a valid nation.
     */
    public function nationFilter(string $nation): self
    {
        if (false === in_array($nation, self::$nationalCalendarsKeys, true)) {
            throw new \Exception("Invalid nation: {$nation}, valid values are: " . implode(', ', self::$nationalCalendarsKeys));
        }
        $this->nationFilterForDioceseOptions = $nation;
        return $this;
    }

    /**
     * Sets whether a label element will be included for the select element.
     *
     * @param bool $label Whether to include a label element for the select element.
     *
     * @return $this
     */
    public function label(bool $label = true): self
    {
        $this->label = $label;
        return $this;
    }

    /**
     * Sets the text of the label element for the select element.
     *
     * If the label element is not enabled, this value is ignored.
     *
     * @param string $text The text of the label element
     *
     * @return $this
     */
    public function labelText(string $text): self
    {
        $this->labelStr = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        return $this;
    }

    public function labelClass(string $labelClass): self
    {
        $this->labelClass = htmlspecialchars($labelClass, ENT_QUOTES, 'UTF-8');
        return $this;
    }

    /**
     * If set to true, the select element will include an empty option as the first option.
     *
     * This can be useful when you want to allow the user to select no option.
     *
     * @param bool $allowNull Whether to include an empty option as the first option.
     *
     * @return static
     */
    public function allowNull(bool $allowNull = true): self
    {
        $this->allowNull = $allowNull;
        return $this;
    }

    /**
     * Sets whether the select element should be disabled.
     *
     * @param bool $disabled Whether the select element should be disabled.
     *
     * @return static
     */
    public function disabled(bool $disabled = true): self
    {
        $this->disabled = $disabled;
        return $this;
    }

    /**
     * Returns true if the given locale is valid, and false otherwise.
     *
     * A locale is valid if it is either 'la' or 'la_VA' (for Latin) or
     * if it is a valid PHP locale string according to the {@link https://www.php.net/manual/en/class.locale.php Locale class}.
     * Note that in order for a locale to be considered valid, it must be installed in the current server environment.
     *
     * This method is declared as public simply to allow PHP Unit testing.
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
     * Fetches the metadata from the API if it has not been fetched yet.
     *
     * If the request url has changed since the last time it was fetched, it will be refetched.
     *
     * If the metadata is invalid, an exception is thrown.
     *
     * @throws \Exception If there is an error fetching or decoding the metadata,
     *                     or if the metadata is invalid.
     */
    private function fetchMetadata(): void
    {
        // If we haven't cached the metadata yet, or the request url has changed, fetch it from the API
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
            [
                'diocesan_calendars' => self::$diocesanCalendars,
                'national_calendars' => self::$nationalCalendars,
                'national_calendars_keys' => self::$nationalCalendarsKeys
            ] = self::$calendarIndex;
        }
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
     */
    private function addNationOption($nationalCalendar)
    {
        $selectedStr = '';
        if ($this->selectedOption === $nationalCalendar['calendar_id']) {
            $selectedStr = ' selected';
        }
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
        $selectedStr = '';
        if ($this->selectedOption === $item['calendar_id']) {
            $selectedStr = ' selected';
        }
        $optionOpenTag = "<option data-calendartype=\"diocesancalendar\" value=\"{$item['calendar_id']}\"{$selectedStr}>";
        $optionContents = $item['diocese'];
        $optionCloseTag = "</option>";
        $optionHtml = "{$optionOpenTag}{$optionContents}{$optionCloseTag}";
        array_push($this->dioceseOptions[$item['nation']], $optionHtml);
    }

    /**
     * Builds all of the options for the calendar select element.
     *
     * This function ensures that "Vatican" is always the first option in the select element when nations are included,
     * and that it is selected by default when allowNull is false.
     *
     * It also groups the diocesan calendars by nation, and ensures that the diocese options
     * are sorted alphabetically by the name of the nation.
     *
     * Finally, it ensures that the options for the nations in the nationalCalendarsWithDioceses
     * list are sorted alphabetically by the name of the nation.
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
                // This will ensure that the VATICAN (or any other nation without any diocese) will be added as the first option,
                // thus ensuring that VATICAN is always the default selected option when allowNull is false.
                $this->addNationOption($nationalCalendar);
            }
        }

        // now we can add the options for the nations in the nationalCalendarsWithDioceses list
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
     * @return string The HTML for the select options.
     */
    private function getOptions(): string
    {
        $this->buildAllOptions();
        switch ($this->optionsType) {
            case OptionsType::NATIONS:
                return implode('', $this->nationOptions);
            case OptionsType::DIOCESES:
                return implode('', $this->dioceseOptionsGrouped);
            case OptionsType::DIOCESES_FOR_NATION:
                if (null === $this->nationFilterForDioceseOptions || empty($this->nationFilterForDioceseOptions)) {
                    throw new \Exception('No selected nation');
                }
                if ('VA' === $this->nationFilterForDioceseOptions) {
                    $this->disabled = true;
                    return '';
                }
                return implode('', $this->dioceseOptions[$this->nationFilterForDioceseOptions] ?? []);
            case OptionsType::ALL:
            default:
                return implode('', $this->nationOptions) . implode('', $this->dioceseOptionsGrouped);
        }

        return "<option>{$this->optionsType->value}</option>";
    }

    /**
     * Returns a complete HTML select element for the given options.
     *
     * @return string The HTML for the select element.
     */
    public function getSelect(): string
    {
        $labelClass = !empty($this->labelClass) ? " class=\"{$this->labelClass}\"" : '';
        $id = $this->id && !empty($this->id) ? " id=\"{$this->id}\"" : '';
        $name = $this->name && !empty($this->name) ? " name=\"{$this->name}\"" : '';
        $class = $this->class && !empty($this->class) ? " class=\"{$this->class}\"" : '';
        $disabled = $this->disabled ? 'disabled' : '';
        $optionsHtml = $this->getOptions();
        if ($this->allowNull) {
            $optionsHtml = "<option value=\"\">---</option>{$optionsHtml}";
        }
        return ($this->label ? "<label for=\"{$this->id}\"{$labelClass}>{$this->labelStr}</label>" : '')
            . "<select{$id}{$name}{$class}{$disabled}>{$optionsHtml}</select>";
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
     * Returns true if the given diocese is a valid diocese for the given nation,
     * and false otherwise.
     *
     * @param string $diocese The diocese to check.
     * @param string $nation The nation to check.
     *
     * @return bool True if the diocese is valid for the nation, false otherwise.
     */
    public static function isValidDioceseForNation($diocese, $nation): bool
    {
        $nationalCalendarMetadata = array_values(array_filter(self::$nationalCalendars, fn($item) => $item['calendar_id'] === $nation));
        if (count($nationalCalendarMetadata) === 0) {
            return false;
        }
        $nationalCalendar = $nationalCalendarMetadata[0];
        if (false === array_key_exists('dioceses', $nationalCalendar)) {
            return false;
        }
        return in_array($diocese, $nationalCalendar['dioceses']);
    }

    public static function getMetadata(): ?array
    {
        return self::$calendarIndex;
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
