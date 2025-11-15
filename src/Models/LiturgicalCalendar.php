<?php

namespace LiturgicalCalendar\Components\Models;

use LiturgicalCalendar\Components\Models\LiturgicalEvent;

/**
 * Model representing a complete liturgical calendar from the API
 *
 * This class provides magic property access to events by their event_key via the __get() method.
 * For example, $calendar->Easter will return the first event with event_key "Easter".
 *
 * @package LiturgicalCalendar\Components\Models
 *
 * @property-read LiturgicalEvent|null $AshWednesday
 * @property-read LiturgicalEvent|null $HolyThurs
 * @property-read LiturgicalEvent|null $Easter
 * @property-read LiturgicalEvent|null $Pentecost
 * @property-read LiturgicalEvent|null $Advent1
 * @property-read LiturgicalEvent|null $Advent1_vigil
 * @property-read LiturgicalEvent|null $Christmas
 * @property-read LiturgicalEvent|null $BaptismLord
 * @property-read LiturgicalEvent|null $ChristKing
 */
class LiturgicalCalendar
{
    /**
     * @param LiturgicalEvent[] $litcal The array of liturgical events
     * @param LiturgicalCalendarSettings $settings The calendar settings
     * @param \stdClass $metadata The metadata about the calendar
     * @param string[] $messages Any messages from the API
     */
    public function __construct(
        /** @var LiturgicalEvent[] */
        public readonly array $litcal,
        public readonly LiturgicalCalendarSettings $settings,
        public readonly \stdClass $metadata,
        public readonly array $messages
    ) {
    }

    /**
     * Create an instance from an associative array or object
     *
     * @param array<string,mixed>|object $data The calendar data
     * @return self
     */
    public static function fromArrayOrObject(array|object $data): self
    {
        if (is_array($data)) {
            $data = (object) $data;
        }

        // Convert litcal array to object with LiturgicalEvent instances
        $liturgicalEvents = [];
        /** @var array<mixed> $litcalData */
        $litcalData = property_exists($data, 'litcal') ? $data->litcal : [];
        foreach ($litcalData as $event) {
            if (!is_array($event) && !is_object($event)) {
                throw new \InvalidArgumentException('Expected array or object for liturgical event, got ' . gettype($event));
            }
            /** @var array{event_idx: int, event_key: string, name: string, date: string, color: array<string>, color_lcl: array<string>, type: string, grade: int, grade_lcl: string, grade_abbr: string, grade_display: string, common: array<string>, common_lcl: string, liturgical_year: string, liturgical_season: string, liturgical_season_lcl: string, psalter_week: int, is_vigil_mass?: bool, is_vigil_for?: string}|object{event_idx: int, event_key: string, name: string, date: string, color: array<string>, color_lcl: array<string>, type: string, grade: int, grade_lcl: string, grade_abbr: string, grade_display: string, common: array<string>, common_lcl: string, liturgical_year: string, liturgical_season: string, liturgical_season_lcl: string, psalter_week: int, is_vigil_mass?: bool, is_vigil_for?: string} $event */
            $liturgicalEvents[] = new LiturgicalEvent($event);
        }

        /** @var array<string,mixed>|object $settingsData */
        $settingsData = property_exists($data, 'settings') ? $data->settings : [];

        /** @var \stdClass|array<string,mixed> $metadataData */
        $metadataData = property_exists($data, 'metadata') ? $data->metadata : [];
        $metadata     = is_object($metadataData) && $metadataData instanceof \stdClass
            ? $metadataData
            : (object) $metadataData;

        /** @var string[] $messagesData */
        $messagesData = property_exists($data, 'messages') ? $data->messages : [];
        $messages     = is_array($messagesData) ? $messagesData : (array) $messagesData;

        return new self(
            litcal: $liturgicalEvents,
            settings: LiturgicalCalendarSettings::fromArrayOrObject($settingsData),
            metadata: $metadata,
            messages: $messages
        );
    }

    /**
     * Find the first event with the given event_key
     *
     * @param string $eventKey The event_key to search for
     * @return LiturgicalEvent|null The first matching event, or null if not found
     */
    public function getEventByKey(string $eventKey): ?LiturgicalEvent
    {
        foreach ($this->litcal as $event) {
            if ($event instanceof LiturgicalEvent && $event->event_key === $eventKey) {
                return $event;
            }
        }
        return null;
    }

    /**
     * Find all events with the given event_key
     *
     * @param string $eventKey The event_key to search for
     * @return LiturgicalEvent[] Array of all matching events
     */
    public function getEventsByKey(string $eventKey): array
    {
        $matches = [];
        foreach ($this->litcal as $event) {
            if ($event instanceof LiturgicalEvent && $event->event_key === $eventKey) {
                $matches[] = $event;
            }
        }
        return $matches;
    }

    /**
     * Magic method to access events by event_key as properties
     * Returns the first event with the given event_key
     *
     * @param string $name The event_key to search for
     * @return LiturgicalEvent|null
     */
    public function __get(string $name): ?LiturgicalEvent
    {
        return $this->getEventByKey($name);
    }

    /**
     * Convert the model to an associative array
     *
     * @return array{
     *     litcal: array<int|string,mixed>,
     *     settings: array<string,mixed>,
     *     metadata: \stdClass,
     *     messages: string[]
     * }
     */
    public function toArray(): array
    {
        $litcalArray = [];
        foreach ($this->litcal as $key => $event) {
            if ($event instanceof LiturgicalEvent) {
                $litcalArray[$key] = (array) $event;
            } else {
                $litcalArray[$key] = $event;
            }
        }

        return [
            'litcal'   => $litcalArray,
            'settings' => $this->settings->toArray(),
            'metadata' => $this->metadata,
            'messages' => $this->messages
        ];
    }
}
