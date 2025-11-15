<?php

namespace LiturgicalCalendar\Components\Models\Index;

/**
 * Model representing a wider region (e.g., continents or large geographical areas)
 *
 * @package LiturgicalCalendar\Components\Models
 */
class WiderRegion
{
    /**
     * @param string $name The name of the wider region
     * @param string[] $locales The locales supported by this region
     * @param string $apiPath The API path for this region's calendar
     */
    public function __construct(
        public readonly string $name,
        public readonly array $locales,
        public readonly string $apiPath
    ) {
    }

    /**
     * Create an instance from an associative array
     *
     * @param array<string,mixed> $data The wider region data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            locales: $data['locales'],
            apiPath: $data['api_path']
        );
    }

    /**
     * Convert the model to an associative array
     *
     * @return array{
     *     name: string,
     *     locales: string[],
     *     api_path: string
     * }
     */
    public function toArray(): array
    {
        return [
            'name'     => $this->name,
            'locales'  => $this->locales,
            'api_path' => $this->apiPath
        ];
    }
}
