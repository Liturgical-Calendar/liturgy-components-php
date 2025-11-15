<?php

namespace LiturgicalCalendar\Components\Models;

/**
 * Model representing liturgical calendar settings from the API
 *
 * @package LiturgicalCalendar\Components\Models
 */
class LiturgicalCalendarSettings
{
    /**
     * @param int $year The liturgical year
     * @param string $epiphany When Epiphany is celebrated
     * @param string $ascension When Ascension is celebrated
     * @param string $corpusChristi When Corpus Christi is celebrated
     * @param bool $eternalHighPriest Whether the Eternal High Priest feast is celebrated
     * @param string $locale The locale for the calendar
     * @param string $yearType The type of year (LITURGICAL or CIVIL)
     * @param string $returnType The return type of the API response
     * @param HolyDaysOfObligation $holydaysOfObligation The holy days of obligation
     * @param string|null $nationalCalendar The national calendar ID (ISO 3166-1 alpha-2 country code) when dealing with a national or diocesan calendar
     * @param string|null $diocesanCalendar The diocesan calendar ID when dealing with a diocesan calendar
     */
    public function __construct(
        public readonly int $year,
        public readonly string $epiphany,
        public readonly string $ascension,
        public readonly string $corpusChristi,
        public readonly bool $eternalHighPriest,
        public readonly string $locale,
        public readonly string $yearType,
        public readonly string $returnType,
        public readonly HolyDaysOfObligation $holydaysOfObligation,
        public readonly ?string $nationalCalendar = null,
        public readonly ?string $diocesanCalendar = null
    ) {
    }

    /**
     * Helper to safely get int value from object property
     *
     * @param object $data
     * @param string $key
     * @param int $default
     * @return int
     */
    private static function getIntProperty(object $data, string $key, int $default = 0): int
    {
        if (!property_exists($data, $key)) {
            return $default;
        }
        $value = $data->$key;
        if (is_int($value)) {
            return $value;
        }
        $filtered = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        return $filtered !== null ? (int) $filtered : $default;
    }

    /**
     * Helper to safely get string value from object property
     *
     * @param object $data
     * @param string $key
     * @param string $default
     * @return string
     */
    private static function getStringProperty(object $data, string $key, string $default = ''): string
    {
        if (!property_exists($data, $key)) {
            return $default;
        }
        $value = $data->$key;
        if (is_string($value)) {
            return $value;
        }
        if (is_scalar($value) || ( is_object($value) && method_exists($value, '__toString') )) {
            return (string) $value;
        }
        return $default;
    }

    /**
     * Helper to safely get nullable string value from object property
     *
     * @param object $data
     * @param string $key
     * @return string|null
     */
    private static function getNullableStringProperty(object $data, string $key): ?string
    {
        if (!property_exists($data, $key)) {
            return null;
        }
        $value = $data->$key;
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            return $value;
        }
        if (is_scalar($value) || ( is_object($value) && method_exists($value, '__toString') )) {
            return (string) $value;
        }
        return null;
    }

    /**
     * Helper to safely get bool value from object property
     *
     * @param object $data
     * @param string $key
     * @param bool $default
     * @return bool
     */
    private static function getBoolProperty(object $data, string $key, bool $default = false): bool
    {
        if (!property_exists($data, $key)) {
            return $default;
        }
        $value = $data->$key;
        if (is_bool($value)) {
            return $value;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Create an instance from an associative array or object
     *
     * @param array<string,mixed>|object $data The settings data
     * @return self
     */
    public static function fromArrayOrObject(array|object $data): self
    {
        if (is_array($data)) {
            $data = (object) $data;
        }

        /** @var array<string,bool> $holydaysData */
        $holydaysData = property_exists($data, 'holydays_of_obligation')
            ? ( is_array($data->holydays_of_obligation) ? $data->holydays_of_obligation : (array) $data->holydays_of_obligation )
            : [];

        return new self(
            year: self::getIntProperty($data, 'year', 0),
            epiphany: self::getStringProperty($data, 'epiphany', ''),
            ascension: self::getStringProperty($data, 'ascension', ''),
            corpusChristi: self::getStringProperty($data, 'corpus_christi', ''),
            eternalHighPriest: self::getBoolProperty($data, 'eternal_high_priest', false),
            locale: self::getStringProperty($data, 'locale', ''),
            yearType: self::getStringProperty($data, 'year_type', ''),
            returnType: self::getStringProperty($data, 'return_type', ''),
            holydaysOfObligation: HolyDaysOfObligation::fromArray($holydaysData),
            nationalCalendar: self::getNullableStringProperty($data, 'national_calendar'),
            diocesanCalendar: self::getNullableStringProperty($data, 'diocesan_calendar')
        );
    }

    /**
     * Convert the model to an associative array
     *
     * @return array{
     *     year: int,
     *     epiphany: string,
     *     ascension: string,
     *     corpus_christi: string,
     *     eternal_high_priest: bool,
     *     locale: string,
     *     year_type: string,
     *     return_type: string,
     *     holydays_of_obligation: array<string,bool>
     * }
     */
    public function toArray(): array
    {
        $result = [
            'year'                   => $this->year,
            'epiphany'               => $this->epiphany,
            'ascension'              => $this->ascension,
            'corpus_christi'         => $this->corpusChristi,
            'eternal_high_priest'    => $this->eternalHighPriest,
            'locale'                 => $this->locale,
            'year_type'              => $this->yearType,
            'return_type'            => $this->returnType,
            'holydays_of_obligation' => $this->holydaysOfObligation->toArray()
        ];

        if ($this->nationalCalendar !== null) {
            $result['national_calendar'] = $this->nationalCalendar;
        }

        if ($this->diocesanCalendar !== null) {
            $result['diocesan_calendar'] = $this->diocesanCalendar;
        }

        return $result;
    }
}
