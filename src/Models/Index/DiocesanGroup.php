<?php

namespace LiturgicalCalendar\Components\Models\Index;

/**
 * Model representing a group of dioceses
 *
 * @package LiturgicalCalendar\Components\Models
 */
class DiocesanGroup
{
    /**
     * @param string $groupName The name of the diocesan group
     * @param string[] $dioceses The list of diocese calendar IDs in this group
     */
    public function __construct(
        public readonly string $groupName,
        public readonly array $dioceses
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
     * @param array<string,mixed> $data The diocesan group data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $dioceses = self::getArray($data, 'dioceses');
        /** @var array<string> $dioceses */

        return new self(
            groupName: self::getString($data, 'group_name'),
            dioceses: $dioceses
        );
    }

    /**
     * Convert the model to an associative array
     *
     * @return array{
     *     group_name: string,
     *     dioceses: string[]
     * }
     */
    public function toArray(): array
    {
        return [
            'group_name' => $this->groupName,
            'dioceses'   => $this->dioceses
        ];
    }
}
