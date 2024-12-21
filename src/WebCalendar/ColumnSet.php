<?php

namespace LiturgicalCalendar\Components\WebCalendar;

/**
 * A class to manage a set of Column flags.
 *
 * The class provides methods to add, remove, toggle, and check for the presence of Column flags.
 * The class also provides a method to retrieve the value of the columnFlags property.
 *
 * @package LiturgicalCalendar\Components\WebCalendar
 * @author John Roman Dorazio <priest@johnromanodorazio.com>
 */
class ColumnSet
{
    /**
     * A bitfield of Column flags.
     *
     * The value of this property is a bitfield of the flags defined in the Column class.
     * The flags indicate which columns are to be included in the table, or set to hidden, or set to a certain color.
     * The property is initialized with the value passed to the constructor.
     *
     * @var int
     */
    private int $columnFlags;

    /**
     * Constructor for ColumnSet class.
     *
     * Initializes the columnFlags property with the value provided to the constructor.
     *
     * @param Column|int $columnFlag The bitfield of Column flags to initialize the columnFlags property with.
     */
    public function __construct(Column|int $columnFlag = Column::NONE)
    {
        $this->set($columnFlag);
    }

    /**
     * Adds a column flag to the columnFlags property.
     *
     * If the given column flag is invalid, an InvalidArgumentException is thrown.
     *
     * @param Column $columnFlag The column flag to add.
     */
    public function add(Column $columnFlag)
    {
        $this->columnFlags |= $columnFlag->value;
    }

    /**
     * Removes a column flag from the columnFlags property.
     *
     * If the given column flag is invalid, an InvalidArgumentException is thrown.
     *
     * @param Column $columnFlag The column flag to remove.
     */
    public function remove(Column $columnFlag)
    {
        $this->columnFlags &= ~$columnFlag->value;
    }

    /**
     * Toggles a column flag.
     *
     * If the given column flag is invalid, an InvalidArgumentException is thrown.
     *
     * @param Column $columnFlag The column flag to toggle.
     */
    public function toggle(Column $columnFlag)
    {
        $this->columnFlags ^= $columnFlag->value;
    }

    /**
     * Resets the columnFlags property to Column::NONE, effectively removing all column flags.
     */
    public function clear()
    {
        $this->columnFlags = Column::NONE->value;
    }

    /**
     * Sets all column flags by setting columnFlags to Column::ALL.
     */
    public function setAll()
    {
        $this->columnFlags = Column::ALL->value;
    }

    /**
     * Sets the columnFlags property to the given value.
     *
     * If the given column flag is invalid, an InvalidArgumentException is thrown.
     *
     * @param Column|int $columnFlag The column flag to set.
     */
    public function set(Column|int $columnFlag)
    {
        if (is_int($columnFlag)) {
            if (!Column::isValid($columnFlag)) {
                throw new \InvalidArgumentException('Invalid column flag');
            }
            $this->columnFlags = $columnFlag;
            return;
        }
        $this->columnFlags = $columnFlag->value;
    }

    /**
     * Checks if a given column flag is set in the columnFlags property.
     *
     * @param Column $columnFlag The column flag to check.
     *
     * @return bool True if the given column flag is set, false otherwise.
     */
    public function has(Column $columnFlag): bool
    {
        return ($columnFlag->value & $this->columnFlags) === $columnFlag->value;
    }

    /**
     * Returns the value of the columnFlags property.
     *
     * @return int The value of the columnFlags property.
     */
    public function get(): int
    {
        return $this->columnFlags;
    }
}
