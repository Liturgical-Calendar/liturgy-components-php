<?php

namespace LiturgicalCalendar\Components\ApiOptions\Input;

use LiturgicalCalendar\Components\ApiOptions;
use LiturgicalCalendar\Components\ApiOptions\Input;
use DateTime;
use IntlDateFormatter;

final class CorpusChristi extends Input
{
    public array $data           = [
        'param' => 'corpus_christi'
    ];

    public function __construct()
    {
        $this->name('corpus_christi');
        $this->id('corpus_christi');
    }


    /**
     * Generates and returns an HTML string for the Corpus Christi select element.
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
     * The select element contains options for the Corpus Christi date.
     * The select element has two options: Thursday and Sunday.
     * The locale used for formatting the dates is the one set with {@see ApiOptions::$locale}.
     *
     * @return string The HTML string for the Corpus Christi select element.
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
        $today = new DateTime();
        $nextThursday = $today->modify('next Thursday');
        $formatter = new IntlDateFormatter(
            ApiOptions::$locale,
            IntlDateFormatter::LONG,    // Use LONG format, which typically shows full month and day
            IntlDateFormatter::NONE,    // No time formatting
            null,
            null,
            'EEEE'
        );
        $Thursday = $formatter->format($nextThursday);
        $nextSunday = $today->modify('next Sunday');
        $Sunday = $formatter->format($nextSunday);
        $optionsArray = [
            "" => "--",
            'THURSDAY' => $Thursday,
            'SUNDAY' => $Sunday
        ];
        $options = array_map(fn(string $k, string $v) => "<option value=\"{$k}\"" . ($this->selectedValue === $v ? ' selected' : '') . ">{$v}</option>", array_keys($optionsArray), array_values($optionsArray));
        $optionsHtml = implode('', $options);
        $for = $this->id !== '' ? " for=\"{$this->id}\"" : '';
        $id = $this->id !== '' ? " id=\"{$this->id}\"" : '';
        $name = $this->name !== '' ? " name=\"{$this->name}\"" : '';
        $input = "<select{$id}{$name}{$inputClass}{$data}{$disabled}>$optionsHtml</select>";

        $html .= $wrapper !== null ? "<{$wrapper}{$wrapperClass}>" : '';
        $html .= "<label{$labelClass}{$for}>corpus_christi{$labelAfter}</label>";
        $html .= $input;
        $html .= $wrapper !== null ? "</{$wrapper}>" : '';
        return $html;
    }
}
