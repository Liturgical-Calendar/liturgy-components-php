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
     * Create an instance from an associative array
     *
     * @param array<string,mixed> $data The national calendar data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            calendarId: $data['calendar_id'],
            locales: $data['locales'],
            missals: $data['missals'],
            settings: NationalCalendarSettings::fromArray($data['settings']),
            widerRegion: $data['wider_region'] ?? null,
            dioceses: $data['dioceses'] ?? null
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
