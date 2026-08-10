<?php

namespace LiturgicalCalendar\Components\ApiOptions\Input;

use LiturgicalCalendar\Components\ApiOptions\Input;
use LiturgicalCalendar\Components\Rite;

final class Year extends Input
{
    /**
     * The widest range the API will serve for any rite.
     *
     * The floor is a per-rite fact and moves with `min()`; these two are the
     * outer bounds it moves within, and they are the `max` attribute outright.
     */
    private const ABSOLUTE_MIN_YEAR = 1970;
    private const ABSOLUTE_MAX_YEAR = 9999;

    private int $minYear = self::ABSOLUTE_MIN_YEAR;

    public function __construct()
    {
        $this->data(['param' => 'year']);
        $this->name('year');
        $this->id('year');
    }

    /**
     * Sets the earliest selectable year.
     *
     * Mirrors `YearInput.min()` in liturgy-components-js, which exists for the
     * same reason: to raise the floor to the Ambrosian rite's first reformed
     * Missal and to put it back for the Roman rite. Prefer {@see self::rite()}
     * for that — it reads the year off the rite rather than repeating 1976 at
     * the call site — and reach for this directly only to narrow the range for
     * some reason of your own, such as an archive that starts later than the
     * API does.
     *
     * There is deliberately no "already set" guard: the floor is re-set every
     * time the rite changes, and the last call wins.
     *
     * @param int $year The earliest selectable year.
     *
     * @return $this
     *
     * @throws \Exception If the year falls outside the range the API serves.
     */
    public function min(int $year): self
    {
        if ($year < self::ABSOLUTE_MIN_YEAR || $year > self::ABSOLUTE_MAX_YEAR) {
            throw new \Exception("Invalid minimum year: {$year}, valid values are between " . self::ABSOLUTE_MIN_YEAR . ' and ' . self::ABSOLUTE_MAX_YEAR);
        }
        $this->minYear = $year;
        return $this;
    }

    /**
     * Sets the earliest selectable year to the first year the given rite can be
     * computed.
     *
     * 1970 for the Roman rite and 1976 for the Ambrosian — the first year of the
     * reformed Ambrosian Missal. The year is read from {@see Rite::minYear()}
     * rather than restated here, so a rite that gains a floor gains it in one
     * place.
     *
     * Accepts a `Rite` case or its string value, matching
     * {@see \LiturgicalCalendar\Components\CalendarSelect::rite()}; an unknown
     * string is refused here rather than surfacing later as an input whose floor
     * silently stayed put.
     *
     * @param Rite|string $rite A `Rite` case, or its string value.
     *
     * @return $this
     *
     * @throws \Exception If the string is not a valid rite.
     */
    public function rite(Rite|string $rite): self
    {
        return $this->min(Rite::resolve($rite)->minYear());
    }

    public function get(): string
    {
        $html = '';

        $labelClass = $this->labelClass !== null
            ? " class=\"{$this->labelClass}\""
            : ( self::$globalLabelClass !== null
                ? ' class="' . self::$globalLabelClass . '"'
                : '' );
        $labelAfter = $this->labelAfter !== null ? ' ' . $this->labelAfter : '';

        $inputClass = $this->inputClass !== null
            ? " class=\"{$this->inputClass}\""
            : ( self::$globalInputClass !== null
                ? ' class="' . self::$globalInputClass . '"'
                : '' );

        $wrapperClass = $this->wrapperClass !== null
            ? " class=\"{$this->wrapperClass}\""
            : ( self::$globalWrapperClass !== null
                ? ' class="' . self::$globalWrapperClass . '"'
                : '' );
        $wrapper      = $this->wrapper !== null
            ? $this->wrapper
            : ( self::$globalWrapper !== null
                ? self::$globalWrapper
                : null );

        $disabled = $this->disabled ? ' disabled' : '';

        $data = $this->getData();
        $for  = $this->id !== '' ? " for=\"{$this->id}\"" : '';
        $id   = $this->id !== '' ? " id=\"{$this->id}\"" : '';
        $name = $this->name !== '' ? " name=\"{$this->name}\"" : '';

        // An unusable selected value — non-numeric, or outside the range the API
        // serves at all — falls back to the current year, as it always has.
        $selectedYear = ( is_int($this->selectedValue) || ( is_string($this->selectedValue) && is_numeric($this->selectedValue) ) )
            ? (int) $this->selectedValue
            : null;
        $year         = ( $selectedYear !== null && $selectedYear >= self::ABSOLUTE_MIN_YEAR && $selectedYear <= self::ABSOLUTE_MAX_YEAR )
            ? $selectedYear
            : (int) date('Y');

        // Raising the floor alone would leave a year below it rendered as-is —
        // 1970 is a valid Roman year and below the Ambrosian floor of 1976 — and
        // the API would reject the request it built. Clamp up to the floor, which
        // is what the JS component does on the same transition.
        $year = max($year, $this->minYear);

        $html .= $wrapper !== null ? "<{$wrapper}{$wrapperClass}>" : '';
        $html .= "<label{$labelClass}{$for}>year{$labelAfter}</label>";
        $html .= "<input type=\"number\"{$id}{$name}{$inputClass}{$data}{$disabled} min=\"{$this->minYear}\" max=\"" . self::ABSOLUTE_MAX_YEAR . "\" value=\"{$year}\" />";
        $html .= $wrapper !== null ? "</{$wrapper}>" : '';
        return $html;
    }
}
