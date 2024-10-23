<?php

namespace LiturgicalCalendar\Components\ApiOptions;

use LiturgicalCalendar\Components\ApiOptions;

class Wrapper
{
    private string $as      = 'div';
    private string $class   = '';
    private string $id      = '';

    public function __construct(?string $as = null)
    {
        if ($as !== null) {
            if (false === in_array($as, ['div', 'form'])) {
                throw new \Exception("Invalid wrapper element: {$as}");
            }
            $this->as = $as;
        }
    }

    public function as(string $element)
    {
        if (false === in_array($element, ['div', 'form'])) {
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

    /**
     * Generates and returns an HTML string for a wrapper element.
     *
     * This method creates an HTML string representing a wrapper element,
     * which can be either a `<div>` or `<form>`, based on the value of `$this->as`.
     * The generated HTML includes the element's class, id, and contents.
     *
     * @param string $contents The contents of the wrapper element.
     * @param ApiOptions|null $callingClass The calling class instance, which must be an instance of ApiOptions.
     *
     * @return string The HTML string for the wrapper element.
     *
     * @throws \Exception If the calling class is not an instance of ApiOptions.
     */
    public function get(string $contents, ?ApiOptions $callingClass): string
    {
        if (false === is_a($callingClass, ApiOptions::class, false)) {
            throw new \Exception("This method can only be called by an ApiOptions class instance");
        }
        return "<{$this->as}{$this->class}{$this->id}>{$contents}</{$this->as}>";
    }
}
