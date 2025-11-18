<?php

namespace LiturgicalCalendar\Components;

use LiturgicalCalendar\Components\ApiOptions\FormLabel;
use LiturgicalCalendar\Components\ApiOptions\Input\AcceptHeader;
use LiturgicalCalendar\Components\ApiOptions\Input\Ascension;
use LiturgicalCalendar\Components\ApiOptions\Input\Epiphany;
use LiturgicalCalendar\Components\ApiOptions\Input\CorpusChristi;
use LiturgicalCalendar\Components\ApiOptions\Input\EternalHighPriest;
use LiturgicalCalendar\Components\ApiOptions\Input\HolyDaysOfObligation;
use LiturgicalCalendar\Components\ApiOptions\Input\Year;
use LiturgicalCalendar\Components\ApiOptions\Input\YearType;
use LiturgicalCalendar\Components\ApiOptions\Input\Locale;
use LiturgicalCalendar\Components\ApiOptions\Wrapper;
use LiturgicalCalendar\Components\ApiOptions\Submit;
use LiturgicalCalendar\Components\ApiOptions\PathType;

/**
 * Generate an options form for the Liturgical Calendar API.
 *
 * The class contains methods to generate the form, form label, form wrapper, and form submit elements.
 * The form elements can be fully customized using the methods provided by the class.
 *
 * @see LiturgicalCalendar\Components\ApiOptions::__construct() Initializes the ApiOptions object with default or provided settings:
 * - __$options__: An array of options, including `locale`, `formLabel`, `wrapper`, `submit`, `after`, and `url`.
 *
 * The following properties are initialized on the object instance:
 * - __formLabel__: A {@see LiturgicalCalendar\Components\ApiOptions\FormLabel} object if the `formLabel` key is present in the options array.
 * - __wrapper__: A {@see LiturgicalCalendar\Components\ApiOptions\Wrapper} object if the `wrapper` key is present in the options array.
 * - __submit__: A {@see LiturgicalCalendar\Components\ApiOptions\Submit} object if the `submit` key is present in the options array.
 * - __epiphanyInput__: A {@see LiturgicalCalendar\Components\ApiOptions\Input} object.
 * - __ascensionInput__: An {@see LiturgicalCalendar\Components\ApiOptions\Input} object.
 * - __corpusChristiInput__: An {@see LiturgicalCalendar\Components\ApiOptions\Input} object.
 * - __eternalHighPriestInput__: An {@see LiturgicalCalendar\Components\ApiOptions\Input} object.
 * - __yearInput__: An {@see LiturgicalCalendar\Components\ApiOptions\Input} object.
 * - __yearTypeInput__: An {@see LiturgicalCalendar\Components\ApiOptions\Input} object.
 * - __localeInput__: An {@see LiturgicalCalendar\Components\ApiOptions\Input} object.
 * - __acceptHeaderInput__: An {@see LiturgicalCalendar\Components\ApiOptions\Input} object.
 * - __holydaysOfObligationInput__: An {@see LiturgicalCalendar\Components\ApiOptions\Input} object.
 * @see LiturgicalCalendar\Components\ApiOptions::after() Sets the HTML to add after the form.
 * @see LiturgicalCalendar\Components\ApiOptions::getForm() Returns the HTML for the form.
 * @see LiturgicalCalendar\Components\ApiOptions::getLocale() Returns the locale used by the API options component.
 * @method static string baseLocale()
 *
 * @package LiturgicalCalendar\Components
 * @author John Romano D'Orazio <priest@johnromanodorazio.com>
 */
class ApiOptions
{
    private ?string $after         = null;
    private static ?string $locale = null;
    public ?FormLabel $formLabel   = null;
    public ?Wrapper $wrapper       = null;
    public ?Submit $submit         = null;
    public Epiphany $epiphanyInput;
    public Ascension $ascensionInput;
    public CorpusChristi $corpusChristiInput;
    public EternalHighPriest $eternalHighPriestInput;
    public Year $yearInput;
    public YearType $yearTypeInput;
    public Locale $localeInput;
    public AcceptHeader $acceptHeaderInput;
    public HolyDaysOfObligation $holydaysOfObligationInput;
    public string $currentSetLocale       = '';
    public string $expectedTextDomainPath = '';
    public string $currentTextDomainPath  = '';
    private static string $apiUrl         = 'https://litcal.johnromanodorazio.com/api/dev';

