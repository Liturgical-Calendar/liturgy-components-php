<?php

namespace LiturgicalCalendar\Components\ApiOptions\Input;

use LiturgicalCalendar\Components\ApiOptions\Input;

final class Year extends Input
{
    public array $data = [
        'param' => 'year'
    ];

    public function __construct()
    {
        $this->name('year');
        $this->id('year');
    }

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

        $data = $this->getData();
        $for  = $this->id !== '' ? " for=\"{$this->id}\"" : '';
        $id   = $this->id !== '' ? " id=\"{$this->id}\"" : '';
        $name = $this->name !== '' ? " name=\"{$this->name}\"" : '';

        $defaultValue = ( is_int($this->selectedValue) && $this->selectedValue >= 1970 && $this->selectedValue <= 9999 )
            ? " value=\"{$this->selectedValue}\""
            : (
                ( is_numeric($this->selectedValue) && (int) $this->selectedValue >= 1970 && (int) $this->selectedValue <= 9999 )
                    ? " value=\"{$this->selectedValue}\""
                    : ' value="' . date('Y') . '"'
            );
        $html        .= $wrapper !== null ? "<{$wrapper}{$wrapperClass}>" : '';
        $html        .= "<label{$labelClass}{$for}>year{$labelAfter}</label>";
        $html        .= "<input type=\"number\"{$id}{$name}{$inputClass}{$data}{$disabled} min=\"1970\" max=\"9999\"{$defaultValue} />";
        $html        .= $wrapper !== null ? "</{$wrapper}>" : '';
        return $html;
    }
}
