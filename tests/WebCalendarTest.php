<?php

use PHPUnit\Framework\TestCase;
use LiturgicalCalendar\Components\WebCalendar;

class WebCalendarTest extends TestCase
{
    private static string $apiResult;

    // Called once per test class, before any test method runs
    public static function setUpBeforeClass(): void
    {
        self::$apiResult = file_get_contents('https://litcal.johnromanodorazio.com/api/dev/calendar');
    }

    public function testBuildTable()
    {
        $webCalendar = new WebCalendar(json_decode(self::$apiResult));
        $table       = $webCalendar->buildTable();
        $this->assertStringContainsString('<table>', $table);
    }

    public function testBuildTableWithId()
    {
        $webCalendar = new WebCalendar(json_decode(self::$apiResult));
        $webCalendar->id('liturgicalCalendar');
        $table = $webCalendar->buildTable();
        $this->assertStringContainsString('<table id="liturgicalCalendar">', $table);
    }

    public function testBuildTableWithClass()
    {
        $webCalendar = new WebCalendar(json_decode(self::$apiResult));
        $webCalendar->class('calendar');
        $table = $webCalendar->buildTable();
        $this->assertStringContainsString('<table class="calendar">', $table);
    }
}