    /**
     * Constructor for the ApiOptions class.
     *
     * Initializes the class properties based on the provided options array.
     * If the `formLabel`, `locale`, `wrapper`, or `submit` keys are present in the options array,
     * sets the corresponding property values accordingly.
     * If the `formLabel` key is present, instantiates a new {@see LiturgicalCalendar\Components\ApiOptions\FormLabel} object with the provided value.
     * If the `locale` key is present, canonicalizes the locale value and stores it in the static $locale property.
     *
     * If the `submit` or `wrapper` keys are present in the options array,
     * checks the boolean value associated with them and instantiates a new Submit or Wrapper object accordingly.
     * The `wrapper` key can be set to `div` or `form` to specify whether the wrapper should be an HTML div or form element.
     * It can also be set to an associative array with the `class` key to set the class attribute of the wrapper element,
     * the `id` key to set the id attribute of the wrapper element, and the `as` key to set the HTML tag of the wrapper element.
     *
     * Prepares localization using the prepareL10n method.
     * Initializes various {@see LiturgicalCalendar\Components\ApiOptions\Input} objects such as:
     * - {@see LiturgicalCalendar\Components\ApiOptions\Input\Epiphany}
     * - {@see LiturgicalCalendar\Components\ApiOptions\Input\Ascension}
     * - {@see LiturgicalCalendar\Components\ApiOptions\Input\CorpusChristi}
     * - {@see LiturgicalCalendar\Components\ApiOptions\Input\EternalHighPriest}
     * - {@see LiturgicalCalendar\Components\ApiOptions\Input\YearType}
     * - {@see LiturgicalCalendar\Components\ApiOptions\Input\Locale}
     * - {@see LiturgicalCalendar\Components\ApiOptions\Input\AcceptHeader}
     * - {@see LiturgicalCalendar\Components\ApiOptions\Input\HolyDaysOfObligation}
     *
     * @param array<string,mixed>|null $options An array of options for the ApiOptions object.
     */
    public function __construct(?array $options = null)
    {
        if ($options !== null && count($options) > 0) {
            foreach ($options as $key => $value) {
                switch ($key) {
                    case 'locale':
                        if (!is_string($value) && $value !== null) {
                            throw new \InvalidArgumentException('Expected string for locale, got ' . gettype($value));
                        }
                        $value               = null !== $value ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : 'en-US';
                        $canonicalizedLocale = \Locale::canonicalize($value);

                        // Validate that the locale is at least recognized by PHP's Intl extension
                        // by attempting to create an IntlDateFormatter with it
                        try {
                            /** @phpstan-ignore new.resultUnused */
                            new \IntlDateFormatter(
                                $canonicalizedLocale,
                                \IntlDateFormatter::LONG,
                                \IntlDateFormatter::NONE
                            );
                            // If we get here, the locale is valid enough for IntlDateFormatter
                            self::$locale = $canonicalizedLocale;
                        } catch (\ValueError | \IntlException $e) {
                            // Locale is invalid - trigger warning and fall back to 'en'
                            trigger_error(
                                "Invalid locale '{$value}' provided. Falling back to 'en'. " .
                                'Error: ' . $e->getMessage(),
                                E_USER_WARNING
                            );
                            self::$locale = 'en';
                        }
                        break;
                    case 'formLabel':
                        if (is_bool($value) && $value === true) {
                            $this->$key = new FormLabel();
                        } elseif (is_string($value) || is_array($value)) {
                            /** @var array{as?: string, text?: string, class?: string, id?: string}|string $value */
                            $this->$key = new FormLabel($value);
                        }
                        break;
                    case 'wrapper':
                        if (is_bool($value) && $value === true) {
                            $this->$key = new Wrapper();
                        } elseif (is_string($value) && in_array($value, ['div', 'form'])) {
                            $this->$key = new Wrapper($value);
                        }
                        break;
                    case 'submit':
                        if ($value === true) {
                            $this->$key = new Submit();
                        }
                        break;
                    case 'after':
                        if (is_string($value)) {
                            $value = preg_replace('/<\?php.*?\?>/s', '', $value);
                            if ($value === null) {
                                throw new \Exception('Failed to clean PHP tags from after content');
                            }
                            $value = preg_replace('/<script.*?>.*?<\/script>/s', '', $value);
                            if ($value === null) {
                                throw new \Exception('Failed to clean script tags from after content');
                            }
                            $this->$key = $value;
                        }
                        break;
                    case 'url':
                        if (false === is_string($value)) {
                            throw new \InvalidArgumentException('The API URL must be a valid URL string.');
                        }
                        $value = filter_var($value, FILTER_VALIDATE_URL);
                        if (false === $value) {
                            throw new \InvalidArgumentException('The API URL is not valid: ' . $value);
                        }
                        self::$apiUrl = rtrim($value, '/');
                        break;
                }
            }
        }

        $this->prepareL10n();

        $this->epiphanyInput             = new Epiphany();
        $this->ascensionInput            = new Ascension();
        $this->corpusChristiInput        = new CorpusChristi();
        $this->eternalHighPriestInput    = new EternalHighPriest();
        $this->yearInput                 = new Year();
        $this->yearTypeInput             = new YearType();
        $this->localeInput               = new Locale();
        $this->acceptHeaderInput         = new AcceptHeader();
        $this->holydaysOfObligationInput = new HolydaysOfObligation();
    }

