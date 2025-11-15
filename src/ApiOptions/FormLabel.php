<?php

namespace LiturgicalCalendar\Components\ApiOptions;

use LiturgicalCalendar\Components\ApiOptions;

/**
 * Class FormLabel
 *
 * @package LiturgicalCalendar\Components\ApiOptions
 * @author John Roman Dorazio <priest@johnromanodorazio.com>
 */
class FormLabel
{
    private string $as    = 'label';
    private string $text  = '';
    private string $class = '';
    private string $id    = '';
    private string $for   = '';

    /**
     * @param string|array{as?:string,text?:string,class?:string,id?:string}|null $options
     *
     * If $options is a string, it will be used as the value of the `as` property.
     * If $options is an array, the following keys can be used to set the associated properties:
     * - `as`: The HTML element to use to wrap the label text.
     * - `text`: The text to display within the label.
     * - `class`: The class to apply to the label element.
     * - `id`: The id to apply to the label element.
     */
    public function __construct(string|array|null $options = null)
    {
        if (is_string($options)) {
            $this->as($options);
        }

        if (is_array($options)) {
            if (array_key_exists('as', $options)) {
                $this->as($options['as'] ?? 'label');
            }

            if (array_key_exists('text', $options)) {
                $this->text($options['text'] ?? '');
            }

            if (array_key_exists('class', $options)) {
                $this->class($options['class'] ?? '');
            }

            if (array_key_exists('id', $options)) {
                $this->id($options['id'] ?? '');
            }

            if (array_key_exists('for', $options)) {
                $this->for($options['for'] ?? '');
            }
        }
    }

    /**
     * Sets the HTML element to use for the form label.
     *
     * The element must be one of the following: 'label', 'legend', 'div', 'span', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', or 'h6'. If the element is not one of these, an exception is thrown.
     *
     * @param string $element The HTML element to use for the form label.
     *
     * @return FormLabel The FormLabel instance.
     *
     * @throws \Exception If the element is not one of the valid elements.
     */
    public function as(string $element): self
    {
        $element = htmlspecialchars($element, ENT_QUOTES, 'UTF-8');
        if (false === in_array($element, ['label', 'legend', 'div', 'span', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'])) {
            throw new \Exception("Invalid label element: {$element}");
        }
        $this->as = $element;
        return $this;
    }

    /**
     * Sets the class attribute of the form label element.
     *
     * @param string $class The value of the class attribute.
     *
     * @return $this
     */
    public function class(string $class): self
    {
        $class       = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
        $this->class = " class=\"{$class}\"";
        return $this;
    }

    /**
     * Sets the id attribute of the form label element.
     *
     * @param string $id The value of the id attribute of the form label element.
     *
     * @return $this
     */
    public function id(string $id): self
    {
        $id       = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        $this->id = " id=\"{$id}\"";
        return $this;
    }

    /**
     * Sets the text content of the form label element.
     *
     * This method removes any `<?php` code blocks and any `<script>` tags from the provided text string.
     *
     * @param string $text The text content of the form label element.
     *
     * @return $this
     */
    public function text(string $text): self
    {
        $text       = preg_replace('/<\?php.*?\?>/s', '', $text);
        $text       = preg_replace('/<script.*?>.*?<\/script>/s', '', $text);
        $this->text = $text;
        return $this;
    }

    public function for(string $for): self
    {
        $for       = htmlspecialchars($for, ENT_QUOTES, 'UTF-8');
        $this->for = " for=\"{$for}\"";
        return $this;
    }

    /**
     * Generates and returns an HTML string for a form label element.
     *
     * This method creates an HTML string representing a form label element,
     * which can be either a `<label>`, `<legend>`, `<div>`, `<span>`, `<p>`, `<h1>`, `<h2>`, `<h3>`, `<h4>`, `<h5>`, or `<h6>`, based on the value of `$this->as`.
     * The generated HTML includes the element's class, id, and value attributes.
     *
     * @param ApiOptions|null $callingClass The calling class instance, which must be an instance of ApiOptions.
     *
     * @return string The HTML string for the form label element.
     *
     * @throws \Exception If the calling class is not an instance of ApiOptions.
     */
    public function get(?ApiOptions $callingClass): string
    {
        if (false === is_a($callingClass, ApiOptions::class, false)) {
            throw new \Exception('This method can only be called by an ApiOptions class instance');
        }
        return "<{$this->as}{$this->class}{$this->id}{$this->for}>{$this->text}</{$this->as}>";
    }
}
