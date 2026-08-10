<?php

namespace LiturgicalCalendar\Components\Tests;

use PHPUnit\Framework\TestCase;
use LiturgicalCalendar\Components\ApiClient;
use LiturgicalCalendar\Components\WebCalendar;
use LiturgicalCalendar\Components\WebCalendar\Grouping;

class WebCalendarTest extends TestCase
{
    private static string $apiResult;

    // Called once per test class, before any test method runs
    public static function setUpBeforeClass(): void
    {
        // Honour the API the bootstrap resolved. This used to hardcode the public
        // instance, so the whole class hung or errored whenever that was slow or
        // down — indistinguishable from a code regression, which is the exact
        // failure mode tests/bootstrap.php exists to avoid.
        $apiUrl = ApiClient::defaultApiUrl();
        $body   = file_get_contents($apiUrl . '/calendar');
        if (false === $body) {
            self::markTestSkippedBeforeClass("Could not reach the API at {$apiUrl}");
        }
        self::$apiResult = $body;
    }

    private static function markTestSkippedBeforeClass(string $message): never
    {
        throw new \PHPUnit\Framework\SkippedWithMessageException($message);
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

    /**
     * buildTable() changes the process locale and LANGUAGE and restores them at
     * the end. Reaching that restore only on the success path leaves the host in
     * a locale it never chose whenever rendering fails part-way — and the
     * component has plenty of throw sites between the two.
     *
     * A locale that Intl rejects gets past setLocale() and then blows up when the
     * first IntlDateFormatter is built, which is a realistic partial render
     * rather than a contrived one.
     */
    public function testRestoresTheProcessLocaleWhenBuildingThrows(): void
    {
        $data                   = json_decode(self::$apiResult);
        $data->settings->locale = 'xx_INVALID_9';

        $localeBefore   = setlocale(LC_ALL, '0');
        $languageBefore = getenv('LANGUAGE');
        $threw          = false;

        try {
            ( new WebCalendar($data) )
                ->firstColumnGrouping(Grouping::BY_MONTH)
                ->buildTable();
        } catch (\Throwable) {
            $threw = true;
        }

        $this->assertTrue($threw, 'The fixture is meant to make buildTable throw part-way');
        $this->assertSame($localeBefore, setlocale(LC_ALL, '0'), 'Locale must be restored on the failure path');
        $this->assertSame($languageBefore, getenv('LANGUAGE'), 'LANGUAGE must be restored on the failure path');
    }
}
