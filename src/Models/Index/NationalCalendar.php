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
     * The wider regions this calendar declares, ordered most general first
     * (e.g. `['Europe', 'Nordic']`); empty when it declares none.
     *
     * @var list<string>
     */
    public readonly array $widerRegions;

    /**
     * The calendar's only wider region, set only when it declares exactly one.
     *
     * @deprecated Read {@see self::$widerRegions}; a calendar may declare more than one wider region.
     */
    public readonly ?string $widerRegion;

    /**
     * Pass `$widerRegions`; `$widerRegion` is accepted for callers written before a
     * calendar could declare more than one region, and is read as a one-element list
     * when `$widerRegions` is empty. When both are given they must agree.
     *
     * @param string $calendarId The calendar ID (ISO 3166-1 alpha-2 country code)
     * @param string[] $locales The locales supported by this calendar
     * @param string[] $missals The missals available for this calendar
     * @param NationalCalendarSettings $settings The settings for this calendar
     * @param string|null $widerRegion Deprecated: the calendar's single wider region (optional)
     * @param string[]|null $dioceses The dioceses within this calendar (optional)
     * @param list<string> $widerRegions The wider regions this calendar declares, most general first
     * @throws \InvalidArgumentException When `$widerRegion` contradicts `$widerRegions`
     */
    public function __construct(
        public readonly string $calendarId,
        public readonly array $locales,
        public readonly array $missals,
        public readonly NationalCalendarSettings $settings,
        ?string $widerRegion = null,
        public readonly ?array $dioceses = null,
        array $widerRegions = []
    ) {
        if ($widerRegions === [] && $widerRegion !== null) {
            $widerRegions = [$widerRegion];
        } elseif ($widerRegion !== null && $widerRegions !== [$widerRegion]) {
            throw new \InvalidArgumentException(
                "Deprecated wider_region '{$widerRegion}' contradicts wider_regions [" . implode(', ', $widerRegions) . ']'
            );
        }
        $this->widerRegions = $widerRegions;
        $this->widerRegion  = count($widerRegions) === 1 ? $widerRegions[0] : null;
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
     * Helper method to safely cast mixed values to a list of strings
     *
     * @param array<string,mixed> $data The source array
     * @param string $key The key to retrieve
     * @return list<string>
     */
    private static function getStringList(array $data, string $key): array
    {
        $list = [];
        foreach (self::getArray($data, $key) as $item) {
            if (!is_string($item)) {
                throw new \InvalidArgumentException("Expected string items in '{$key}', got " . gettype($item));
            }
            $list[] = $item;
        }
        return $list;
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

        $widerRegion  = self::getNullableString($data, 'wider_region');
        $widerRegions = self::getStringList($data, 'wider_regions');

        // The legacy fallback is for an API that sends no `wider_regions` key. An explicit
        // empty list declares no region, which the constructor cannot tell from an absent one.
        if ($widerRegion !== null && $widerRegions === [] && array_key_exists('wider_regions', $data)) {
            throw new \InvalidArgumentException(
                "Deprecated wider_region '{$widerRegion}' contradicts an empty wider_regions"
            );
        }

        return new self(
            calendarId: self::getString($data, 'calendar_id'),
            locales: $locales,
            missals: $missals,
            settings: NationalCalendarSettings::fromArray($settings),
            widerRegion: $widerRegion,
            dioceses: $dioceses,
            widerRegions: $widerRegions
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
     *     wider_regions: list<string>,
     *     wider_region?: string,
     *     dioceses?: string[]
     * }
     */
    public function toArray(): array
    {
        $result = [
            'calendar_id'   => $this->calendarId,
            'locales'       => $this->locales,
            'missals'       => $this->missals,
            'settings'      => $this->settings->toArray(),
            'wider_regions' => $this->widerRegions
        ];

        // Mirrors the API, which still sends the deprecated key when exactly one region is declared.
        if (count($this->widerRegions) === 1) {
            $result['wider_region'] = $this->widerRegions[0];
        }

        if ($this->dioceses !== null) {
            $result['dioceses'] = $this->dioceses;
        }

        return $result;
    }
}
