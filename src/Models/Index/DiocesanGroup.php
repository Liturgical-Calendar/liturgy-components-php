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
     * Create an instance from an associative array
     *
     * @param array<string,mixed> $data The diocesan group data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            groupName: $data['group_name'],
            dioceses: $data['dioceses']
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
