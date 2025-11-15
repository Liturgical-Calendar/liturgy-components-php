<?php

namespace LiturgicalCalendar\Components\ApiOptions\Input;

use LiturgicalCalendar\Components\ApiOptions\Input;

/**
 * Class YearType
 *
 * Represents the year type input option for the Liturgical Calendar.
 * This class provides functionality to generate a select element
 * for choosing between different year types such as liturgical or civil.
 * It extends the Input class and utilizes various settings for
 * configuring the element's attributes and appearance.
 * @see LiturgicalCalendar\Components\ApiOptions\Input
 */
final class YearType extends Input
{
    public array $data = [
        'param' => 'year_type'
    ];

    public function __construct()
    {
        $this->name('year_type');
        $this->id('year_type');
    }

    /**
     * Generates and returns an HTML string for the year type select element.
     *
     * This method creates an HTML string representing a select element
     * for choosing the year type. It uses class attributes for styling
     * the label, input, and wrapper. The generated HTML includes the
     * select element's class, id, and contents.
     *
     * The element's wrapper element can be set using the `wrapper` property.
     * A global wrapper can be set for all elements using the `setGlobalWrapper`
     * static method. The wrapper element's class can be set using the
     * `wrapperClass` property. A global class can be set for all wrapper
     * elements using the `setGlobalWrapperClass` static method.
     *
     * The select element contains options for the year type:
     *   - Liturgical year
     *   - Civil year
     *
     * @return string The HTML string for the year type select element.
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

        $disabled = $this->disabled ? ' disabled' : '';

        $optionsArray = [ 'LITURGICAL', 'CIVIL' ];
        $options      = array_map(
            fn (string $option) => "<option value=\"{$option}\"" . ( $this->selectedValue === $option ? ' selected' : '' ) . ">{$option}</option>",
            $optionsArray
        );
        $optionsHtml  = implode('', $options);


        $data = $this->getData();
        $for  = $this->id !== '' ? " for=\"{$this->id}\"" : '';
        $id   = $this->id !== '' ? " id=\"{$this->id}\"" : '';
        $name = $this->name !== '' ? " name=\"{$this->name}\"" : '';

        $html .= $wrapper !== null ? "<{$wrapper}{$wrapperClass}>" : '';
        $html .= "<label{$labelClass}{$for}>year_type{$labelAfter}</label>";
        $html .= "<select{$id}{$name}{$inputClass}{$data}{$disabled}>$optionsHtml</select>";
        $html .= $wrapper !== null ? "</{$wrapper}>" : '';
        return $html;
    }
}
