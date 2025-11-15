<?php

namespace LiturgicalCalendar\Components\Models\Index;

/**
 * Model representing a national calendar
 *
 * @package LiturgicalCalendar\Components\Models
 */
class NationalCalendar
{
    /**
     * @param string $calendarId The calendar ID (ISO 3166-1 alpha-2 country code)
     * @param string[] $locales The locales supported by this calendar
     * @param string[] $missals The missals available for this calendar
     * @param NationalCalendarSettings $settings The settings for this calendar
     * @param string|null $widerRegion The wider region this calendar belongs to (optional)
     * @param string[]|null $dioceses The dioceses within this calendar (optional)
     */
    public function __construct(
        public readonly string $calendarId,
        public readonly array $locales,
        public readonly array $missals,
        public readonly NationalCalendarSettings $settings,
        public readonly ?string $widerRegion = null,
        public readonly ?array $dioceses = null
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
     * Helper method to safely cast mixed values to nullable string
     *
     * @param array<string,mixed> $data The source array
     * @param string $key The key to retrieve
     * @return string|null
     */
    private static function getNullableString(array $data, string $key): ?string
    {
        if (!array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }
        $value = $data[$key];
        if (!is_string($value)) {
            throw new \InvalidArgumentException("Expected string for key '{$key}', got " . gettype($value));
        }
        return $value;
    }

    /**
     * Helper method to safely cast mixed values to array
     *
     * @param array<string,mixed> $data The source array
     * @param string $key The key to retrieve
     * @return array<int|string, mixed>
     */
    private static function getArray(array $data, string $key): array
    {
        $value = $data[$key] ?? [];
        if (!is_array($value)) {
            throw new \InvalidArgumentException("Expected array for key '{$key}', got " . gettype($value));
        }
        return $value;
    }

    /**
     * Helper method to safely cast mixed values to nullable array
     *
     * @param array<string,mixed> $data The source array
     * @param string $key The key to retrieve
     * @return array<int|string, mixed>|null
     */
    private static function getNullableArray(array $data, string $key): ?array
    {
        if (!array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }
        $value = $data[$key];
        if (!is_array($value)) {
            throw new \InvalidArgumentException("Expected array for key '{$key}', got " . gettype($value));
        }
        return $value;
    }

    /**
     * Create an instance from an associative array
     *
     * @param array<string,mixed> $data The national calendar data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $settings = $data['settings'] ?? null;
        if (!is_array($settings)) {
            throw new \InvalidArgumentException("Expected array for 'settings', got " . gettype($settings));
        }
        /** @var array<string,mixed> $settings */

        $locales = self::getArray($data, 'locales');
        /** @var array<string> $locales */

        $missals = self::getArray($data, 'missals');
        /** @var array<string> $missals */

        $dioceses = self::getNullableArray($data, 'dioceses');
        /** @var array<string>|null $dioceses */

        return new self(
            calendarId: self::getString($data, 'calendar_id'),
            locales: $locales,
            missals: $missals,
            settings: NationalCalendarSettings::fromArray($settings),
            widerRegion: self::getNullableString($data, 'wider_region'),
            dioceses: $dioceses
        );
    }

    /**
     * Convert the model to an associative array
     *
     * @return array{
     *     calendar_id: string,
     *     locales: string[],
     *     missals: string[],
     *     settings: array{
     *         epiphany: string,
     *         ascension: string,
     *         corpus_christi: string,
     *         eternal_high_priest: bool,
     *         holydays_of_obligation: array<string,bool>
     *     },
     *     wider_region?: string,
     *     dioceses?: string[]
     * }
     */
    public function toArray(): array
    {
        $result = [
            'calendar_id' => $this->calendarId,
            'locales'     => $this->locales,
            'missals'     => $this->missals,
            'settings'    => $this->settings->toArray()
        ];

        if ($this->widerRegion !== null) {
            $result['wider_region'] = $this->widerRegion;
        }

        if ($this->dioceses !== null) {
            $result['dioceses'] = $this->dioceses;
        }

        return $result;
    }
}
