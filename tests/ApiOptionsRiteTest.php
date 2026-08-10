<?php

namespace LiturgicalCalendar\Components\Tests;

use PHPUnit\Framework\TestCase;
use LiturgicalCalendar\Components\ApiClient;
use LiturgicalCalendar\Components\ApiOptions;
use LiturgicalCalendar\Components\Metadata\MetadataProvider;
use LiturgicalCalendar\Components\Rite;

/**
 * The rite an `ApiOptions` form is built for.
 *
 * Everything it currently governs is the year input's floor, so that is what
 * these assert on — through the form, not through the input, since the point of
 * the option is that a caller never has to reach for the input at all.
 */
final class ApiOptionsRiteTest extends TestCase
{
    /**
     * The Locale input fetches metadata; see the same hook in ApiOptionsTest for
     * why it is class-level and why the singletons are reset first.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        ApiClient::resetForTesting();
        MetadataProvider::resetForTesting();
    }

    protected function setUp(): void
    {
        ApiOptions::resetForTesting();
    }

    public function testTheRiteOptionSetsTheYearFloor(): void
    {
        $apiOptions = new ApiOptions(['rite' => Rite::AMBROSIAN]);

        $this->assertStringContainsString('min="1976"', $apiOptions->yearInput->get());
    }

    public function testTheRiteOptionAcceptsTheStringValueOfTheRite(): void
    {
        $apiOptions = new ApiOptions(['rite' => 'ambrosian']);

        $this->assertStringContainsString('min="1976"', $apiOptions->yearInput->get());
    }

    public function testTheFloorReachesTheRenderedForm(): void
    {
        $form = ( new ApiOptions(['rite' => Rite::AMBROSIAN]) )->getForm();

        $this->assertStringContainsString('min="1976"', $form);
        $this->assertStringNotContainsString('min="1970"', $form);
    }

    public function testWithoutARiteTheFloorStaysWhereEveryEarlierReleasePutIt(): void
    {
        $this->assertStringContainsString('min="1970"', ( new ApiOptions() )->getForm());
    }

    public function testTheRomanRiteIsTheSameAsNoRiteAtAll(): void
    {
        $this->assertStringContainsString('min="1970"', ( new ApiOptions(['rite' => Rite::ROMAN]) )->getForm());
    }

    public function testAnUnknownRiteIsRefusedNamingTheValidValues(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Invalid rite: byzantine.*roman.*ambrosian/');
        new ApiOptions(['rite' => 'byzantine']);
    }

    public function testAValueThatIsNeitherARiteNorAStringIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected Rite or string for rite, got integer');
        new ApiOptions(['rite' => 1976]);
    }

    /**
     * The option is a starting point, not a lock: a caller that wants a floor of
     * its own can still set one, and the later call wins.
     */
    public function testAnExplicitFloorStillOverridesTheOption(): void
    {
        $apiOptions = new ApiOptions(['rite' => Rite::AMBROSIAN]);
        $apiOptions->yearInput->min(2000);

        $this->assertStringContainsString('min="2000"', $apiOptions->yearInput->get());
    }
}
