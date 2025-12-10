<?php

namespace LiturgicalCalendar\Components\Tests;

use PHPUnit\Framework\TestCase;
use LiturgicalCalendar\Components\CalendarSelect;
use LiturgicalCalendar\Components\ApiClient;
use LiturgicalCalendar\Components\Metadata\MetadataProvider;

class CalendarSelectTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset singletons to ensure test isolation
        ApiClient::resetForTesting();
        MetadataProvider::resetForTesting();
    }

    public function testConstructorDefaults()
    {
        $calendarSelect = new CalendarSelect();
        $this->assertEquals('https://litcal.johnromanodorazio.com/api/dev/calendars', MetadataProvider::getMetadataUrl());
        $this->assertEquals('en', $calendarSelect->getLocale());
    }

    public function testConstructorOptions()
    {
        $options        = [
            'locale' => 'fr-FR'
        ];
        $calendarSelect = new CalendarSelect($options);
        $this->assertEquals('https://litcal.johnromanodorazio.com/api/dev/calendars', MetadataProvider::getMetadataUrl());
        $this->assertEquals('fr_FR', $calendarSelect->getLocale());
    }

    public function testInvalidLocale()
    {
        $this->expectException(\Exception::class);
        $options = [
            'locale' => ' invalid-locale '
        ];
        new CalendarSelect($options);
    }

    public function testGetSelect()
    {
        $calendarSelect = new CalendarSelect();
        $selectHtml     = $calendarSelect->getSelect();
        $this->assertStringContainsString('<select id="calendarSelect" name="calendarSelect" class="calendarSelect">', $selectHtml);
        $this->assertStringContainsString('</select>', $selectHtml);
    }

    public function testGetSelectWithOptions()
    {
        $options        = [
            'class' => 'form-select',
            'id'    => 'mySelect',
            'name'  => 'mySelect'
        ];
        $calendarSelect = new CalendarSelect($options);
        $selectHtml     = $calendarSelect->getSelect();
        $this->assertStringContainsString('<select id="mySelect" name="mySelect" class="form-select">', $selectHtml);
        $this->assertStringContainsString('</select>', $selectHtml);
    }

    public function testIsValidLocale()
    {
        $this->assertTrue(CalendarSelect::isValidLocale('en'));
        $this->assertTrue(CalendarSelect::isValidLocale('fr'));
        $this->assertTrue(CalendarSelect::isValidLocale('es'));
        $this->assertTrue(CalendarSelect::isValidLocale('it'));
        $this->assertTrue(CalendarSelect::isValidLocale('de'));
        $this->assertTrue(CalendarSelect::isValidLocale('pt'));
        $this->assertTrue(CalendarSelect::isValidLocale('nl'));
        $this->assertTrue(CalendarSelect::isValidLocale('zh'));
        $this->assertTrue(CalendarSelect::isValidLocale('en_US'));
        $this->assertTrue(CalendarSelect::isValidLocale('es_ES'));
        $this->assertFalse(CalendarSelect::isValidLocale(' invalid-locale '));
    }

    public function testDataMethodWithValueAttribute()
    {
        $calendarSelect = new CalendarSelect();
        $calendarSelect->data(['calendar-type' => 'national']);
        $selectHtml = $calendarSelect->getSelect();
        $this->assertStringContainsString('data-calendar-type="national"', $selectHtml);
    }

    public function testDataMethodWithEmptyValueAttribute()
    {
        $calendarSelect = new CalendarSelect();
        $calendarSelect->data(['requires-auth' => '']);
        $selectHtml = $calendarSelect->getSelect();
        $this->assertStringContainsString('data-requires-auth', $selectHtml);
        $this->assertStringNotContainsString('data-requires-auth=""', $selectHtml);
    }

    public function testDataMethodWithMultipleAttributes()
    {
        $calendarSelect = new CalendarSelect();
        $calendarSelect->data([
            'requires-auth' => '',
            'calendar-type' => 'national',
            'api-version'   => '2.0'
        ]);
        $selectHtml = $calendarSelect->getSelect();
        $this->assertStringContainsString('data-requires-auth', $selectHtml);
        $this->assertStringContainsString('data-calendar-type="national"', $selectHtml);
        $this->assertStringContainsString('data-api-version="2.0"', $selectHtml);
    }

    public function testDataMethodChaining()
    {
        $calendarSelect = new CalendarSelect();
        $result         = $calendarSelect
            ->class('form-select')
            ->data(['requires-auth' => ''])
            ->id('mySelect');
        $this->assertSame($calendarSelect, $result);
        $selectHtml = $calendarSelect->getSelect();
        $this->assertStringContainsString('class="form-select"', $selectHtml);
        $this->assertStringContainsString('data-requires-auth', $selectHtml);
        $this->assertStringContainsString('id="mySelect"', $selectHtml);
    }

    public function testDataMethodEscapesHtml()
    {
        $calendarSelect = new CalendarSelect();
        $calendarSelect->data(['test-attr' => '<script>alert("xss")</script>']);
        $selectHtml = $calendarSelect->getSelect();
        $this->assertStringNotContainsString('<script>', $selectHtml);
        $this->assertStringContainsString('&lt;script&gt;', $selectHtml);
    }

    public function testDataMethodWithNoAttributes()
    {
        $calendarSelect = new CalendarSelect();
        $selectHtml     = $calendarSelect->getSelect();
        // Ensure there are no stray data- attributes when none are set
        $this->assertMatchesRegularExpression('/<select[^>]*>/', $selectHtml);
        // Check that no data- attributes appear on the select element itself
        preg_match('/<select[^>]*>/', $selectHtml, $matches);
        $this->assertNotEmpty($matches);
        $this->assertStringNotContainsString('data-', $matches[0]);
    }

    public function testDataMethodRejectsInvalidAttributeName()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid data attribute name');
        $calendarSelect = new CalendarSelect();
        $calendarSelect->data(['invalid<name' => 'value']);
    }

    public function testDataMethodRejectsAttributeNameStartingWithNumber()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid data attribute name');
        $calendarSelect = new CalendarSelect();
        $calendarSelect->data(['123invalid' => 'value']);
    }

    public function testDataMethodAcceptsValidAttributeNames()
    {
        $calendarSelect = new CalendarSelect();
        // Should not throw - valid attribute names
        $calendarSelect->data([
            'simple'       => 'value1',
            'with-hyphen'  => 'value2',
            'with_under'   => 'value3',
            'withNumbers1' => 'value4',
            'name:space'   => 'value5'
        ]);
        $selectHtml = $calendarSelect->getSelect();
        $this->assertStringContainsString('data-simple="value1"', $selectHtml);
        $this->assertStringContainsString('data-with-hyphen="value2"', $selectHtml);
        $this->assertStringContainsString('data-with_under="value3"', $selectHtml);
        $this->assertStringContainsString('data-withNumbers1="value4"', $selectHtml);
        $this->assertStringContainsString('data-name:space="value5"', $selectHtml);
    }

    public function testDataMethodReplacesNotMerges()
    {
        $calendarSelect = new CalendarSelect();
        $calendarSelect->data(['first' => 'value1']);
        $calendarSelect->data(['second' => 'value2']);
        $selectHtml = $calendarSelect->getSelect();
        // Second call should replace, not merge
        $this->assertStringNotContainsString('data-first', $selectHtml);
        $this->assertStringContainsString('data-second="value2"', $selectHtml);
    }
}