    /**
     * Returns the URL of the liturgical calendar API.
     * @return string The URL of the liturgical calendar API.
     */
    public static function getApiUrl(): string
    {
        return self::$apiUrl;
    }

    /**
     * Provides a way to call static methods or properties, even if they don't exist.
     * In this case, we create a magic value for ApiOptions::baseLocale().
     *
     * @param string $name The name of the static method or property.
     * @param array<null> $arguments
     *
     * @throws \Exception If `$name` is not a valid magic static property or method.
     *
     * @return mixed The value of the static property or the result of the static method.
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        if ($name === 'baseLocale') {
            $locale = self::$locale ?? 'en';
            return \Locale::getPrimaryLanguage($locale);
        }
        throw new \Exception("Static property {$name} does not exist");
    }

    /**
     * Initializes the localization for the component.
     *
     * If the ApiOptions::$locale is not set, it defaults to 'en_US'.
     * Then, it sets the locale using setlocale with an array of locale variants built as follows:
     *    - ApiOptions::$locale with '.utf8' or '.UTF-8' appended, and without suffix
     *    - If a region is detected (via \Locale::getRegion), base locale + '_' + region with
     *      '.utf8' or '.UTF-8' appended, and without suffix
     *    - Base locale with '.utf8' or '.UTF-8' appended, and without suffix
     *
     * Duplicate variants are removed to ensure efficient locale resolution.
     *
     * If none of the locale variants can be set (usually because they are not installed on the system),
     * a warning is triggered but the component continues to function. Translations may fall back to
     * English or display untranslated strings.
     *
     * After attempting to set the locale, it binds the textdomain 'litcompphp' to the 'i18n' directory.
     */
    private function prepareL10n(): void
    {
        if (self::$locale === null) {
            self::$locale = 'en_US';
        }
        $region     = \Locale::getRegion(self::$locale);
        $baseLocale = self::baseLocale();
        if (null === $baseLocale) {
            throw new \RuntimeException('“Pride was the reason for the division of tongues, humility the reason they were reunited.” - St. Augustine, The City of God, Book XVI, Chapter 4');
        }

        $localeArray = [
            self::$locale . '.utf8',
            self::$locale . '.UTF-8',
            self::$locale,
            $baseLocale . '.utf8',
            $baseLocale . '.UTF-8',
            $baseLocale
        ];
        if ($region !== null && $region !== '') {
            array_splice($localeArray, 3, 0, [
                $baseLocale . '_' . $region . '.utf8',
                $baseLocale . '_' . $region . '.UTF-8',
                $baseLocale . '_' . $region
            ]);
        }
        // Remove duplicates that may occur when self::$locale already includes a region
        $localeArray = array_unique($localeArray);

        $runtimeLocale = setlocale(LC_ALL, $localeArray);
        if (false === $runtimeLocale) {
            // Locale setting failed - trigger a warning but continue
            // The component will still work, but translations may not be available
            trigger_error(
                'Failed to set locale to one of the following: ' . implode(', ', $localeArray) .
                '. Translations may not be available. To fix this, install the required locale packages on your system.',
                E_USER_WARNING
            );
            // Fall back to current locale (typically "C" or system default)
            $runtimeLocale = setlocale(LC_ALL, null) ?: 'C';
        }
        $this->currentSetLocale       = $runtimeLocale;
        $this->expectedTextDomainPath = __DIR__ . '/ApiOptions/i18n';
        $bound                        = bindtextdomain('litcompphp', $this->expectedTextDomainPath);
        if (false === $bound || $bound !== $this->expectedTextDomainPath) {
            trigger_error(
                "Failed to bind text domain. Expected path: {$this->expectedTextDomainPath}, got: {$bound}. " .
                'Translations may not be available.',
                E_USER_WARNING
            );
            $this->currentTextDomainPath = $bound ?: $this->expectedTextDomainPath;
        } else {
            $this->currentTextDomainPath = $bound;
        }
    }

