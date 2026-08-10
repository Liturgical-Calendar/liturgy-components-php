<?php

namespace LiturgicalCalendar\Components\Tests;

use LiturgicalCalendar\Components\Rite;
use PHPUnit\Framework\TestCase;

final class RiteTest extends TestCase
{
    public function testValuesMatchTheApiAndTheJsLibrary(): void
    {
        $this->assertSame('roman', Rite::ROMAN->value);
        $this->assertSame('ambrosian', Rite::AMBROSIAN->value);
    }

    public function testOnlyTheRomanRiteHasANationalTier(): void
    {
        $this->assertTrue(Rite::ROMAN->hasNationalTier());
        $this->assertFalse(Rite::AMBROSIAN->hasNationalTier());
    }

    public function testOnlyTheAmbrosianRiteFixesItsTemporalOptions(): void
    {
        $this->assertFalse(Rite::ROMAN->hasFixedTemporalOptions());
        $this->assertTrue(Rite::AMBROSIAN->hasFixedTemporalOptions());
    }

    public function testMinYearIsTheFirstYearTheRiteCanBeComputed(): void
    {
        $this->assertSame(1970, Rite::ROMAN->minYear());
        $this->assertSame(1976, Rite::AMBROSIAN->minYear());
    }

    public function testEmptyOptionLabelNamesTheRiteLevelCalendar(): void
    {
        $this->assertSame('General Roman Calendar', Rite::ROMAN->emptyOptionLabel());
        $this->assertSame('Ambrosian Calendar', Rite::AMBROSIAN->emptyOptionLabel());
    }

    public function testUnknownRiteIsRejected(): void
    {
        $this->assertNull(Rite::tryFrom('byzantine'));
    }
}
