<?php

namespace LiturgicalCalendar\Components\ApiOptions\Input;

use LiturgicalCalendar\Components\ApiOptions;
use LiturgicalCalendar\Components\ApiOptions\Input;
use DateTime;
use IntlDateFormatter;

/**
 * Generates an HTML string for an Ascension date select element.
 *
 * This class uses the methods of the parent Input class to generate an HTML string
 * for an Ascension date select element. It uses class attributes for styling the
 * label, input, and wrapper. The generated HTML includes the select element's
 * class, id, and contents.
 *
 * @see LiturgicalCalendar\Components\ApiOptions\Input
 * @see LiturgicalCalendar\Components\ApiOptions
 */
final class Ascension extends Input
{
    public array $data = [
        'param' => 'ascension'
    ];

    public function __construct()
    {
        $this->name('ascension');
        $this->id('ascension');
    }

    /**
     * Generates and returns an HTML string for an Ascension date select element.
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
     * The select element contains options for the Ascension date.
     * The options are:
     *   - Thursday
     *   - Sunday
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
        $html .= "<label{$labelClass}{$for}>ascension{$labelAfter}</label>";
        $html .= "<select{$id}{$name}{$inputClass}{$data}{$disabled}>$optionsHtml</select>";
        $html .= $wrapper !== null ? "</{$wrapper}>" : '';
        return $html;
    }
}
