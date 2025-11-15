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
     * Create an instance from an associative array
     *
     * @param array<string,mixed> $data The diocesan calendar data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            calendarId: $data['calendar_id'],
            diocese: $data['diocese'],
            nation: $data['nation'],
            locales: $data['locales'],
            timezone: $data['timezone'],
            group: $data['group'] ?? null,
            settings: $data['settings'] ?? null
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
