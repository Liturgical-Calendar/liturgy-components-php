<?php

namespace LiturgicalCalendar\Components\Models\Index;

/**
 * Model representing a rite-level calendar
 *
 * The calendar a rite serves when neither a nation nor a diocese is chosen.
 * The API announces these under `{rite}_calendars`, so `ambrosian_calendars`
 * for the Ambrosian rite. The Roman rite has no such key, because its
 * rite-level calendar is the General Roman Calendar itself.
 *
 * @package LiturgicalCalendar\Components\Models
 */
class RiteCalendar
{
    /**
     * @param string $calendarId The calendar ID, e.g. `ambrosian`
     * @param string $rite The liturgical rite this calendar belongs to
     * @param string[] $locales The locales this calendar is served in
     */
    public function __construct(
        public readonly string $calendarId,
        public readonly string $rite,
        public readonly array $locales
    ) {
    }

    /**
     * Create an instance from an associative array
     *
     * @param array<string,mixed> $data The rite calendar data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $calendarId = $data['calendar_id'] ?? null;
        if (!is_string($calendarId)) {
            throw new \InvalidArgumentException('Expected string for calendar_id in a rite calendar');
        }

        $rite = $data['rite'] ?? null;
        if (!is_string($rite)) {
            throw new \InvalidArgumentException('Expected string for rite in a rite calendar');
        }

        $locales = $data['locales'] ?? [];
        if (!is_array($locales)) {
            throw new \InvalidArgumentException('Expected array for locales in a rite calendar');
        }
        /** @var array<string> $locales */

        return new self(
            calendarId: $calendarId,
            rite: $rite,
            locales: $locales
        );
    }

    /**
     * Convert the model to an associative array
     *
     * @return array{
     *     calendar_id: string,
     *     rite: string,
     *     locales: string[]
     * }
     */
    public function toArray(): array
    {
        return [
            'calendar_id' => $this->calendarId,
            'rite'        => $this->rite,
            'locales'     => $this->locales
        ];
    }
}
