<?php

namespace LiturgicalCalendar\Components\ApiOptions\Input;

use LiturgicalCalendar\Components\ApiOptions;

class Locale extends ApiOptions\Input
{
    public array $data           = [
        'param' => 'locale'
    ];

    private static array $apiLocales        = [];
    private static array $apiLocalesDisplay = [];

    /**
     * Fetches the list of locales from the Liturgical Calendar API and stores
     * them in the self::$apiLocales static property.
     *
     * If the ApiOptions::$locale property is set, it will also generate a
     * sorted list of locales with their display names in the given locale
     * and store it in the self::$apiLocalesDisplay static property.
     *
     * If the list of locales has already been fetched, it will not fetch it
     * again. Instead, it will return the stored list.
     *
     * @throws \Exception If there is an error fetching or decoding the list of
     *                     locales from the Liturgical Calendar API.
     */
    public function __construct()
    {
        if (empty(self::$apiLocales)) {
            $metadataRaw = file_get_contents('https://litcal.johnromanodorazio.com/api/dev/calendars');
            if ($metadataRaw === false) {
                throw new \Exception('Failed to fetch locales from https://litcal.johnromanodorazio.com/api/dev/calendars');
            }
            $metadataJson = json_decode($metadataRaw);
            if (JSON_ERROR_NONE !== json_last_error()) {
                throw new \Exception('Failed to decode locales from https://litcal.johnromanodorazio.com/api/dev/calendars');
            }
            if (
                false === property_exists($metadataJson, 'litcal_metadata')
                || false === property_exists($metadataJson->litcal_metadata, 'locales')
                || false === is_array($metadataJson->litcal_metadata->locales)
            ) {
                throw new \Exception('Invalid `locales` property from https://litcal.johnromanodorazio.com/api/dev/calendars: ' . var_export($metadataJson->litcal_metadata->locales, true));
            }

            self::$apiLocales = $metadataJson->litcal_metadata->locales;
        }
        if (false === array_key_exists(ApiOptions::getLocale(), self::$apiLocalesDisplay)) {
            self::$apiLocalesDisplay[ApiOptions::getLocale()] = array_reduce(self::$apiLocales, function (array $carry, string $item) {
                $carry[$item] = \Locale::getDisplayName($item, ApiOptions::getLocale());
                return $carry;
            }, []);
            asort(self::$apiLocalesDisplay[ApiOptions::getLocale()]);
        }

        $this->name('locale');
        $this->id('locale');
    }

    /**
     * Generates and returns an HTML string for a locale select input.
     *
     * This method creates an HTML string representing a select input element
     * for locales, wrapped in a specified HTML element if applicable.
     * It uses class attributes for styling the label, input, and wrapper.
     * The options for the select input are generated based on available locales,
     * with the default selection set to Latin ('la').
     *
     * @return string The HTML string for the locale select input.
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

        $options = array_map(
            fn (string $k, string $v) => "<option value=\"{$k}\"" . ($k === $this->selectedValue ? ' selected' : '') . ">{$k} ({$v})</option>",
            array_keys(self::$apiLocalesDisplay[ApiOptions::getLocale()]),
            array_values(self::$apiLocalesDisplay[ApiOptions::getLocale()])
        );
        $optionsHtml = implode('', $options);

        $data = $this->getData();
        $for = $this->id !== '' ? " for=\"{$this->id}\"" : '';
        $id = $this->id !== '' ? " id=\"{$this->id}\"" : '';
        $name = $this->name !== '' ? " name=\"{$this->name}\"" : '';

        $html .= $wrapper !== null ? "<{$wrapper}{$wrapperClass}>" : '';
        $html .= "<label{$labelClass}{$for}>locale{$labelAfter}</label>";
        $html .= "<select{$id}{$name}{$inputClass}{$data}{$disabled}>{$optionsHtml}</select>";
        $html .= $wrapper !== null ? "</{$wrapper}>" : '';
        return $html;
    }
}
