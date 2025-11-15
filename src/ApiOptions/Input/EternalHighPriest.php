<?php

namespace LiturgicalCalendar\Components\ApiOptions\Input;

use LiturgicalCalendar\Components\ApiOptions\Input;

/**
 * Class EternalHighPriest
 *
 * This class represents the Eternal High Priest input component for the Liturgical Calendar.
 * It extends the Input class and is responsible for generating a select element
 * that allows users to specify whether to include the Eternal High Priest celebration
 * in the calendar.
 *
 * The input element is configurable, supporting the setting of element id, name,
 * and other attributes via its methods.
 * @see LiturgicalCalendar\Components\ApiOptions\Input
 */
final class EternalHighPriest extends Input
{
    public array $data = [
        'param' => 'eternal_high_priest'
    ];


    public function __construct()
    {
        $this->name('eternal_high_priest');
        $this->id('eternal_high_priest');
    }

    /**
     * Generates and returns an HTML string for a select element.
     *
     * The select element allows the user to choose whether to include the Eternal High Priest
     * celebration in the calendar.
     *
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
     * @return string The HTML string for the select element.
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

        $true  = dgettext('litcompphp', 'true');
        $false = dgettext('litcompphp', 'false');

        $optionsArray = [
            'false' => $false,
            'true'  => $true
        ];
        $options      = array_map(
            fn (string $k, string $v) => "<option value=\"{$k}\"" . ( $this->selectedValue === $k ? ' selected' : '' ) . ">{$v}</option>",
            array_keys($optionsArray),
            array_values($optionsArray)
        );
        $optionsHtml  = implode('', $options);

        $data = $this->getData();
        $for  = $this->id !== '' ? " for=\"{$this->id}\"" : '';
        $id   = $this->id !== '' ? " id=\"{$this->id}\"" : '';
        $name = $this->name !== '' ? " name=\"{$this->name}\"" : '';

        $html .= $wrapper !== null ? "<{$wrapper}{$wrapperClass}>" : '';
        $html .= "<label{$labelClass}{$for}>eternal_high_priest{$labelAfter}</label>";
        $html .= "<select{$id}{$name}{$inputClass}{$data}{$disabled}>$optionsHtml</select>";
        $html .= $wrapper !== null ? "</{$wrapper}>" : '';
        return $html;
    }
}
