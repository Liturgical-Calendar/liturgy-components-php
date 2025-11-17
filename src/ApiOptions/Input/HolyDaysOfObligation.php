<?php

namespace LiturgicalCalendar\Components\ApiOptions\Input;

use LiturgicalCalendar\Components\ApiOptions\Input;

/**
 * Class to generate an HTML multi-select element for holydays of obligation.
 *
 * This class provides the necessary methods to generate an HTML multi-select element
 * for configuring which holydays of obligation should be included in the calendar.
 * The generated HTML includes the element's class, id, and options attributes.
 *
 * @see LiturgicalCalendar\Components\ApiOptions\Input
 */
final class HolyDaysOfObligation extends Input
{
    private const HOLY_DAYS_VALS = [
        'Christmas'            => 'Christmas',
        'Epiphany'             => 'Epiphany',
        'Ascension'            => 'Ascension',
        'CorpusChristi'        => 'Corpus Christi',
        'MaryMotherOfGod'      => 'Mary, Mother of God',
        'ImmaculateConception' => 'Immaculate Conception',
        'Assumption'           => 'Assumption',
        'StJoseph'             => 'St. Joseph',
        'StsPeterPaulAp'       => 'Sts. Peter and Paul, Apostles',
        'AllSaints'            => 'All Saints'
    ];

    public function __construct()
    {
        $this->data(['param' => 'holydays_of_obligation']);
        $this->name('holydays_of_obligation[]');
        $this->id('holydays_of_obligation');
        $this->selectedValue(array_keys(self::HOLY_DAYS_VALS));
    }

    /**
     * Sets the name attribute of the input element.
     *
     * Overrides the parent method to ensure the name always ends with '[]'
     * for proper array serialization in PHP when the form is submitted.
     *
     * @param string $name The value of the name attribute.
     * @return self The instance of the class, for chaining.
     */
    public function name(string $name): self
    {
        // Ensure name ends with [] for multi-select array serialization
        if (!str_ends_with($name, '[]')) {
            $name .= '[]';
        }
        $this->name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        return $this;
    }

    /**
     * Generates and returns an HTML string for the holydays of obligation multi-select element.
     *
     * The element is a `<select multiple>` with the possible holydays of obligation.
     * The element's class can be set using the `inputClass` property. A global
     * class can be set for all input elements using the `setGlobalInputClass`
     * static method.
     *
     * The element's wrapper element can be set using the `wrapper` property. A
     * global wrapper can be set for all elements using the `setGlobalWrapper`
     * static method. The wrapper element's class can be set using the
     * `wrapperClass` property. A global class can be set for all wrapper
     * elements using the `setGlobalWrapperClass` static method.
     *
     * @return string The HTML for the holydays of obligation multi-select element.
     */
    public function get(): string
    {
        $html = '';

        $labelClass = $this->labelClass !== null
            ? " class=\"{$this->labelClass}\""
            : ( self::$globalLabelClass !== null
                ? ' class="' . self::$globalLabelClass . '"'
                : '' );
        $labelAfter = $this->labelAfter !== null ? ' ' . $this->labelAfter : '';

        $inputClass = $this->inputClass !== null
            ? " class=\"{$this->inputClass}\""
            : ( self::$globalInputClass !== null
                ? ' class="' . self::$globalInputClass . '"'
                : '' );

        $wrapperClass = $this->wrapperClass !== null
            ? " class=\"{$this->wrapperClass}\""
            : ( self::$globalWrapperClass !== null
                ? ' class="' . self::$globalWrapperClass . '"'
                : '' );
        $wrapper      = $this->wrapper !== null
            ? $this->wrapper
            : ( self::$globalWrapper !== null
                ? self::$globalWrapper
                : null );

        $disabled = $this->disabled ? ' readonly' : '';

        $options     = array_map(
            fn (string $val, string $name): string => "<option value=\"$val\"" . ( is_array($this->selectedValue) && in_array($val, $this->selectedValue) ? ' selected' : '' ) . ( $this->disabled ? ' disabled' : '' ) . ">$name</option>",
            array_keys(self::HOLY_DAYS_VALS),
            array_values(self::HOLY_DAYS_VALS)
        );
        $optionsHtml = implode('', $options);

        $data      = $this->getData();
        $for       = $this->id !== '' ? " for=\"{$this->id}\"" : '';
        $id        = $this->id !== '' ? " id=\"{$this->id}\"" : '';
        $name      = $this->name !== '' ? " name=\"{$this->name}\"" : '';
        $labelText = 'holydays_of_obligation';

        $html .= $wrapper !== null ? "<{$wrapper}{$wrapperClass}>" : '';
        $html .= "<label{$labelClass}{$for}>{$labelText}{$labelAfter}</label>";
        $html .= "<select{$id}{$name}{$inputClass}{$data}{$disabled} multiple>$optionsHtml</select>";
        $html .= $wrapper !== null ? "</{$wrapper}>" : '';
        return $html;
    }
}
