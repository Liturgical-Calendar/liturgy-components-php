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

    public function testResolvePassesACaseThroughUntouched(): void
    {
        $this->assertSame(Rite::ROMAN, Rite::resolve(Rite::ROMAN));
        $this->assertSame(Rite::AMBROSIAN, Rite::resolve(Rite::AMBROSIAN));
    }

    public function testResolveReturnsTheCaseNamedByItsStringValue(): void
    {
        $this->assertSame(Rite::ROMAN, Rite::resolve('roman'));
        $this->assertSame(Rite::AMBROSIAN, Rite::resolve('ambrosian'));
    }

    /**
     * The exact message, not a pattern. Four components refused an unknown rite
     * with this wording before it was hoisted here, and each of them has a test
     * asserting it, so the wording is observable behaviour rather than an
     * implementation detail. Naming the valid values is the whole reason this
     * exists instead of `Rite::from()`.
     */
    public function testResolveRefusesAnUnknownRiteNamingTheValidValues(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid rite: byzantine, valid values are: roman, ambrosian');
        Rite::resolve('byzantine');
    }

    /**
     * The gap `resolve()` closes: the built-in throws a `\ValueError` that names
     * neither the valid values nor, usefully, the enum a caller is holding.
     */
    public function testTheBuiltInFromDoesNotNameTheValidValues(): void
    {
        $this->expectException(\ValueError::class);
        Rite::from('byzantine');
    }
}
