<?php

namespace LiturgicalCalendar\Components;

use LiturgicalCalendar\Components\CalendarSelect\OptionsType;
use LiturgicalCalendar\Components\Models\Index\CalendarIndex;
use LiturgicalCalendar\Components\Models\Index\NationalCalendar;
use LiturgicalCalendar\Components\Models\Index\DiocesanCalendar;
use LiturgicalCalendar\Components\Http\HttpClientInterface;
use LiturgicalCalendar\Components\Http\HttpClientFactory;
use LiturgicalCalendar\Components\Http\LoggingHttpClient;
use LiturgicalCalendar\Components\Http\CachingHttpClient;
use LiturgicalCalendar\Components\Logging\LoggerAwareTrait;
use LiturgicalCalendar\Components\Metadata\MetadataProvider;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;

/**
 * A class to generate a select element for selecting a Liturgical Calendar.
 *
 * This class will generate a select element with options for all the national
 * calendars, as well as the diocesan calendars for each nation.
 *
 * The API URL is configured once through MetadataProvider during initialization
 * and becomes immutable for the lifetime of the application.
 *
 * Public Methods:
 * - {@see LiturgicalCalendar\Components\CalendarSelect::__construct()} Initializes the CalendarSelect object with default settings.
 * - {@see LiturgicalCalendar\Components\CalendarSelect::selectedOption()} Sets the selected option.
 * - {@see LiturgicalCalendar\Components\CalendarSelect::locale()} Sets the locale for the calendar select.
 * - {@see LiturgicalCalendar\Components\CalendarSelect::class()} Sets the class for the calendar select.
 * - {@see LiturgicalCalendar\Components\CalendarSelect::id()} Sets the ID for the select element.
 * - {@see LiturgicalCalendar\Components\CalendarSelect::name()} Sets the name for the select element.
 * - {@see LiturgicalCalendar\Components\CalendarSelect::setOptions()} Sets the type of option elements for the select element.
 * - {@see LiturgicalCalendar\Components\CalendarSelect::nationFilter()} Sets the nation to filter the diocese options to.
 * - {@see LiturgicalCalendar\Components\CalendarSelect::label()} Configures whether to show the label.
 * - {@see LiturgicalCalendar\Components\CalendarSelect::labelText()} Sets the text for the label.
 * - {@see LiturgicalCalendar\Components\CalendarSelect::allowNull()} Allows or disallows null selection.
 * - {@see LiturgicalCalendar\Components\CalendarSelect::disabled()} Sets whether the select element is disabled.
 * - {@see LiturgicalCalendar\Components\CalendarSelect::data()} Sets data attributes for the select element.
 * - {@see LiturgicalCalendar\Components\CalendarSelect::isValidLocale()} Checks if the given locale is valid.
 * - {@see LiturgicalCalendar\Components\CalendarSelect::getSelect()} Returns the HTML for the select element.
 * - {@see LiturgicalCalendar\Components\CalendarSelect::getLocale()} Returns the locale used by the calendar select instance.
 * - {@see LiturgicalCalendar\Components\CalendarSelect::isValidDioceseForNation()} Static method to check if the given diocese is valid for the given nation.
 *
 * @package LiturgicalCalendar\Components
 * @author John Romano D'Orazio <priest@johnromanodorazio.com>
 */
class CalendarSelect
{
    use LoggerAwareTrait;

    private CalendarIndex $calendarIndex;
    private MetadataProvider $metadataProvider;

    /** @var NationalCalendar[] $nationalCalendarsWithDioceses */
    private array $nationalCalendarsWithDioceses = [];

    /** @var string[] $nationOptions An array of strings representing HTML select options for national calendars */
    private array $nationOptions = [];

    /** @var array<string,string[]> $dioceseOptions An associative array mapping nation to an array of strings representing HTML select options for dioceses within the given nation */
    private array $dioceseOptions = [];

    /** @var string[] $dioceseOptionsGrouped */
    private array $dioceseOptionsGrouped = [];

    private string $locale                         = 'en';
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

    /** @var array<string,string> $dataAttributes Data attributes (name => value, without 'data-' prefix) */
    private array $dataAttributes = [];

