<?php

namespace LiturgicalCalendar\Components\ApiOptions\Input;

use LiturgicalCalendar\Components\ApiOptions\Input;

final class AcceptHeader extends Input
{
    public array $data           = [
        'param' => 'accept'
    ];

    private bool $as_return_type_param = false;
    private bool $hidden               = false;
    private ?string $labelAfter        = null;

    /**
     * @param bool $as_return_type_param Set to true to include the accept header as a query parameter,
     *     or false to not include it.  The default is true.
     *
     * @return self The instance of the class, for chaining.
     */
    public function asReturnTypeParam(bool $as_return_type_param = true): self
    {
        $this->as_return_type_param = $as_return_type_param;
        return $this;
    }

    /**
     * Sets the hidden property to true.
     *
     * @return self The instance of the class, for chaining.
     */
    public function hide(): self
    {
        $this->hidden = true;
        return $this;
    }

    /**
     * Checks if the accept header input field is hidden.
     *
     * @return bool true if the accept header input field is hidden, false otherwise.
     */
    public function isHidden(): bool
    {
        return $this->hidden;
    }

    /**
     * Appends a string to the label after the label text.
     *
     * Note that this method will strip any PHP tags and any script tags from the
     * input string to prevent any potential security issues.
     *
     * @param string $text The string to append to the label after the label text.
     *
     * @return self The instance of the class, for chaining.
     */
    public function labelAfter(string $text): self
    {
        $text = preg_replace('/<\?php.*?\?>/s', '', $text);
        $text = preg_replace('/<script.*?>.*?<\/script>/s', '', $text);
        $this->labelAfter = $text;
        return $this;
    }

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

        $returnTypeParamContents = <<<OPTIONS
<option value="JSON">JSON</option>
<option value="XML">XML</option>
<option value="YML">YML</option>
<option value="ICS">ICS</option>
OPTIONS;
        $acceptHeaderContents = <<<OPTIONS
<option value="application/json">application/json</option>
<option value="application/xml">application/xml</option>
<option value="application/yaml">application/yaml</option>
<option value="text/calendar">text/calendar</option>
OPTIONS;
        $inputContents = $this->as_return_type_param ? $returnTypeParamContents : $acceptHeaderContents;
        $labelText = $this->as_return_type_param ? 'return_type' : 'accept header';

        $data = $this->getData();
        $input = "<select{$this->id}{$inputClass}{$data}{$disabled}>$inputContents</select>";
        $html .= $wrapper !== null ? "<{$wrapper}{$wrapperClass}>" : '';
        $html .= "<label{$labelClass}>{$labelText}{$labelAfter}</label>";
        $html .= $input;
        $html .= $wrapper !== null ? "</{$wrapper}>" : '';
        return $html;
    }
}
