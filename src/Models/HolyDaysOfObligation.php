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
     * Helper method to safely cast mixed values to bool
     *
     * @param array<string,mixed> $data The source array
     * @param string $key The key to retrieve
     * @param bool $default The default value if key doesn't exist
     * @return bool
     */
    private static function getBool(array $data, string $key, bool $default = false): bool
    {
        $value = $data[$key] ?? $default;
        if (is_bool($value)) {
            return $value;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
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
        /** @var array<string,bool> $additionalHolyDays */

        return new self(
            Christmas: self::getBool($data, 'Christmas'),
            Epiphany: self::getBool($data, 'Epiphany'),
            Ascension: self::getBool($data, 'Ascension'),
            CorpusChristi: self::getBool($data, 'CorpusChristi'),
            MaryMotherOfGod: self::getBool($data, 'MaryMotherOfGod'),
            ImmaculateConception: self::getBool($data, 'ImmaculateConception'),
            Assumption: self::getBool($data, 'Assumption'),
            StJoseph: self::getBool($data, 'StJoseph'),
            StsPeterPaulAp: self::getBool($data, 'StsPeterPaulAp'),
            AllSaints: self::getBool($data, 'AllSaints'),
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
