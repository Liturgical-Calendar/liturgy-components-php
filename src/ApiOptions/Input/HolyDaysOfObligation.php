<?php

namespace LiturgicalCalendar\Components\ApiOptions\Input;

use LiturgicalCalendar\Components\ApiOptions\Input;

/**
 * Class to generate an HTML select element for the 'accept' header of the API.
 *
 * This class provides the necessary methods to generate an HTML select element
 * for the 'accept' header of the API. The generated HTML includes the element's
 * class, id, and options attributes.
 *
 * @see LiturgicalCalendar\Components\ApiOptions\Input
 */
final class HolyDaysOfObligation extends Input
{
    private bool $hidden         = false;
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
        $this->name('holydays_of_obligation');
        $this->id('holydays_of_obligation');
        $this->selectedValue(array_keys(self::HOLY_DAYS_VALS));
    }

    /**
     * Sets the hidden property to true.
     *
     * @return self The instance of the class, for chaining.
     */
    public function hide(): self
    {
        $this->hidden = true;
        return $this;
    }

    /**
     * Checks if the accept header input field is hidden.
     *
     * @return bool true if the accept header input field is hidden, false otherwise.
     */
    public function isHidden(): bool
    {
        return $this->hidden;
    }

    /**
     * Generates and returns an HTML string for an accept header input element.
     *
     * The element is a `<select>` with the possible values for the accept header.
     * The element's class can be set using the `inputClass` property.  A global
     * class can be set for all input elements using the `setGlobalInputClass`
     * static method.
     *
     * The element's wrapper element can be set using the `wrapper` property.  A
     * global wrapper can be set for all elements using the `setGlobalWrapper`
     * static method.  The wrapper element's class can be set using the
     * `wrapperClass` property.  A global class can be set for all wrapper
     * elements using the `setGlobalWrapperClass` static method.
     *
     * @return string The HTML for the accept header input element.
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
            fn ($val, $name) => "<option value=\"$val\"" . ( in_array($val, $this->selectedValue) ? ' selected' : '' ) . ( $this->disabled ? ' disabled' : '' ) . ">$name</option>",
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
