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
     * Helper method to safely cast mixed values to string
     *
     * @param array<string,mixed> $data The source array
     * @param string $key The key to retrieve
     * @param string $default The default value if key doesn't exist
     * @return string
     */
    private static function getString(array $data, string $key, string $default = ''): string
    {
        $value = $data[$key] ?? $default;
        if (!is_string($value)) {
            throw new \InvalidArgumentException("Expected string for key '{$key}', got " . gettype($value));
        }
        return $value;
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
     * @param array<string,mixed> $data The national calendar settings data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $holydaysData = $data['holydays_of_obligation'] ?? null;
        if (!is_array($holydaysData)) {
            throw new \InvalidArgumentException("Expected array for 'holydays_of_obligation', got " . gettype($holydaysData));
        }
        /** @var array<string,mixed> $holydaysData */

        return new self(
            epiphany: self::getString($data, 'epiphany'),
            ascension: self::getString($data, 'ascension'),
            corpusChristi: self::getString($data, 'corpus_christi'),
            eternalHighPriest: self::getBool($data, 'eternal_high_priest'),
            holydaysOfObligation: HolyDaysOfObligation::fromArray($holydaysData)
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
