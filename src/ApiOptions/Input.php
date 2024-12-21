<?php

namespace LiturgicalCalendar\Components\ApiOptions;

/**
 * Abstract class for generating select elements in the API options form.
 *
 * This class is extended by other classes that represent specific input elements.
 * It provides methods for setting properties of the input element and its wrapper
 * element, label element, and options.
 *
 * @package LiturgicalCalendar\Components\ApiOptions
 * @author John Roman Dorazio <priest@johnromanodorazio.com>
 */
abstract class Input
{
    protected static ?string $globalWrapper      = null;
    protected static ?string $globalWrapperClass = null;
    protected static ?string $globalLabelClass   = null;
    protected static ?string $globalInputClass   = null;
    protected string $id            = '';
    protected string $name          = '';
    protected ?string $wrapper      = null;
    protected ?string $wrapperClass = null;
    protected ?string $inputClass   = null;
    protected ?string $labelClass   = null;
    protected ?string $labelAfter   = null;
    protected array $data           = [];
    protected bool $disabled        = false;
    protected string $selectedValue = '';

    /**
     * Sets a global wrapper element for all input elements created by this class.
     *
     * Only 'div' is a valid wrapper element.
     *
     * @param string $wrapper The wrapper element, currently only 'div' is supported.
     */
    public static function setGlobalWrapper(string $wrapper)
    {
        if (false === in_array($wrapper, ['div', 'td'])) {
            throw new \Exception("Invalid wrapper: {$wrapper}, valid values are: div, td");
        }
        static::$globalWrapper = $wrapper;
    }

    /**
     * Sets a global class attribute for all wrapper elements created by this class.
     *
     * @param string $class The value of the class attribute.
     */
    public static function setGlobalWrapperClass(string $class)
    {
        $class = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
        static::$globalWrapperClass = $class;
    }

    /**
     * Sets a global class attribute for all label elements created by this class.
     *
     * @param string $class The value of the class attribute.
     * @return void
     */
    public static function setGlobalLabelClass(string $class)
    {
        $class = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
        static::$globalLabelClass = $class;
    }

    /**
     * Sets a global class attribute for all input elements created by this class.
     *
     * @param string $class The value of the class attribute.
     * @return void
     */
    public static function setGlobalInputClass(string $class)
    {
        $class = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
        static::$globalInputClass = $class;
    }

    /**
     * Sets the id attribute of the input element.
     *
     * @param string $id The value of the id attribute.
     * @return $this
     */
    public function id(string $id): Input
    {
        $this->id = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        return $this;
    }

    public function name(string $name): Input
    {
        $this->name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        return $this;
    }

    /**
     * Sets the class or classes to apply to the input element.
     *
     * @param string $class The class or classes to apply to the input element.
     *                      This can be any valid HTML class, such as a single
     *                      string or an array of strings.
     *
     * @return $this The current instance for method chaining.
     */
    public function class(string $class): Input
    {
        $class = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
        $this->inputClass = $class;
        return $this;
    }

    /**
     * Sets the class or classes to apply to the label element.
     *
     * @param string $labelClass The class or classes to apply to the label element.
     *                            This can be any valid HTML class, such as a single
     *                            string or an array of strings.
     *
     * @return $this The current instance for method chaining.
     */
    public function labelClass(string $labelClass): Input
    {
        $labelClass = htmlspecialchars($labelClass, ENT_QUOTES, 'UTF-8');
        $this->labelClass = $labelClass;
        return $this;
    }

    /**
     * Appends a string to the label after the label text.
     *
     * Note that this method will strip any PHP tags and any script tags from the
     * input string to prevent any potential security issues.
     *
     * @param string $text The string to append to the label after the label text.
     *
     * @return $this The instance of the class, for chaining.
     */
    public function labelAfter(string $text): Input
    {
        $text = preg_replace('/<\?php.*?\?>/s', '', $text);
        $text = preg_replace('/<script.*?>.*?<\/script>/s', '', $text);
        $this->labelAfter = $text;
        return $this;
    }

    /**
     * Sets the type of HTML element to use as the wrapper for the input element.
     *
     * @param string $wrapper The type of HTML element to use as the wrapper.
     *                         Only valid values are `div`.
     *                         The default value is `null`, which means no
     *                         wrapper element will be used.
     *
     * @return $this
     */
    public function wrapper(string $wrapper): Input
    {
        $wrapper = htmlspecialchars($wrapper, ENT_QUOTES, 'UTF-8');
        if (false === in_array($wrapper, ['div', 'td'])) {
            throw new \Exception("Invalid wrapper: {$wrapper}, valid values are: div, td");
        }
        $this->wrapper = $wrapper;
        return $this;
    }

    /**
     * Sets the class for the wrapper element.
     *
     * @param string $wrapperClass The class name for the wrapper element.
     *
     * @return $this
     */
    public function wrapperClass(string $wrapperClass): Input
    {
        $wrapperClass = htmlspecialchars($wrapperClass, ENT_QUOTES, 'UTF-8');
        $this->wrapperClass = $wrapperClass;
        return $this;
    }

    /**
     * Sets the data attributes for the input element.
     *
     * This method accepts an associative array where the keys are the data attribute names
     * (without the 'data-' prefix) and the values are the corresponding attribute values.
     * These data attributes will be used to form the 'data-*' attributes in the HTML input element.
     *
     * @param array $data An associative array representing data attributes for the input element.
     *
     * @return Input Returns the current instance to allow method chaining.
     */
    public function data(array $data): Input
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Marks the input element as disabled.
     *
     * This method sets the `disabled` property of the input element to true,
     * resulting in the rendered HTML input element having the `disabled` attribute.
     * A disabled input element is not interactive and cannot be submitted.
     *
     * @return Input Returns the current instance to allow method chaining.
     */
    public function disabled(): Input
    {
        $this->disabled = true;
        return $this;
    }

    public function selectedValue(string $value): Input
    {
        $this->selectedValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        return $this;
    }

    /**
     * Converts the data array into a string of data-* attributes.
     *
     * If the data array is empty, this method will return an empty string.
     *
     * @return string The data-* attributes string.
     */
    protected function getData(): string
    {
        if (empty($this->data)) {
            return '';
        }
        $data = array_map(fn(string $k, string $v) => "data-{$k}=\"{$v}\"", array_keys($this->data), array_values($this->data));
        return ' ' . implode(' ', $data);
    }

    abstract public function get(): string;
}
