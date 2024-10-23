<?php

namespace LiturgicalCalendar\Components\ApiOptions\Input;

use LiturgicalCalendar\Components\ApiOptions\Input;

final class AcceptHeader extends Input
{
    public array $data           = [
        'param' => 'accept'
    ];


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
        $input = <<<ELEMENT
<select{$this->id}{$inputClass}{$data}>
    <option value="application/json">application/json</option>
    <option value="application/xml">application/xml</option>
    <option value="application/yaml">application/yaml</option>
    <option value="text/calendar">text/calendar</option>
</select>
ELEMENT;
        $html .= $wrapper !== null ? "<{$wrapper}{$wrapperClass}>" : '';
        $html .= "<label{$labelClass}>accept header</label>";
        $html .= $input;
        $html .= $wrapper !== null ? "</{$wrapper}>" : '';
        return $html;
    }
}
