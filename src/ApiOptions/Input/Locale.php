<?php

namespace LiturgicalCalendar\Components\ApiOptions\Input;

use LiturgicalCalendar\Components\ApiOptions;
use LiturgicalCalendar\Components\ApiOptions\Input;

/**
 * Class Locale
 *
 * Generates an HTML select element for selecting a locale in the Liturgical
 * Calendar API options form.
 *
 * @see LiturgicalCalendar\Components\ApiOptions
 * @see LiturgicalCalendar\Components\ApiOptions\Input
 */
class Locale extends Input
{
    /** @var array<string,string> */
    public array $data = [
        'param' => 'locale'
    ];

    private static \stdClass $metadata;

    /** @var string[] */
    private static array $apiLocales = [];

    /** @var array<string,string[]> */
    private static array $apiLocalesDisplay = [];

    /**
     * Fetches the list of locales from the Liturgical Calendar API and stores
     * them in the {@see LiturgicalCalendar\Components\ApiOptions\Input\Locale::$apiLocales} static property.
     *
     * If the {@see LiturgicalCalendar\Components\ApiOptions::$locale} property is set, it will also generate a
     * sorted list of locales with their display names in the given locale
     * and store it in the {@see LiturgicalCalendar\Components\ApiOptions\Input\Locale::$apiLocalesDisplay} static property.
     *
     * If the list of locales has already been fetched, it will not fetch it again.
     * Instead, it will return the stored list.
     *
     * @throws \Exception If there is an error fetching or decoding the list of
     *                     locales from the Liturgical Calendar API.
     */
    public function __construct()
    {
        $this->setOptionsForCalendar(null, null);
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

        if (is_string($this->selectedValue) && $this->selectedValue !== '' && !array_key_exists($this->selectedValue, self::$apiLocalesDisplay[ApiOptions::getLocale()])) {
            $baseLocale = \Locale::getPrimaryLanguage($this->selectedValue);
            if ($baseLocale !== null && array_key_exists($baseLocale, self::$apiLocalesDisplay[ApiOptions::getLocale()])) {
                $this->selectedValue = $baseLocale;
            } else {
                $this->selectedValue = array_keys(self::$apiLocalesDisplay[ApiOptions::getLocale()])[0];
            }
        }

        $options     = array_map(
            fn (string $k, string $v): string => "<option value=\"{$k}\"" . ( $k === $this->selectedValue ? ' selected' : '' ) . ">{$v}</option>",
            array_keys(self::$apiLocalesDisplay[ApiOptions::getLocale()]),
            array_values(self::$apiLocalesDisplay[ApiOptions::getLocale()])
        );
        $optionsHtml = implode('', $options);

        $data = $this->getData();
        $for  = $this->id !== '' ? " for=\"{$this->id}\"" : '';
        $id   = $this->id !== '' ? " id=\"{$this->id}\"" : '';
        $name = $this->name !== '' ? " name=\"{$this->name}\"" : '';

        $html .= $wrapper !== null ? "<{$wrapper}{$wrapperClass}>" : '';
        $html .= "<label{$labelClass}{$for}>locale{$labelAfter}</label>";
        $html .= "<select{$id}{$name}{$inputClass}{$data}{$disabled}>{$optionsHtml}</select>";
        $html .= $wrapper !== null ? "</{$wrapper}>" : '';
        return $html;
    }

