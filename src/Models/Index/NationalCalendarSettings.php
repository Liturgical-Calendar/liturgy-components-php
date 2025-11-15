<?php

namespace LiturgicalCalendar\Components\Models\Index;

use LiturgicalCalendar\Components\Models\HolyDaysOfObligation;

/**
 * Model representing the settings for a national calendar
 *
 * @package LiturgicalCalendar\Components\Models
 */
class NationalCalendarSettings
{
    /**
     * @param string $epiphany When Epiphany is celebrated (e.g., "JAN6" or "SUNDAY_JAN2_JAN8")
     * @param string $ascension When Ascension is celebrated (e.g., "THURSDAY" or "SUNDAY")
     * @param string $corpusChristi When Corpus Christi is celebrated (e.g., "THURSDAY" or "SUNDAY")
     * @param bool $eternalHighPriest Whether the Eternal High Priest feast is celebrated
     * @param HolyDaysOfObligation $holydaysOfObligation The holy days of obligation for this calendar
     */
    public function __construct(
        public readonly string $epiphany,
        public readonly string $ascension,
        public readonly string $corpusChristi,
        public readonly bool $eternalHighPriest,
        public readonly HolyDaysOfObligation $holydaysOfObligation
    ) {
    }

    /**
     * Create an instance from an associative array
     *
     * @param array<string,mixed> $data The national calendar settings data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            epiphany: $data['epiphany'],
            ascension: $data['ascension'],
            corpusChristi: $data['corpus_christi'],
            eternalHighPriest: $data['eternal_high_priest'],
            holydaysOfObligation: HolyDaysOfObligation::fromArray($data['holydays_of_obligation'])
        );
    }

    /**
     * Convert the model to an associative array
     *
     * @return array{
     *     epiphany: string,
     *     ascension: string,
     *     corpus_christi: string,
     *     eternal_high_priest: bool,
     *     holydays_of_obligation: array<string,bool>
     * }
     */
    public function toArray(): array
    {
        return [
            'epiphany'               => $this->epiphany,
            'ascension'              => $this->ascension,
            'corpus_christi'         => $this->corpusChristi,
            'eternal_high_priest'    => $this->eternalHighPriest,
            'holydays_of_obligation' => $this->holydaysOfObligation->toArray()
        ];
    }
}
