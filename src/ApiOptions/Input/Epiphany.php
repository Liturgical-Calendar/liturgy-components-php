<?php

namespace LiturgicalCalendar\Components\ApiOptions\Input;

use LiturgicalCalendar\Components\ApiOptions;
use LiturgicalCalendar\Components\ApiOptions\Input;
use DateTime;
use IntlDateFormatter;

/**
 * Represents the input element for selecting the Epiphany date in the Liturgical Calendar API options form.
 * This class extends the Input class and provides specific configurations for the Epiphany select element.
 *
 * The select element contains options for the Epiphany date, which include:
 * - January 6th
 * - Sunday between January 2nd and 8th
 *
 * @see LiturgicalCalendar\Components\ApiOptions\Input
 * @see LiturgicalCalendar\Components\ApiOptions
 */
final class Epiphany extends Input
{
    public function __construct()
    {
        $this->data(['param' => 'epiphany']);
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

        $Jan6 = '';
        $date = new DateTime('2024-01-06');
        /** @disregard P1014 because ApiOptions::baseLocale() is a magic method retrieved with a magic getter */
        if (ApiOptions::baseLocale() === 'en') {
            $Jan6 = $date->format('F jS');
        } else {
            $formatter = new IntlDateFormatter(
                ApiOptions::getLocale(),
                IntlDateFormatter::LONG,    // Use LONG format, which typically shows full month and day
                IntlDateFormatter::NONE     // No time formatting
            );

            $pattern = $formatter->getPattern();
            if (false === $pattern) {
                throw new \Exception('Failed to get pattern from formatter');
            }
            $patternWithoutYear = preg_replace('/,? y/', '', $pattern);
            if (false === is_string($patternWithoutYear)) {
                throw new \Exception('Failed to remove year from pattern for formatter');
            }
            $formatter->setPattern($patternWithoutYear);
            $Jan6 = $formatter->format($date) ?: $date->format('F jS');
        }
        $SundayJan2Jan8 = dgettext('litcompphp', 'Sunday between January 2nd and 8th');

        $optionsArray = [
            ''                 => '--',
            'JAN6'             => $Jan6,
            'SUNDAY_JAN2_JAN8' => $SundayJan2Jan8
        ];
        $options      = array_map(
            fn (string $k, string $v): string => "<option value=\"{$k}\"" . ( $this->selectedValue === $k ? ' selected' : '' ) . ">{$v}</option>",
            array_keys($optionsArray),
            array_values($optionsArray)
        );
        $optionsHtml  = implode('', $options);

        $data = $this->getData();
        $for  = $this->id !== '' ? " for=\"{$this->id}\"" : '';
        $id   = $this->id !== '' ? " id=\"{$this->id}\"" : '';
        $name = $this->name !== '' ? " name=\"{$this->name}\"" : '';

        $html .= $wrapper !== null ? "<{$wrapper}{$wrapperClass}>" : '';
        $html .= "<label{$labelClass}{$for}>epiphany{$labelAfter}</label>";
        $html .= "<select{$id}{$name}{$inputClass}{$data}{$disabled}>$optionsHtml</select>";
        $html .= $wrapper !== null ? "</{$wrapper}>" : '';
        return $html;
    }
}
