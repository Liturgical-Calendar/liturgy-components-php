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
            year: property_exists($data, 'year') ? (int) $data->year : 0,
            epiphany: property_exists($data, 'epiphany') ? (string) $data->epiphany : '',
            ascension: property_exists($data, 'ascension') ? (string) $data->ascension : '',
            corpusChristi: property_exists($data, 'corpus_christi') ? (string) $data->corpus_christi : '',
            eternalHighPriest: property_exists($data, 'eternal_high_priest') ? (bool) $data->eternal_high_priest : false,
            locale: property_exists($data, 'locale') ? (string) $data->locale : '',
            yearType: property_exists($data, 'year_type') ? (string) $data->year_type : '',
            returnType: property_exists($data, 'return_type') ? (string) $data->return_type : '',
            holydaysOfObligation: HolyDaysOfObligation::fromArray($holydaysData),
            nationalCalendar: property_exists($data, 'national_calendar') ? $data->national_calendar : null,
            diocesanCalendar: property_exists($data, 'diocesan_calendar') ? $data->diocesan_calendar : null
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
