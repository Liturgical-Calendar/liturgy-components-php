<?php

namespace LiturgicalCalendar\Components\Models\Index;

/**
 * Model representing the complete calendar metadata index
 *
 * This corresponds to the LitCalMetadata phpstan-type from CalendarSelect
 *
 * @package LiturgicalCalendar\Components\Models
 */
class CalendarIndex
{
    /**
     * @param NationalCalendar[] $nationalCalendars All national calendars
     * @param string[] $nationalCalendarsKeys Calendar IDs for all national calendars
     * @param DiocesanCalendar[] $diocesanCalendars All diocesan calendars
     * @param string[] $diocesanCalendarsKeys Calendar IDs for all diocesan calendars
     * @param DiocesanGroup[] $diocesanGroups Groups of dioceses
     * @param WiderRegion[] $widerRegions Wider regions (e.g., continents)
     * @param string[] $widerRegionsKeys Keys for all wider regions
     * @param string[] $locales All available locales
     */
    public function __construct(
        public readonly array $nationalCalendars,
        public readonly array $nationalCalendarsKeys,
        public readonly array $diocesanCalendars,
        public readonly array $diocesanCalendarsKeys,
        public readonly array $diocesanGroups,
        public readonly array $widerRegions,
        public readonly array $widerRegionsKeys,
        public readonly array $locales
    ) {
    }

    /**
     * Create an instance from an associative array
     *
     * Expected array structure:
     * - national_calendars: array of national calendar data
     * - national_calendars_keys: array of national calendar IDs
     * - diocesan_calendars: array of diocesan calendar data
     * - diocesan_calendars_keys: array of diocesan calendar IDs
     * - diocesan_groups: array of diocesan group data
     * - wider_regions: array of wider region data
     * - wider_regions_keys: array of wider region keys
     * - locales: array of locale strings
     *
     * @param array<string,mixed> $data The metadata array from the API
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $nationalCalendars = array_map(
            fn(array $item) => NationalCalendar::fromArray($item),
            $data['national_calendars']
        );

        $diocesanCalendars = array_map(
            fn(array $item) => DiocesanCalendar::fromArray($item),
            $data['diocesan_calendars']
        );

        $diocesanGroups = array_map(
            fn(array $item) => DiocesanGroup::fromArray($item),
            $data['diocesan_groups']
        );

        $widerRegions = array_map(
            fn(array $item) => WiderRegion::fromArray($item),
            $data['wider_regions']
        );

        return new self(
            nationalCalendars: $nationalCalendars,
            nationalCalendarsKeys: $data['national_calendars_keys'],
            diocesanCalendars: $diocesanCalendars,
            diocesanCalendarsKeys: $data['diocesan_calendars_keys'],
            diocesanGroups: $diocesanGroups,
            widerRegions: $widerRegions,
            widerRegionsKeys: $data['wider_regions_keys'],
            locales: $data['locales']
        );
    }

    /**
     * Convert the model to an associative array
     *
     * @return array{
     *     national_calendars: array<int,array{
     *         calendar_id: string,
     *         locales: string[],
     *         missals: string[],
     *         settings: array{
     *             epiphany: string,
     *             ascension: string,
     *             corpus_christi: string,
     *             eternal_high_priest: bool,
     *             holydays_of_obligation: array<string,bool>
     *         },
     *         wider_region?: string,
     *         dioceses?: string[]
     *     }>,
     *     national_calendars_keys: string[],
     *     diocesan_calendars: array<int,array{
     *         calendar_id: string,
     *         diocese: string,
     *         nation: string,
     *         locales: string[],
     *         timezone: string,
     *         group?: string,
     *         settings?: array{
     *             epiphany?: string,
     *             ascension?: string,
     *             corpus_christi?: string
     *         }
     *     }>,
     *     diocesan_calendars_keys: string[],
     *     diocesan_groups: array<int,array{
     *         group_name: string,
     *         dioceses: string[]
     *     }>,
     *     wider_regions: array<int,array{
     *         name: string,
     *         locales: string[],
     *         api_path: string
     *     }>,
     *     wider_regions_keys: string[],
     *     locales: string[]
     * }
     */
    public function toArray(): array
    {
        return [
            'national_calendars'      => array_map(
                fn(NationalCalendar $calendar) => $calendar->toArray(),
                $this->nationalCalendars
            ),
            'national_calendars_keys' => $this->nationalCalendarsKeys,
            'diocesan_calendars'      => array_map(
                fn(DiocesanCalendar $calendar) => $calendar->toArray(),
                $this->diocesanCalendars
            ),
            'diocesan_calendars_keys' => $this->diocesanCalendarsKeys,
            'diocesan_groups'         => array_map(
                fn(DiocesanGroup $group) => $group->toArray(),
                $this->diocesanGroups
            ),
            'wider_regions'           => array_map(
                fn(WiderRegion $region) => $region->toArray(),
                $this->widerRegions
            ),
            'wider_regions_keys'      => $this->widerRegionsKeys,
            'locales'                 => $this->locales
        ];
    }
}
