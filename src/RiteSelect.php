<?php

namespace LiturgicalCalendar\Components;

use LiturgicalCalendar\Components\Rite\TextDomainTrait;

/**
 * A class to generate a select element for selecting a liturgical rite.
 *
 * The options are built from the {@see Rite} enum rather than from API
 * metadata, so this component issues no HTTP request and needs no
 * MetadataProvider: the set of rites is a fact about the liturgy, not about
 * which calendars a given API happens to serve.
 *
 * **There is no linking method, deliberately.** liturgy-components-js offers
 * `linkToRiteSelect()`, but that is a runtime DOM listener and has no
 * server-side analogue: this library renders once and ships no JavaScript.
 * Reacting to a change is the integrator's business — a form submit, a query
 * parameter, a re-render with `rite()` set on the CalendarSelect. Anything
 * interactive belongs in liturgy-components-js.
 *
 * Public Methods:
 * - {@see LiturgicalCalendar\Components\RiteSelect::__construct()} Initializes the RiteSelect object with default settings.
 * - {@see LiturgicalCalendar\Components\RiteSelect::locale()} Sets the locale for the rite select.
 * - {@see LiturgicalCalendar\Components\RiteSelect::class()} Sets the class for the select element.
 * - {@see LiturgicalCalendar\Components\RiteSelect::id()} Sets the ID for the select element.
 * - {@see LiturgicalCalendar\Components\RiteSelect::name()} Sets the name for the select element.
 * - {@see LiturgicalCalendar\Components\RiteSelect::label()} Configures whether to show the label.
 * - {@see LiturgicalCalendar\Components\RiteSelect::labelText()} Sets the text for the label.
 * - {@see LiturgicalCalendar\Components\RiteSelect::labelClass()} Sets the class for the label element.
 * - {@see LiturgicalCalendar\Components\RiteSelect::disabled()} Sets whether the select element is disabled.
 * - {@see LiturgicalCalendar\Components\RiteSelect::selectedOption()} Sets the selected rite.
 * - {@see LiturgicalCalendar\Components\RiteSelect::getSelect()} Returns the HTML for the select element.
 * - {@see LiturgicalCalendar\Components\RiteSelect::getLocale()} Returns the locale used by the rite select instance.
 *
 * @package LiturgicalCalendar\Components
 * @author John Romano D'Orazio <priest@johnromanodorazio.com>
 */
class RiteSelect
{
    use TextDomainTrait;

    private string $locale        = 'en';
    private string $class         = 'riteSelect';
    private string $id            = 'riteSelect';
    private string $name          = 'riteSelect';
    private bool $label           = false;
    private ?string $labelStr     = null;
    private string $labelClass    = '';
    private bool $disabled        = false;
    private ?Rite $selectedOption = null;

