<?php

namespace LiturgicalCalendar\Components\ApiOptions;

use LiturgicalCalendar\Components\ApiOptions;

class FormLabel
{
    private string $as      = 'label';
    private string $text    = '';
    private string $class   = '';
    private string $id      = '';

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
        }
    }

    public function as(string $element)
    {
        $element = htmlspecialchars($element, ENT_QUOTES, 'UTF-8');
        if (false === in_array($element, ['label', 'legend', 'div', 'span', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'])) {
            throw new \Exception("Invalid label element: {$element}");
        }
        $this->as = $element;
        return $this;
    }

    public function class(string $class)
    {
        $class = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
        $this->class = " class=\"{$class}\"";
        return $this;
    }

    public function id(string $id)
    {
        $id = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        $this->id = " id=\"{$id}\"";
        return $this;
    }

    public function text(string $text)
    {
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $this->text = $text;
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
            throw new \Exception("This method can only be called by an ApiOptions class instance");
        }
        return "<{$this->as}{$this->class}{$this->id}>{$this->text}</{$this->as}>";
    }
}