    /**
     * Creates a new instance of the CalendarSelect class.
     *
     * The options array can contain the following keys:
     * - `locale`: string, the locale to use, defaults to 'en'
     * - `class`: string, the class to apply to the select element, defaults to 'calendarSelect'
     * - `id`: string, the id to apply to the select element, defaults to 'calendarSelect'
     * - `name`: string, the name to apply to the select element, defaults to 'calendarSelect'
     * - `nationFilter`: string, the nation to filter the diocese options to, defaults to false
     * - `setOptions`: OptionsType, the type of options to set, defaults to OptionsType::ALL
     * - `selectedOption`: string, the selected option, defaults to false
     * - `label`: boolean, whether to include a label element, defaults to false
     * - `labelStr`: string, the string to use for the label element, defaults to 'Select a calendar'
     * - `allowNull`: boolean, whether to allow the null value in the select element, defaults to false
     *
     * **MetadataProvider Integration:**
     *
     * CalendarSelect uses the globally configured {@see MetadataProvider} singleton for metadata operations.
     * Initialize MetadataProvider once at application bootstrap with HTTP client, cache, and logger:
     *
     * ```php
     * use LiturgicalCalendar\Components\Metadata\MetadataProvider;
     * use LiturgicalCalendar\Components\Http\HttpClientFactory;
     *
     * // Initialize MetadataProvider once at application startup
     * MetadataProvider::getInstance(
     *     apiUrl: 'https://litcal.johnromanodorazio.com/api/dev',
     *     httpClient: HttpClientFactory::createProductionClient($cache, $logger),
     *     cache: $cache,
     *     logger: $logger,
     *     cacheTtl: 86400
     * );
     *
     * // Components automatically use the configured singleton
     * $calendarSelect = new CalendarSelect(['locale' => 'en']);
     * ```
     *
     * **Usage Examples:**
     *
     * Basic usage with default API URL:
     * ```php
     * $calendarSelect = new CalendarSelect(['locale' => 'en']);
     * ```
     *
     * Full configuration with all options:
     * ```php
     * $calendarSelect = new CalendarSelect([
     *     'locale' => 'en',
     *     'class' => 'form-select',
     *     'id' => 'calendar-dropdown',
     *     'name' => 'selected_calendar',
     *     'nationFilter' => 'US',
     *     'selectedOption' => 'USA'
     * ]);
     * ```
     *
     * @param array{locale?:string,class?:string,id?:string,name?:string,nationFilter?:string,setOptions?:OptionsType,selectedOption?:string,label?:bool,labelStr?:string,allowNull?:bool} $options The options for the instance.
     */
    public function __construct(array $options = [])
    {
        // Get MetadataProvider singleton (auto-initializes with default URL if not already initialized)
        $this->metadataProvider = MetadataProvider::getInstance();

        if (isset($options['locale'])) {
            $this->locale($options['locale']);
        }

        // Fetch metadata from MetadataProvider
        // This will throw an exception if metadata cannot be loaded
        $this->fetchMetadata();

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
            if (false === in_array($options['nationFilter'], $this->calendarIndex->nationalCalendarsKeys, true)) {
                throw new \Exception("Invalid nation: {$options['nationFilter']}, valid values are: " . implode(', ', $this->calendarIndex->nationalCalendarsKeys));
            }
            $this->nationFilterForDioceseOptions = $options['nationFilter'];
        }

        if (isset($options['setOptions'])) {
            $this->setOptions($options['setOptions']);
        }

        if (isset($options['selectedOption'])) {
            $this->selectedOption = htmlspecialchars($options['selectedOption'], ENT_QUOTES, 'UTF-8');
        }

