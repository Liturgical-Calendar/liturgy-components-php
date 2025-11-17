<?php

namespace LiturgicalCalendar\Components\ApiOptions\Input;

use LiturgicalCalendar\Components\ApiOptions;
use LiturgicalCalendar\Components\ApiOptions\Input;
use DateTime;
use IntlDateFormatter;

/**
 * Class CorpusChristi
 *
 * Represents an input option for selecting the date of Corpus Christi.
 * This class provides functionality to generate the HTML for a select
 * element with options for the Corpus Christi date.
 *
 * It extends the Input class and utilizes locale settings for formatting
 * options. The select element contains two options: Thursday and Sunday.
 * @see LiturgicalCalendar\Components\ApiOptions\Input
 * @see LiturgicalCalendar\Components\ApiOptions
 */
final class CorpusChristi extends Input
{
    public function __construct()
    {
        $this->data(['param' => 'corpus_christi']);
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
     * The locale used for formatting the dates is the one set in {@see ApiOptions::getLocale()}.
     *
     * @return string The HTML string for the Corpus Christi select element.
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

        $today        = new DateTime();
        $nextThursday = $today->modify('next Thursday');
        $formatter    = new IntlDateFormatter(
            ApiOptions::getLocale(),
            IntlDateFormatter::LONG,    // Use LONG format, which typically shows full month and day
            IntlDateFormatter::NONE,    // No time formatting
            null,
            null,
            'EEEE'
        );
        $Thursday     = $formatter->format($nextThursday) ?: 'Thursday';
        $nextSunday   = $today->modify('next Sunday');
        $Sunday       = $formatter->format($nextSunday) ?: 'Sunday';

        $optionsArray = [
            ''         => '--',
            'THURSDAY' => $Thursday,
            'SUNDAY'   => $Sunday
        ];
        $options      = array_map(
            fn(string $k, string $v): string => "<option value=\"{$k}\"" . ( $this->selectedValue === $k ? ' selected' : '' ) . ">{$v}</option>",
            array_keys($optionsArray),
            array_values($optionsArray)
        );
        $optionsHtml  = implode('', $options);

        $data = $this->getData();
        $for  = $this->id !== '' ? " for=\"{$this->id}\"" : '';
        $id   = $this->id !== '' ? " id=\"{$this->id}\"" : '';
        $name = $this->name !== '' ? " name=\"{$this->name}\"" : '';

        $html .= $wrapper !== null ? "<{$wrapper}{$wrapperClass}>" : '';
        $html .= "<label{$labelClass}{$for}>corpus_christi{$labelAfter}</label>";
        $html .= "<select{$id}{$name}{$inputClass}{$data}{$disabled}>$optionsHtml</select>";
        $html .= $wrapper !== null ? "</{$wrapper}>" : '';
        return $html;
    }
}