    public function setOptionsForCalendar(?string $calendarType, ?string $calendarId): void
    {
        $apiUrl = ApiOptions::getApiUrl();
        if (empty(self::$metadata)) {
            $metadataRaw = file_get_contents("{$apiUrl}/calendars");
            if ($metadataRaw === false) {
                throw new \Exception("Failed to fetch locales from {$apiUrl}/calendars");
            }
            $metadataJson = json_decode($metadataRaw);
            if (JSON_ERROR_NONE !== json_last_error()) {
                throw new \Exception("Failed to decode locales from {$apiUrl}/calendars");
            }
            if (false === property_exists($metadataJson, 'litcal_metadata') || false === $metadataJson->litcal_metadata instanceof \stdClass) {
                throw new \Exception("Invalid `litcal_metadata` property from {$apiUrl}/calendars, should exist and should be object: " . var_export($metadataJson->litcal_metadata, true));
            }
            self::$metadata = $metadataJson->litcal_metadata;
        }

        if (null === $calendarType && null === $calendarId) {
            if (
                false === property_exists(self::$metadata, 'locales')
                || false === is_array(self::$metadata->locales)
            ) {
                throw new \Exception("Invalid `litcal_metadata.locales` property from {$apiUrl}/calendars, should exist and should be array: " . var_export(self::$metadata->locales, true));
            }

            self::$apiLocales                                 = self::$metadata->locales;
            self::$apiLocalesDisplay[ApiOptions::getLocale()] = array_reduce(self::$apiLocales, function (array $carry, string $item) {
                $carry[$item] = \Locale::getDisplayName($item, ApiOptions::getLocale());
                return $carry;
            }, []);
            asort(self::$apiLocalesDisplay[ApiOptions::getLocale()]);
        } elseif (null === $calendarId || null === $calendarType) {
            throw new \Exception('Invalid calendarType or calendarId');
        } else {
            switch ($calendarType) {
                case 'nation':
                    if (false === property_exists(self::$metadata, 'national_calendars') || false === is_array(self::$metadata->national_calendars)) {
                        throw new \Exception("Invalid `litcal_metadata.national_calendars` property from {$apiUrl}/calendars, should exist and should be array: " . var_export(self::$metadata->national_calendars, true));
                    }
                    $calendarMetadata = array_values(array_filter(self::$metadata->national_calendars, function ($calendar) use ($calendarId) {
                        return $calendar->calendar_id === $calendarId;
                    }));
                    if (empty($calendarMetadata)) {
                        throw new \Exception("Invalid calendarId: {$calendarId}");
                    }
                    if (false === property_exists($calendarMetadata[0], 'locales') || false === is_array($calendarMetadata[0]->locales)) {
                        throw new \Exception("Invalid `litcal_metadata.national_calendars[calendar_id={$calendarId}].locales` property from {$apiUrl}/calendars, should exist and should be array: " . var_export(self::$metadata->national_calendars[$calendarId]->locales, true));
                    }
                    self::$apiLocales                                 = $calendarMetadata[0]->locales;
                    self::$apiLocalesDisplay[ApiOptions::getLocale()] = array_reduce(self::$apiLocales, function (array $carry, string $item) {
                        $carry[$item] = \Locale::getDisplayName($item, ApiOptions::getLocale());
                        return $carry;
                    }, []);
                    asort(self::$apiLocalesDisplay[ApiOptions::getLocale()]);
                    break;
                case 'diocese':
                    if (false === property_exists(self::$metadata, 'diocesan_calendars') || false === is_array(self::$metadata->diocesan_calendars)) {
                        throw new \Exception("Invalid `litcal_metadata.diocesan_calendars` property from {$apiUrl}/calendars, should exist and should be array: " . var_export(self::$metadata->diocesan_calendars, true));
                    }
                    $calendarMetadata = array_values(array_filter(self::$metadata->diocesan_calendars, function ($calendar) use ($calendarId) {
                        return $calendar->calendar_id === $calendarId;
                    }));
                    if (empty($calendarMetadata)) {
                        throw new \Exception("Invalid calendarId: {$calendarId}");
                    }
                    if (false === property_exists($calendarMetadata[0], 'locales') || false === is_array($calendarMetadata[0]->locales)) {
                        throw new \Exception("Invalid `litcal_metadata.diocesan_calendars[calendar_id={$calendarId}].locales` property from {$apiUrl}/calendars, should exist and should be array: " . var_export(self::$metadata->diocesan_calendars[$calendarId]->locales, true));
                    }
                    self::$apiLocales                                 = $calendarMetadata[0]->locales;
                    self::$apiLocalesDisplay[ApiOptions::getLocale()] = array_reduce(self::$apiLocales, function (array $carry, string $item) {
                        $carry[$item] = \Locale::getDisplayName($item, ApiOptions::getLocale());
                        return $carry;
                    }, []);
                    asort(self::$apiLocalesDisplay[ApiOptions::getLocale()]);
                    break;
                default:
                    throw new \Exception("Invalid calendarType: {$calendarType}");
            }
        }
    }
}
