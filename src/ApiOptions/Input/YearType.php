<?php

namespace LiturgicalCalendar\Components\ApiOptions\Input;

use LiturgicalCalendar\Components\ApiOptions\Input;

final class YearType extends Input
{
    public array $data           = [
        'param' => 'year_type'
    ];


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
        $disabled = $this->disabled ? ' disabled' : '';

        $data = $this->getData();
        $input = <<<ELEMENT
<select{$this->id}{$inputClass}{$data}{$disabled}>
    <option value="LITURGICAL">LITURGICAL</option>
    <option value="CIVIL">CIVIL</option>
</select>
ELEMENT;
        $html .= $wrapper !== null ? "<{$wrapper}{$wrapperClass}>" : '';
        $html .= "<label{$labelClass}>year_type</label>";
        $html .= $input;
        $html .= $wrapper !== null ? "</{$wrapper}>" : '';
        return $html;
    }
}