        if (isset($options['label'])) {
            $this->label = filter_var($options['label'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($options['labelStr'])) {
            $labelStr = $options['labelStr'];
            if (!is_string($labelStr)) {
                throw new \InvalidArgumentException('Expected string for labelStr, got ' . gettype($labelStr));
            }
            $this->labelStr = htmlspecialchars($labelStr, ENT_QUOTES, 'UTF-8');
        }

        if (isset($options['labelClass'])) {
            $labelClass = $options['labelClass'];
            if (!is_string($labelClass)) {
                throw new \InvalidArgumentException('Expected string for labelClass, got ' . gettype($labelClass));
            }
            $this->labelClass = htmlspecialchars($labelClass, ENT_QUOTES, 'UTF-8');
        }

        if (isset($options['allowNull'])) {
            $this->allowNull = filter_var($options['allowNull'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($options['disabled'])) {
            $this->disabled = filter_var($options['disabled'], FILTER_VALIDATE_BOOLEAN);
        }
    }


    /**
     * Sets the selected option of the select element.
     *
     * @param string $value The value of the selected option.
     *
     * @return $this
     */
    public function selectedOption(string $value): self
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
     * @return $this
     */
    public function locale(string $locale): self
    {
        $canonicalized = \Locale::canonicalize($locale);
        if ($canonicalized === null) {
            throw new \Exception("Failed to canonicalize locale: {$locale}");
        }
        if (!self::isValidLocale($canonicalized)) {
            throw new \Exception("Invalid locale: {$canonicalized}");
        }
        $this->locale = $canonicalized;
        return $this;
    }

    /**
     * Sets the class attribute of the select element.
     *
     * @param string $className the class attribute of the select element
     *
     * @return $this
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
     * @return $this
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
     * @throws \Exception If the options type is OptionsType::DIOCESES_FOR_NATION and nationFilter has not been set.
     */
    public function setOptions(OptionsType $optionsType): self
    {
        if (
            OptionsType::DIOCESES_FOR_NATION === $optionsType
            && ( null === $this->nationFilterForDioceseOptions || empty($this->nationFilterForDioceseOptions) )
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
        if (false === in_array($nation, $this->calendarIndex->nationalCalendarsKeys, true)) {
            throw new \Exception("Invalid nation: {$nation}, valid values are: " . implode(', ', $this->calendarIndex->nationalCalendarsKeys));
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

    /**
     * Sets the class attribute of the label element.
     *
     * If the label element is not enabled, this value is ignored.
     *
     * @param string $labelClass The class attribute of the label element.
     *
     * @return $this
     */
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
     * @return $this
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
     * @return $this
     */
    public function disabled(bool $disabled = true): self
    {
        $this->disabled = $disabled;
        return $this;
    }

    /**
     * Sets data attributes for the select element.
     *
     * This method accepts an associative array where the keys are the data attribute names
     * (without the 'data-' prefix) and the values are the corresponding attribute values.
     * These data attributes will be rendered as 'data-*' attributes in the HTML select element.
     *
     * **Note:** Each call to this method replaces any previously set data attributes.
     * To add multiple attributes, pass them all in a single array.
     *
     * Example:
     * ```php
     * $calendarSelect->data(['requires-auth' => '', 'calendar-type' => 'national']);
     * // Renders: <select ... data-requires-auth data-calendar-type="national">
     * ```
     *
     * @param array<string,string> $data An associative array representing data attributes for the select element.
     *                                   Keys must start with a letter and may then contain letters, digits,
     *                                   hyphens, underscores, or colons. Keys are converted to lowercase
     *                                   for HTML5 compliance.
     *
     * @return $this
     *
     * @throws \InvalidArgumentException If an attribute name contains invalid characters.
     */
    public function data(array $data): self
    {
        $this->dataAttributes = [];
        foreach ($data as $key => $value) {
            if (!is_string($key) || !preg_match('/^[A-Za-z][A-Za-z0-9_\-:]*$/', $key)) {
                throw new \InvalidArgumentException('Invalid data attribute name: ' . (string) $key);
            }
            // Convert to lowercase for HTML5 compliance
            $this->dataAttributes[strtolower($key)] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }
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
        $latin          = ['la', 'la_VA'];
        $resourceBundle = \ResourceBundle::getLocales('');
        if (false === $resourceBundle) {
            throw new \RuntimeException('Failed to retrieve locales from ResourceBundle.');
        }
        $AllAvailableLocales = array_filter($resourceBundle, fn (mixed $value): bool => is_string($value) && strpos($value, 'POSIX') === false);
        return in_array($locale, $latin) || in_array($locale, $AllAvailableLocales);
    }

    /**
     * Fetches the metadata from the API using MetadataProvider
     *
     * The metadata is cached by MetadataProvider for the current process,
     * so subsequent calls will not make additional HTTP requests.
     *
     * @throws \Exception If there is an error fetching or decoding the metadata
     */
    private function fetchMetadata(): void
    {
        $this->calendarIndex = $this->metadataProvider->getMetadata();
    }


    /**
     * Returns true if we have already stored a national calendar with dioceses for the given nation,
     * that is when diocesan calendars have the given nation as their calendar_id, and false otherwise.
     *
     * @param string $nation The nation to check.
     *
     * @return bool
     */
    private function hasNationalCalendarWithDioceses(string $nation): bool
    {
        return count($this->nationalCalendarsWithDioceses)
                && count(array_filter($this->nationalCalendarsWithDioceses, fn(NationalCalendar $item) => $item->calendarId === $nation)) > 0;
    }

    /**
     * Adds a national calendar to the list of national calendars with dioceses.
     * This will also initialize diocese select options for the given nation.
     *
     * @param string $nation The nation for which we should add the national calendar.
     */
    private function addNationalCalendarWithDioceses(string $nation): void
    {
        $nationalCalendar = array_values(array_filter($this->calendarIndex->nationalCalendars, fn(NationalCalendar $item) => $item->calendarId === $nation));
        array_push($this->nationalCalendarsWithDioceses, $nationalCalendar[0]);
        $this->dioceseOptions[$nation] = [];
    }

    /**
     * Adds a select option for a national calendar to the list of national calendar select options.
     * This will generate an <option> element with the data of the given national calendar.
     *
     * @param NationalCalendar $nationalCalendar The national calendar for which we will add a select options.
     */
    private function addNationOption(NationalCalendar $nationalCalendar): void
    {
        $selectedStr = '';
        if ($this->selectedOption === $nationalCalendar->calendarId) {
            $selectedStr = ' selected';
        }
        $optionOpenTag  = "<option data-calendartype=\"nationalcalendar\" value=\"{$nationalCalendar->calendarId}\"{$selectedStr}>";
        $optionContents = \Locale::getDisplayRegion('-' . $nationalCalendar->calendarId, $this->locale);
        $optionCloseTag = '</option>';
        $optionHtml     = "{$optionOpenTag}{$optionContents}{$optionCloseTag}";
        array_push($this->nationOptions, $optionHtml);
    }

    /**
     * Adds a select option for a diocesan calendar to the list of diocese select options for the given diocesan calendar.
     * This will generate an <option> element with the data of the given diocesan calendar.
     *
     * @param DiocesanCalendar $diocesanCalendar The diocesan calendar for which we will add a select options.
     */
    private function addDioceseOption(DiocesanCalendar $diocesanCalendar): void
    {
        $selectedStr = '';
        if ($this->selectedOption === $diocesanCalendar->calendarId) {
            $selectedStr = ' selected';
        }
        $optionOpenTag  = "<option data-calendartype=\"diocesancalendar\" value=\"{$diocesanCalendar->calendarId}\"{$selectedStr}>";
        $optionContents = $diocesanCalendar->diocese;
        $optionCloseTag = '</option>';
        $optionHtml     = "{$optionOpenTag}{$optionContents}{$optionCloseTag}";
        array_push($this->dioceseOptions[$diocesanCalendar->nation], $optionHtml);
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
    private function buildAllOptions(): void
    {
        $col = \Collator::create($this->locale);
        if ($col === null) {
            throw new \Exception('Failed to create Collator for locale: ' . $this->locale);
        }
        $col->setStrength(\Collator::PRIMARY); // only compare base characters; not accents, lower/upper-case, ...

        foreach ($this->calendarIndex->diocesanCalendars as $diocesanCalendar) {
            if (!$this->hasNationalCalendarWithDioceses($diocesanCalendar->nation)) {
                // we add all nations with dioceses to the nations list
                $this->addNationalCalendarWithDioceses($diocesanCalendar->nation);
            }
            $this->addDioceseOption($diocesanCalendar);
        }
        $sortedNationalCalendars = $this->calendarIndex->nationalCalendars;
        usort($sortedNationalCalendars, function (NationalCalendar $a, NationalCalendar $b) use ($col): int {
            $displayA = \Locale::getDisplayRegion('-' . $a->calendarId, $this->locale);
            $displayB = \Locale::getDisplayRegion('-' . $b->calendarId, $this->locale);
            if ($displayA === false) {
                $displayA = $a->calendarId;
            }
            if ($displayB === false) {
                $displayB = $b->calendarId;
            }
            $result = $col->compare($displayA, $displayB);
            return $result === false ? 0 : $result;
        });
        foreach ($sortedNationalCalendars as $nationalCalendar) {
            if (!$this->hasNationalCalendarWithDioceses($nationalCalendar->calendarId)) {
                // This is the first time we call CalendarSelect::addNationOption().
                // This will ensure that the VATICAN (or any other nation without any diocese) will be added as the first option,
                // thus ensuring that VATICAN is always the default selected option when allowNull is false.
                $this->addNationOption($nationalCalendar);
            }
        }

        // now we can add the options for the nations in the nationalCalendarsWithDioceses list
        // that is to say, nations that have dioceses
        usort($this->nationalCalendarsWithDioceses, function (NationalCalendar $a, NationalCalendar $b) use ($col): int {
            $displayA = \Locale::getDisplayRegion('-' . $a->calendarId, $this->locale);
            $displayB = \Locale::getDisplayRegion('-' . $b->calendarId, $this->locale);
            if ($displayA === false) {
                $displayA = $a->calendarId;
            }
            if ($displayB === false) {
                $displayB = $b->calendarId;
            }
            $result = $col->compare($displayA, $displayB);
            return $result === false ? 0 : $result;
        });
        foreach ($this->nationalCalendarsWithDioceses as $nationalCalendar) {
            $this->addNationOption($nationalCalendar);
            $optgroupLabel    = \Locale::getDisplayRegion('-' . $nationalCalendar->calendarId, $this->locale);
            $optgroupOpenTag  = "<optgroup label=\"{$optgroupLabel}\">";
            $optgroupContents = implode('', $this->dioceseOptions[$nationalCalendar->calendarId]);
            $optgroupCloseTag = '</optgroup>';
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
    }

    /**
     * Returns the data-* attributes string for the select element.
     *
     * If no data attributes are set, an empty string is returned.
     * Otherwise, returns a space-prefixed string of all data attributes.
     *
     * @return string The data-* attributes string.
     */
    private function getData(): string
    {
        if (empty($this->dataAttributes)) {
            return '';
        }
        $data = array_map(
            function (string $key, string $value): string {
                if ($value === '') {
                    return "data-{$key}";
                }
                return "data-{$key}=\"{$value}\"";
            },
            array_keys($this->dataAttributes),
            array_values($this->dataAttributes)
        );
        return ' ' . implode(' ', $data);
    }

    /**
     * Returns a complete HTML select element for the given options.
     *
     * @return string The HTML for the select element.
     */
    public function getSelect(): string
    {
        $labelClass  = !empty($this->labelClass) ? " class=\"{$this->labelClass}\"" : '';
        $id          = $this->id && !empty($this->id) ? " id=\"{$this->id}\"" : '';
        $name        = $this->name && !empty($this->name) ? " name=\"{$this->name}\"" : '';
        $class       = $this->class && !empty($this->class) ? " class=\"{$this->class}\"" : '';
        $disabled    = $this->disabled ? ' disabled' : '';
        $dataAttrs   = $this->getData();
        $optionsHtml = $this->getOptions();
        if ($this->allowNull) {
            $optionsHtml = "<option value=\"\">---</option>{$optionsHtml}";
        }
        return ( $this->label ? "<label for=\"{$this->id}\"{$labelClass}>{$this->labelStr}</label>" : '' )
            . "<select{$id}{$name}{$class}{$disabled}{$dataAttrs}>{$optionsHtml}</select>";
    }

    /**
     * Returns the locale used by the calendar select instance.
     *
     * @return string The locale, a valid PHP locale string such as 'en' or 'es' or 'en_US' or 'es_ES'.
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Returns true if the given diocese is a valid diocese for the given nation,
     * and false otherwise.
     *
     * This method delegates to MetadataProvider for centralized validation.
     *
     * @param string $diocese_id The diocese to check.
     * @param string $nation The nation to check.
     *
     * @return bool True if the diocese is valid for the nation, false otherwise.
     * @throws \Exception If MetadataProvider has not been initialized
     */
    public static function isValidDioceseForNation(string $diocese_id, string $nation): bool
    {
        return MetadataProvider::isValidDioceseForNation($diocese_id, $nation);
    }

    /**
     * Returns the calendar metadata index for the calendar select instance.
     *
     * This contains all the calendar metadata returned by the API,
     * including all the national and diocesan calendars.
     *
     * @return CalendarIndex The metadata index
     */
    public function getMetadata(): CalendarIndex
    {
        return $this->calendarIndex;
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
