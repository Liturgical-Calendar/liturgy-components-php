<?php

namespace LiturgicalCalendar\Components;

use LiturgicalCalendar\Components\ApiOptions\FormLabel;
use LiturgicalCalendar\Components\ApiOptions\Input\AcceptHeader;
use LiturgicalCalendar\Components\ApiOptions\Input\Ascension;
use LiturgicalCalendar\Components\ApiOptions\Input\Epiphany;
use LiturgicalCalendar\Components\ApiOptions\Input\CorpusChristi;
use LiturgicalCalendar\Components\ApiOptions\Input\EternalHighPriest;
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
 * @method __construct(?array $options = null) Initializes the ApiOptions object with default or provided settings:
 *                                             - $options: An array of options, including 'locale', 'formLabel', 'wrapper', 'submit', 'after', and 'url'.
 *
 *                                             The following properties are initialized on the object instance:
 *                                             - formLabel: A FormLabel object if the 'formLabel' key is present in the options array.
 *                                             - wrapper: A Wrapper object if the 'wrapper' key is present in the options array.
 *                                             - submit: A Submit object if the 'submit' key is present in the options array.
 *                                             - epiphanyInput: An Input object.
 *                                             - ascensionInput: An Input object.
 *                                             - corpusChristiInput: An Input object.
 *                                             - eternalHighPriestInput: An Input object.
 *                                             - yearTypeInput: An Input object.
 *                                             - localeInput: An Input object.
 *                                             - acceptHeaderInput: An Input object.
 * @method after(string $after) Sets the HTML to add after the form.
 * @method getForm(?PathType $pathType = null) Returns the HTML for the form.
 * @method static getLocale() Returns the locale used by the API options component.
 *
 * @package LiturgicalCalendar\Components
 * @author John Romano D'Orazio
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
    public YearType $yearTypeInput;
    public Locale $localeInput;
    public AcceptHeader $acceptHeaderInput;
    public string $currentSetLocale       = '';
    public string $expectedTextDomainPath = '';
    public string $currentTextDomainPath  = '';
    private static $apiUrl                = 'https://litcal.johnromanodorazio.com/api/dev';

    /**
     * Constructor for ApiOptions class.
     *
     * Initializes the class properties based on the provided options array.
     * If the 'formLabel', 'locale', 'wrapper', or 'submit' keys are present in the options array,
     * sets the corresponding property values accordingly.
     * If the 'formLabel' key is present, instantiates a new FormLabel object with the provided value.
     * If the 'locale' key is present, canonicalizes the locale value and stores it in the static $locale property.
     *
     * If the 'submit' or 'wrapper' keys are present in the options array,
     * checks the boolean value associated with them and instantiates a new Submit or Wrapper object accordingly.
     * The 'wrapper' key can be set to 'div' or 'form' to specify whether the wrapper should be a div or form element.
     * It can also be set to an associative array with the 'class' key to set the class attribute of the wrapper element,
     * the 'id' key to set the id attribute of the wrapper element, and the 'as' key to set the html tag of the wrapper element.
     *
     * Prepares localization using the prepareL10n method.
     * Initializes various input objects such as Epiphany, Ascension, CorpusChristi, EternalHighPriest, YearType, Locale, and AcceptHeader.
     */
    public function __construct(?array $options = null)
    {
        if ($options !== null && count($options) > 0) {
            foreach ($options as $key => $value) {
                switch ($key) {
                    case 'locale':
                        $value = null !== $value ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : 'en-US';
                        self::$locale = \Locale::canonicalize($value);
                        break;
                    case 'formLabel':
                        if (is_bool($value) && $value === true) {
                            $this->$key = new FormLabel();
                        } elseif (is_string($value) || is_array($value)) {
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
                            $value = preg_replace('/<script.*?>.*?<\/script>/s', '', $value);
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

        $this->epiphanyInput          = new Epiphany();
        $this->ascensionInput         = new Ascension();
        $this->corpusChristiInput     = new CorpusChristi();
        $this->eternalHighPriestInput = new EternalHighPriest();
        $this->yearTypeInput          = new YearType();
        $this->localeInput            = new Locale();
        $this->acceptHeaderInput      = new AcceptHeader();
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
     *
     * @throws \Exception If the static property or method does not exist.
     *
     * @return mixed The value of the static property or the result of the static method.
     */
    public static function __callStatic($name, $arguments)
    {
        if ($name === 'baseLocale') {
            return \Locale::getPrimaryLanguage(self::$locale);
        }
        throw new \Exception("Static property {$name} does not exist");
    }

    /**
     * Initializes the localization for the component.
     *
     * If the ApiOptions::$locale is not set, it defaults to 'en-US'.
     * Then, it sets the locale using setlocale with the following order of preference:
     *    1. ApiOptions::$locale with '.utf8' or '.UTF-8' appended
     *    2. ApiOptions::$locale without any suffix
     *    3. The base locale (retrieved with a magic getter) with '_' followed by its uppercase version
     *       and '.utf8' or '.UTF-8' appended
     *    4. The base locale with '_' followed by its uppercase version without any suffix
     *    5. The base locale with '.utf8' or '.UTF-8' appended
     *    6. The base locale without any suffix
     *
     * After setting the locale, it binds the textdomain 'litcal' to the 'i18n' directory.
     * The textdomain is then set to 'litcal'.
     */
    private function prepareL10n(): void
    {
        if (self::$locale === null) {
            self::$locale = 'en-US';
        }
        /** @disregard P1014 because self::$baseLocale is a magic variable retrieved with a magic getter */
        $baseLocale = self::baseLocale();
        $localeArray = [
            self::$locale . '.utf8',
            self::$locale . '.UTF-8',
            self::$locale,
            $baseLocale . '_' . strtoupper($baseLocale) . '.utf8',
            $baseLocale . '_' . strtoupper($baseLocale) . '.UTF-8',
            $baseLocale . '_' . strtoupper($baseLocale),
            $baseLocale . '.utf8',
            $baseLocale . '.UTF-8',
            $baseLocale
        ];
        $this->currentSetLocale = setlocale(LC_ALL, $localeArray);
        $this->expectedTextDomainPath = __DIR__ . "/ApiOptions/i18n";
        $this->currentTextDomainPath = bindtextdomain("litcompphp", $this->expectedTextDomainPath);
        if ($this->currentTextDomainPath !== $this->expectedTextDomainPath) {
            die("Failed to bind text domain, expected path: {$this->expectedTextDomainPath}, current path: {$this->currentTextDomainPath}");
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
            . $this->localeInput->get();
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
        return $this->yearTypeInput->get()
            . ($this->acceptHeaderInput->isHidden() ? '' : $this->acceptHeaderInput->get());
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
        $html = preg_replace('/<script.*?>.*?<\/script>/s', '', $html);
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
