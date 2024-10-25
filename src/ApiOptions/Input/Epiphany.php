<?php

namespace LiturgicalCalendar\Components\ApiOptions\Input;

use LiturgicalCalendar\Components\ApiOptions;
use LiturgicalCalendar\Components\ApiOptions\Input;
use DateTime;
use IntlDateFormatter;

final class Epiphany extends Input
{
    public array $data           = [
        'param' => 'epiphany'
    ];

    public function __construct()
    {
        $this->name('epiphany');
        $this->id('epiphany');
    }

    /**
     * Generates and returns an HTML string for a select element.
     *
     * This method creates an HTML string representing a select element.
     * It uses class attributes for styling the label, input, and wrapper.
     * The generated HTML includes the select element's class, id, and contents.
     *
     * The element's wrapper element can be set using the `wrapper` property.  A
     * global wrapper can be set for all elements using the `setGlobalWrapper`
     * static method.  The wrapper element's class can be set using the
     * `wrapperClass` property.  A global class can be set for all wrapper
     * elements using the `setGlobalWrapperClass` static method.
     *
     * The select element contains options for the Epiphany date.
     * The options are:
     *   - January 6th
     *   - Sunday between January 2nd and 8th
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
        $labelAfter = $this->labelAfter !== null ? ' ' . $this->labelAfter : '';

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
        $Jan6 = '';
        $date = new DateTime('2024-01-06');
        /** @disregard P1014 because ApiOptions::$baseLocale is a magic variable retrieved with a magic getter */
        if (ApiOptions::baseLocale() === 'en') {
            $Jan6 = $date->format('F jS');
        } else {
            $formatter = new IntlDateFormatter(
                ApiOptions::$locale,
                IntlDateFormatter::LONG,    // Use LONG format, which typically shows full month and day
                IntlDateFormatter::NONE     // No time formatting
            );
            $pattern = $formatter->getPattern();
            $patternWithoutYear = preg_replace('/,? y/', '', $pattern);
            $formatter->setPattern($patternWithoutYear);
            $Jan6 = $formatter->format($date);
        }
        $SundayJan2Jan8 = dgettext('litcompphp', 'Sunday between January 2nd and 8th');
        $for = $this->id !== '' ? " for=\"{$this->id}\"" : '';
        $id = $this->id !== '' ? " id=\"{$this->id}\"" : '';
        $name = $this->name !== '' ? " name=\"{$this->name}\"" : '';
        $input = <<<ELEMENT
<select{$id}{$name}{$inputClass}{$data}{$disabled}>
    <option value="">--</option>
    <option value="JAN6">{$Jan6}</option>
    <option value="SUNDAY_JAN2_JAN8">{$SundayJan2Jan8}</option>
</select>
ELEMENT;
        $html .= $wrapper !== null ? "<{$wrapper}{$wrapperClass}>" : '';
        $html .= "<label{$labelClass}{$for}>epiphany{$labelAfter}</label>";
        $html .= $input;
        $html .= $wrapper !== null ? "</{$wrapper}>" : '';
        return $html;
    }
}
