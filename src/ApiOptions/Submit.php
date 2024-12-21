<?php

namespace LiturgicalCalendar\Components\ApiOptions;

use LiturgicalCalendar\Components\ApiOptions;

/**
 * Class to generate a submit button element.
 *
 * This class provides the necessary methods to generate a submit button element.
 * The generated HTML includes the element's class, id, and value attributes.
 *
 * @package LiturgicalCalendar\Components\ApiOptions
 * @author John Roman Dorazio <priest@johnromanodorazio.com>
 */
class Submit
{
    private string $as      = 'input';
    private string $class   = '';
    private string $id      = '';
    private string $value   = 'Submit';

    /**
     * Initializes the Submit object with default settings.
     *
     * The Submit object is initialized with the following default settings:
     * - $as: 'input'
     * - $class: ''
     * - $id: ''
     * - $value: 'Submit'
     */
    public function __construct()
    {
    }

    /**
     * Sets the type of HTML element to use for the submit element.
     *
     * @param string $element The type of HTML element to use.  Valid values are
     *                        'button' or 'input'.
     *
     * @return $this The current instance for method chaining.
     *
     * @throws \Exception If the element is not a valid value.
     */
    public function as(string $element)
    {
        if (false === in_array($element, ['button', 'input'])) {
            throw new \Exception("Invalid label element: {$element}");
        }
        $this->as = $element;
        return $this;
    }

    /**
     * Sets the class attribute for the element.
     *
     * @param string $class The value of the class attribute.
     *
     * @return $this The current instance for method chaining.
     */
    public function class(string $class)
    {
        $class = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
        $this->class = " class=\"{$class}\"";
        return $this;
    }

    /**
     * Sets the ID attribute for the element.
     *
     * @param string $id The ID attribute value to set.
     *
     * @return $this The current instance for method chaining.
     */
    public function id(string $id)
    {
        $id = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        $this->id = " id=\"{$id}\"";
        return $this;
    }

    /**
     * Sets the value of the submit element.
     *
     * @param string $value The value for the submit element.
     *
     * @return $this The current instance.
     */
    public function value(string $value)
    {
        $this->value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        return $this;
    }

    /**
     * Generates and returns an HTML string for a submit element.
     *
     * This method creates an HTML string representing a submit element,
     * which can be either an `<input>` or `<button>`, based on the value of `$this->as`.
     * The generated HTML includes the element's class, id, and value attributes.
     *
     * @param ApiOptions|null $callingClass The calling class instance, which must be an instance of ApiOptions.
     *
     * @return string The HTML string for the submit element.
     *
     * @throws \Exception If the calling class is not an instance of ApiOptions.
     */
    public function get(?ApiOptions $callingClass): string
    {
        if (false === is_a($callingClass, ApiOptions::class, false)) {
            throw new \Exception("This method can only be called by an ApiOptions class instance");
        }
        if ($this->as === 'input') {
            return "<{$this->as}{$this->class}{$this->id} value=\"{$this->value}\">";
        }
        return "<{$this->as}{$this->class}{$this->id}>{$this->value}</{$this->as}>";
    }
}
