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

    private ?string $labelAfter = null;

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
        if (false === array_key_exists(ApiOptions::$locale, self::$apiLocalesDisplay)) {
            self::$apiLocalesDisplay[ApiOptions::$locale] = array_reduce(self::$apiLocales, function (array $carry, string $item) {
                $carry[$item] = \Locale::getDisplayName($item, ApiOptions::$locale);
                return $carry;
            }, []);
            asort(self::$apiLocalesDisplay[ApiOptions::$locale]);
        }
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

        $data = $this->getData();
        $localesOptions = array_map(fn(string $k, string $v) => "<option value=\"{$k}\"" . ($k === 'la' ? ' selected' : '') . ">{$k} ({$v})</option>", array_keys(self::$apiLocalesDisplay[ApiOptions::$locale]), array_values(self::$apiLocalesDisplay[ApiOptions::$locale]));
        $localesHtml = implode("\n", $localesOptions);
        $html .= $wrapper !== null ? "<{$wrapper}{$wrapperClass}>" : '';
        $html .= "<label{$labelClass}>locale{$labelAfter}</label>";
        $html .= "<select{$this->id}{$inputClass}{$data}>{$localesHtml}</select>";
        $html .= $wrapper !== null ? "</{$wrapper}>" : '';
        return $html;
    }
}
