<?php

namespace LiturgicalCalendar\Components\ApiOptions\Input;

use LiturgicalCalendar\Components\ApiOptions\Input;

final class EternalHighPriest extends Input
{
    public array $data           = [
        'param' => 'eternal_high_priest'
    ];


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
            : (self::$globalLabelClass !== null
                ? ' class="' . self::$globalLabelClass . '"'
                : '');

        $inputClass = $this->inputClass !== null
            ? " class=\"{$this->inputClass}\""
            : (self::$globalInputClass !== null
                ? ' class="' . self::$globalInputClass . '"'
                : '');

        $wrapperClass = $this->wrapperClass !== null
            ? " class=\"{$this->wrapperClass}\""
            : (self::$globalWrapperClass !== null
                ? ' class="' . self::$globalWrapperClass . '"'
                : '');
        $wrapper = $this->wrapper !== null
            ? $this->wrapper
            : (self::$globalWrapper !== null
                ? self::$globalWrapper
                : null);

        $data = $this->getData();
        $true = _('true');
        $false = _('false');
        $input = <<<ELEMENT
<select{$this->id}{$inputClass}{$data}>
    <option value="">--</option>
    <option value="true">{$true}</option>
    <option value="false">{$false}</option>
</select>
ELEMENT;
        $html .= $wrapper !== null ? "<{$wrapper}{$wrapperClass}>" : '';
        $html .= "<label{$labelClass}>eternal_high_priest</label>";
        $html .= $input;
        $html .= $wrapper !== null ? "</{$wrapper}>" : '';
        return $html;
    }
}
