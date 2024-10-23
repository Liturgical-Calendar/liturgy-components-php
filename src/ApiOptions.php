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
 * Class ApiOptions
 * A class that can be used to generate an options form for the Liturgical Calendar API.
 *
 * The class contains methods to generate the form, form label, form wrapper, and form submit elements.
 * The form elements can be fully customized using the methods provided by the class.
 *
 * @package LiturgicalCalendar\Components
 * @author John Romanodorazio
 */
class ApiOptions
{
    public ?Submit $submit        = null;
    public ?FormLabel $formLabel  = null;
    public ?Wrapper $wrapper      = null;
    public ?string $class         = null;
    public ?string $id            = null;
    public static ?string $locale = null;
    public Epiphany $epiphanyInput;
    public Ascension $ascensionInput;
    public CorpusChristi $corpusChristiInput;
    public EternalHighPriest $eternalHighPriestInput;
    public YearType $yearTypeInput;
    public Locale $localeInput;
    public AcceptHeader $acceptHeaderInput;

    /**
     * Constructor for ApiOptions class.
     *
     * Initializes the class properties based on the provided options array.
     * If the 'class', 'id', 'formLabel', or 'locale' keys are present in the options array,
     * sets the corresponding property values accordingly.
     * If the 'formLabel' key is present, instantiates a new FormLabel object with the provided value.
     * If the 'locale' key is present, canonicalizes the locale value and stores it in the static $locale property.
     *
     * If the 'submit' or 'wrapper' keys are present in the options array,
     * checks the boolean value associated with them and instantiates a new Submit or Wrapper object accordingly.
     *
     * Prepares localization using the prepareL10n method.
     * Initializes various input objects such as Epiphany, Ascension, CorpusChristi, EternalHighPriest, YearType, Locale, and AcceptHeader.
     */
    public function __construct(array $options = null)
    {
        if ($options !== null && count($options) > 0) {
            foreach ($options as $key => $value) {
                switch ($key) {
                    case 'locale':
                        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
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
     * Provides a way to call static methods or properties, even if they don't exist.
     * In this case, we create a magic value for ApiOptions::baseLocale().
     *
     * @param string $name The name of the static method or property.
     * @param array  $arguments The arguments to pass to the static method.
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
        $setLocale = setlocale(LC_ALL, $localeArray);
        bindtextdomain("litcal", dirname(__FILE__) . "/ApiOptions/i18n");
        textdomain("litcal");
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
            . $this->acceptHeaderInput->get();
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

        if ($this->wrapper !== null) {
            return $this->wrapper->get($html, $this);
        }
        return $html;
    }
}