    /**
     * Retrieves the HTML for the options related to the base /calendar path.
     *
     * It includes the Epiphany, Ascension, Corpus Christi, Eternal High Priest, and locale options.
     *
     * @return string The HTML for the options.
     */
    private function getBasePathOptions(): string
    {
        return $this->epiphanyInput->get()
            . $this->ascensionInput->get()
            . $this->corpusChristiInput->get()
            . $this->eternalHighPriestInput->get()
            . $this->holydaysOfObligationInput->get();
    }

    /**
     * Retrieves the HTML for the options related to all /calendar/* paths.
     *
     * It includes the year type and Accept header options.
     *
     * @return string The HTML for the options.
     */
    private function getAllPathsOptions(): string
    {
        return $this->localeInput->get()
               . $this->yearInput->get()
               . $this->yearTypeInput->get()
               . ( $this->acceptHeaderInput->isHidden() ? '' : $this->acceptHeaderInput->get() );
    }

    /**
     * Replaces any <?php ?> or <script> blocks from the given HTML,
     * and sets the $after property to the cleaned up HTML.
     *
     * @param string $html The HTML to set the $after property to.
     */
    public function after(string $html): void
    {
        $html = preg_replace('/<\?php.*?\?>/s', '', $html);
        if ($html === null) {
            throw new \Exception('Failed to clean PHP tags from after content');
        }
        $html = preg_replace('/<script.*?>.*?<\/script>/s', '', $html);
        if ($html === null) {
            throw new \Exception('Failed to clean script tags from after content');
        }
        $this->after = $html;
    }

    /**
     * Generates and returns an HTML string for the options form.
     *
     * The form includes a form label, the options related to the base /calendar path,
     * the options related to all /calendar/* paths, and a submit button.
     * The form can be wrapped in a `<div>` or `<form>` element by providing a wrapper object.
     *
     * @param PathType|null $pathType The type of options to include in the form.
     *                                If null, include all options.  If one of the PathType enum
     *                                values, only include the options related to that path.
     *
     * @return string The HTML for the options form.
     */
    public function getForm(?PathType $pathType = null): string
    {
        $html = '';
        if ($this->formLabel !== null) {
            $html .= $this->formLabel->get($this);
        }

        if ($pathType !== null) {
            switch ($pathType) {
                case PathType::BASE_PATH:
                    $html .= $this->getBasePathOptions();
                    break;
                case PathType::ALL_PATHS:
                    $html .= $this->getAllPathsOptions();
                    break;
            }
        } else {
            $html .= $this->getBasePathOptions();
            $html .= $this->getAllPathsOptions();
        }
        if ($this->submit !== null) {
            $html .= $this->submit->get($this);
        }

        if ($this->after !== null) {
            $html .= $this->after;
        }
        if ($this->wrapper !== null) {
            return $this->wrapper->get($html, $this);
        }
        return $html;
    }

    /**
     * Retrieves the locale used by the API options component.
     *
     * @return string|null The locale, or null if no locale has been set.
     */
    public static function getLocale(): ?string
    {
        return self::$locale;
    }
}
