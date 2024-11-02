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
    private const RETURN_TYPE_PARAM_VALS = ['JSON', 'XML', 'YML', 'ICS'];
    private const ACCEPT_HEADER_VALS     = [
        'application/json',
        'application/xml',
        'application/yaml',
        'text/calendar'
    ];

    public function __construct()
    {
        $this->name('return_type');
        $this->id('return_type');
    }

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

        $returnTypeParamOptions = array_map(
            fn ($val) => "<option value=\"$val\"" . ($this->selectedValue === $val ? ' selected' : '') . ">$val</option>",
            self::RETURN_TYPE_PARAM_VALS
        );
        $acceptHeaderOptions = array_map(
            fn ($val) => "<option value=\"$val\"" . ($this->selectedValue === $val ? ' selected' : '') . ">$val</option>",
            self::ACCEPT_HEADER_VALS
        );
        $optionsHtml = $this->as_return_type_param ? implode('', $returnTypeParamOptions) : implode('', $acceptHeaderOptions);

        $data = $this->getData();
        $for = $this->id !== '' ? " for=\"{$this->id}\"" : '';
        $id = $this->id !== '' ? " id=\"{$this->id}\"" : '';
        $name = $this->name !== '' ? " name=\"{$this->name}\"" : '';
        $labelText = $this->as_return_type_param ? 'return_type' : 'accept header';

        $html .= $wrapper !== null ? "<{$wrapper}{$wrapperClass}>" : '';
        $html .= "<label{$labelClass}{$for}>{$labelText}{$labelAfter}</label>";
        $html .= "<select{$id}{$name}{$inputClass}{$data}{$disabled}>$optionsHtml</select>";
        $html .= $wrapper !== null ? "</{$wrapper}>" : '';
        return $html;
    }
}