    /**
     * Creates a new instance of the RiteSelect class.
     *
     * The options array can contain the following keys:
     * - `locale`: string, the locale to use, defaults to 'en'
     * - `class`: string, the class to apply to the select element, defaults to 'riteSelect'
     * - `id`: string, the id to apply to the select element, defaults to 'riteSelect'
     * - `name`: string, the name to apply to the select element, defaults to 'riteSelect'
     * - `label`: bool, whether to include a label element, defaults to false
     * - `labelStr`: string, the text for the label element, defaults to a translated 'Select a rite'
     * - `labelClass`: string, the class to apply to the label element
     * - `disabled`: bool, whether the select element is disabled, defaults to false
     * - `selectedOption`: Rite|string, the rite to mark as selected
     *
     * ```php
     * $riteSelect = new RiteSelect([
     *     'locale' => 'it',
     *     'class'  => 'form-select',
     *     'id'     => 'riteSelect',
     *     'name'   => 'rite'
     * ]);
     * echo $riteSelect;
     * ```
     *
     * @param array{locale?:string,class?:string,id?:string,name?:string,label?:bool,labelStr?:string,labelClass?:string,disabled?:bool,selectedOption?:Rite|string} $options The options for the instance.
     */
    public function __construct(array $options = [])
    {
        if (isset($options['locale'])) {
            $this->locale($options['locale']);
        }

        $this->bindRiteTextDomain();

        if (isset($options['class'])) {
            $this->class = htmlspecialchars($options['class'], ENT_QUOTES, 'UTF-8');
        }

        if (isset($options['id'])) {
            $this->id = htmlspecialchars($options['id'], ENT_QUOTES, 'UTF-8');
        }

        if (isset($options['name'])) {
            $this->name = htmlspecialchars($options['name'], ENT_QUOTES, 'UTF-8');
        }

        if (isset($options['label'])) {
            $this->label = filter_var($options['label'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($options['labelStr'])) {
            $this->labelText($options['labelStr']);
        }

        if (isset($options['labelClass'])) {
            $this->labelClass = htmlspecialchars($options['labelClass'], ENT_QUOTES, 'UTF-8');
        }

        if (isset($options['disabled'])) {
            $this->disabled = filter_var($options['disabled'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($options['selectedOption'])) {
            $this->selectedOption($options['selectedOption']);
        }
    }

    /**
     * Sets the locale for the rite select.
     *
     * @param string $locale A valid PHP locale string such as 'en' or 'it_IT'.
     *
     * @return $this
     *
     * @throws \Exception If the locale cannot be canonicalized, or is not valid.
     */
    public function locale(string $locale): self
    {
        $canonicalized = \Locale::canonicalize($locale);
        if ($canonicalized === null) {
            throw new \Exception("Failed to canonicalize locale: {$locale}");
        }
        if (!CalendarSelect::isValidLocale($canonicalized)) {
            throw new \Exception("Invalid locale: {$canonicalized}");
        }
        $this->locale = $canonicalized;
        return $this;
    }

    /**
     * Sets the class attribute of the select element.
     *
     * @param string $className The class attribute of the select element.
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
     * @param string $id The id attribute of the select element.
     *
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
     * @param string $name The name attribute of the select element.
     *
     * @return $this
     */
    public function name(string $name): self
    {
        $this->name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        return $this;
    }

    /**
     * Sets whether a label element will be included for the select element.
     *
     * @param bool $label Whether to include a label element.
     *
     * @return $this
     */
    public function label(bool $label = true): self
    {
        $this->label = $label;
        return $this;
    }

    /**
     * Sets the text of the label element.
     *
     * @param string $text The text of the label element.
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
     * Sets whether the select element is disabled.
     *
     * @param bool $disabled Whether the select element is disabled.
     *
     * @return $this
     */
    public function disabled(bool $disabled = true): self
    {
        $this->disabled = $disabled;
        return $this;
    }

    /**
     * Sets the rite to mark as selected.
     *
     * @param Rite|string $rite A `Rite` case, or its string value.
     *
     * @return $this
     *
     * @throws \Exception If the string is not a valid rite.
     */
    public function selectedOption(Rite|string $rite): self
    {
        if (is_string($rite)) {
            $resolved = Rite::tryFrom($rite);
            if (null === $resolved) {
                $valid = implode(', ', array_map(fn(Rite $case) => $case->value, Rite::cases()));
                throw new \Exception("Invalid rite: {$rite}, valid values are: {$valid}");
            }
            $rite = $resolved;
        }
        $this->selectedOption = $rite;
        return $this;
    }

    /**
     * The option label for a rite, translated through the `rite` domain.
     *
     * dgettext rather than _(), for the same reason CalendarSelect uses it: the
     * lookup names its domain, so another component calling textdomain() cannot
     * silently redirect it.
     *
     * @param Rite $rite The rite to label.
     *
     * @return string The translated label.
     */
    private function optionLabel(Rite $rite): string
    {
        return match ($rite) {
            Rite::ROMAN     => dgettext('rite', 'Roman Rite'),
            Rite::AMBROSIAN => dgettext('rite', 'Ambrosian Rite'),
        };
    }

    /**
     * Returns the HTML for the options of the select element.
     *
     * Options render in `Rite::cases()` order — Roman, then Ambrosian — which
     * is the order liturgy-components-js renders them in.
     *
     * @return string The HTML for the select options.
     */
    private function getOptions(): string
    {
        $options = array_map(function (Rite $rite): string {
            $selectedStr = $this->selectedOption === $rite ? ' selected' : '';
            $label       = htmlspecialchars($this->optionLabel($rite), ENT_QUOTES, 'UTF-8');
            return "<option value=\"{$rite->value}\"{$selectedStr}>{$label}</option>";
        }, Rite::cases());

        return implode('', $options);
    }

    /**
     * Returns a complete HTML select element for the liturgical rites.
     *
     * @return string The HTML for the select element.
     */
    public function getSelect(): string
    {
        return $this->withRiteMessagesLocale($this->locale, fn(): string => $this->renderSelect());
    }

    /**
     * Renders the select. Called with LC_MESSAGES already set to this
     * instance's locale, so the dgettext lookups below resolve in it.
     *
     * @return string The HTML for the select element.
     */
    private function renderSelect(): string
    {
        $labelClass  = !empty($this->labelClass) ? " class=\"{$this->labelClass}\"" : '';
        $id          = !empty($this->id) ? " id=\"{$this->id}\"" : '';
        $name        = !empty($this->name) ? " name=\"{$this->name}\"" : '';
        $class       = !empty($this->class) ? " class=\"{$this->class}\"" : '';
        $disabled    = $this->disabled ? ' disabled' : '';
        $labelStr    = $this->labelStr ?? htmlspecialchars(dgettext('rite', 'Select a rite'), ENT_QUOTES, 'UTF-8');
        $optionsHtml = $this->getOptions();

        return ( $this->label ? "<label for=\"{$this->id}\"{$labelClass}>{$labelStr}</label>" : '' )
            . "<select{$id}{$name}{$class}{$disabled}>{$optionsHtml}</select>";
    }

    /**
     * Returns the locale used by the rite select instance.
     *
     * @return string The locale, a valid PHP locale string such as 'en' or 'it_IT'.
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Returns the HTML for the select element.
     *
     * @return string The HTML for the select element.
     */
    public function __toString(): string
    {
        return $this->getSelect();
    }
}
