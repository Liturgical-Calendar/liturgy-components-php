<?php

namespace LiturgicalCalendar\Components\Models;

/**
 * Model representing the Holy Days of Obligation settings
 *
 * @package LiturgicalCalendar\Components\Models
 */
class HolyDaysOfObligation
{
    public const STANDARD_KEYS = [
        'Christmas',
        'Epiphany',
        'Ascension',
        'CorpusChristi',
        'MaryMotherOfGod',
        'ImmaculateConception',
        'Assumption',
        'StJoseph',
        'StsPeterPaulAp',
        'AllSaints'
    ];


    /**
     * @param bool $Christmas
     * @param bool $Epiphany
     * @param bool $Ascension
     * @param bool $CorpusChristi
     * @param bool $MaryMotherOfGod
     * @param bool $ImmaculateConception
     * @param bool $Assumption
     * @param bool $StJoseph
     * @param bool $StsPeterPaulAp
     * @param bool $AllSaints
     * @param array<string,bool> $additionalHolyDays Additional holy days beyond the standard ones
     */
    public function __construct(
        public readonly bool $Christmas,
        public readonly bool $Epiphany,
        public readonly bool $Ascension,
        public readonly bool $CorpusChristi,
        public readonly bool $MaryMotherOfGod,
        public readonly bool $ImmaculateConception,
        public readonly bool $Assumption,
        public readonly bool $StJoseph,
        public readonly bool $StsPeterPaulAp,
        public readonly bool $AllSaints,
        public readonly array $additionalHolyDays = []
    ) {
    }

    /**
     * Create an instance from an associative array
     *
     * @param array<string,mixed> $data The holy days of obligation data
     * @return self
     */
    public static function fromArray(array $data): self
    {

        $additionalHolyDays = array_diff_key($data, array_flip(self::STANDARD_KEYS));

        return new self(
            Christmas: $data['Christmas'] ?? false,
            Epiphany: $data['Epiphany'] ?? false,
            Ascension: $data['Ascension'] ?? false,
            CorpusChristi: $data['CorpusChristi'] ?? false,
            MaryMotherOfGod: $data['MaryMotherOfGod'] ?? false,
            ImmaculateConception: $data['ImmaculateConception'] ?? false,
            Assumption: $data['Assumption'] ?? false,
            StJoseph: $data['StJoseph'] ?? false,
            StsPeterPaulAp: $data['StsPeterPaulAp'] ?? false,
            AllSaints: $data['AllSaints'] ?? false,
            additionalHolyDays: $additionalHolyDays
        );
    }

    /**
     * Convert the model to an associative array
     *
     * @return array<string,bool>
     */
    public function toArray(): array
    {
        return array_merge([
            'Christmas'            => $this->Christmas,
            'Epiphany'             => $this->Epiphany,
            'Ascension'            => $this->Ascension,
            'CorpusChristi'        => $this->CorpusChristi,
            'MaryMotherOfGod'      => $this->MaryMotherOfGod,
            'ImmaculateConception' => $this->ImmaculateConception,
            'Assumption'           => $this->Assumption,
            'StJoseph'             => $this->StJoseph,
            'StsPeterPaulAp'       => $this->StsPeterPaulAp,
            'AllSaints'            => $this->AllSaints
        ], $this->additionalHolyDays);
    }
}
