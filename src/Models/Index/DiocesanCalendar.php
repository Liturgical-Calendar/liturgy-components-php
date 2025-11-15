<?php

namespace LiturgicalCalendar\Components\Models\Index;

/**
 * Model representing a diocesan calendar
 *
 * @package LiturgicalCalendar\Components\Models
 */
class DiocesanCalendar
{
    /**
     * @param string $calendarId The calendar ID for the diocese
     * @param string $diocese The name of the diocese
     * @param string $nation The nation this diocese belongs to (ISO 3166-1 alpha-2 country code)
     * @param string[] $locales The locales supported by this calendar
     * @param string $timezone The timezone for this diocese
     * @param string|null $group The group this diocese belongs to (optional)
     * @param array{epiphany?: string, ascension?: string, corpus_christi?: string}|null $settings Optional settings that override national defaults
     */
    public function __construct(
        public readonly string $calendarId,
        public readonly string $diocese,
        public readonly string $nation,
        public readonly array $locales,
        public readonly string $timezone,
        public readonly ?string $group = null,
        public readonly ?array $settings = null
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
     * @param array<string,mixed> $data The diocesan calendar data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $locales = self::getArray($data, 'locales');
        /** @var array<string> $locales */

        $settings = self::getNullableArray($data, 'settings');
        /** @var array{epiphany?: string, ascension?: string, corpus_christi?: string}|null $settings */

        return new self(
            calendarId: self::getString($data, 'calendar_id'),
            diocese: self::getString($data, 'diocese'),
            nation: self::getString($data, 'nation'),
            locales: $locales,
            timezone: self::getString($data, 'timezone'),
            group: self::getNullableString($data, 'group'),
            settings: $settings
        );
    }

    /**
     * Convert the model to an associative array
     *
     * @return array{
     *     calendar_id: string,
     *     diocese: string,
     *     nation: string,
     *     locales: string[],
     *     timezone: string,
     *     group?: string,
     *     settings?: array{
     *         epiphany?: string,
     *         ascension?: string,
     *         corpus_christi?: string
     *     }
     * }
     */
    public function toArray(): array
    {
        $result = [
            'calendar_id' => $this->calendarId,
            'diocese'     => $this->diocese,
            'nation'      => $this->nation,
            'locales'     => $this->locales,
            'timezone'    => $this->timezone
        ];

        if ($this->group !== null) {
            $result['group'] = $this->group;
        }

        if ($this->settings !== null) {
            $result['settings'] = $this->settings;
        }

        return $result;
    }
}
