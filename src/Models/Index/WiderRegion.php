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
     * Create an instance from an associative array
     *
     * @param array<string,mixed> $data The wider region data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $locales = self::getArray($data, 'locales');
        /** @var array<string> $locales */

        return new self(
            name: self::getString($data, 'name'),
            locales: $locales,
            apiPath: self::getString($data, 'api_path')
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
