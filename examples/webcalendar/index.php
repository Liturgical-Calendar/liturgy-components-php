<?php

require '../../vendor/autoload.php';

use LiturgicalCalendar\Components\ApiClient;
use LiturgicalCalendar\Components\CalendarRequest;
use LiturgicalCalendar\Components\ApiOptions;
use LiturgicalCalendar\Components\ApiOptions\Input;
use LiturgicalCalendar\Components\ApiOptions\PathType;
use LiturgicalCalendar\Components\CalendarSelect;
use LiturgicalCalendar\Components\CalendarSelect\OptionsType;
use LiturgicalCalendar\Components\WebCalendar;
use LiturgicalCalendar\Components\WebCalendar\Grouping;
use LiturgicalCalendar\Components\WebCalendar\ColorAs;
use LiturgicalCalendar\Components\WebCalendar\Column;
use LiturgicalCalendar\Components\WebCalendar\ColumnOrder;
use LiturgicalCalendar\Components\WebCalendar\DateFormat;
use LiturgicalCalendar\Components\WebCalendar\GradeDisplay;

// PSR-compliant HTTP Client with caching and logging
use LiturgicalCalendar\Components\Http\HttpClientFactory;
use LiturgicalCalendar\Components\Cache\ArrayCache;

error_reporting(E_ALL);
ini_set('display_errors', '1');

// ============================================================================
// Setup PSR-compliant HTTP Client with Production Features
// ============================================================================
// NOTE: This example uses dev dependencies (monolog, symfony/cache).
//       Run `composer install` (not `composer install --no-dev`) to use them.
// ============================================================================

// Debug mode from environment (configure in .env file)
$debugMode = filter_var($_ENV['DEBUG_MODE'] ?? 'false', FILTER_VALIDATE_BOOLEAN);

// 1. Setup Logger (Monolog) - if available
$logger = null;

if (class_exists('Monolog\Logger')) {
    // Create logs directory if it doesn't exist
    $logsDir = __DIR__ . '/logs';

    if (!is_dir($logsDir)) {
        if ($debugMode) {
            error_log('Creating logs directory: ' . $logsDir);
        }
        $result = mkdir($logsDir, 0755, true);
        // Always log errors (even when debug mode is disabled)
        if (!$result) {
            $lastError = error_get_last();
            $errorMsg  = $lastError['message'] ?? 'unknown';
            error_log('Failed to create logs directory: ' . $errorMsg);
        }
    }

    try {
        $logger = new Monolog\Logger('liturgical-calendar');
        // Log to file for debugging
        $logger->pushHandler(new Monolog\Handler\StreamHandler(
            $logsDir . '/litcal.log',
            Monolog\Level::Debug
        ));
        if ($debugMode) {
            error_log('Logger initialized successfully');
        }
        $logger->info('Logger initialized successfully');
    } catch (\Exception $e) {
        // Always log errors (even when debug mode is disabled)
        error_log('Error creating logger: ' . $e->getMessage());
    }
} elseif ($debugMode) {
    error_log('Monolog not found - run `composer install` to enable logging');
}

// 2. Setup Cache
// For persistent caching across requests, use Symfony FilesystemAdapter or RedisAdapter
// ArrayCache is in-memory only and resets on each request (good for single-request optimization)

if (class_exists('Symfony\Component\Cache\Adapter\FilesystemAdapter')) {
    // Persistent filesystem cache - survives across requests
    $filesystemAdapter = new Symfony\Component\Cache\Adapter\FilesystemAdapter(
        'litcal',           // namespace
        3600 * 24,          // default TTL: 24 hours
        __DIR__ . '/cache'  // cache directory
    );

    $cache = new Symfony\Component\Cache\Psr16Cache($filesystemAdapter);
} else {
    // Fallback to ArrayCache (in-memory, resets each request)
    // To see cache hits, install: composer require symfony/cache
    $cache = new ArrayCache();
}

// 3. Create Production-Ready HTTP Client
// Includes: Circuit Breaker + Retry + Caching + Logging
$httpClient = HttpClientFactory::createProductionClient(
    cache: $cache,
    logger: $logger,         // null if Monolog not available
    cacheTtl: 3600 * 24,     // Cache for 24 hours
    maxRetries: 3,           // Retry up to 3 times
    failureThreshold: 5      // Circuit breaker opens after 5 failures
);

// ============================================================================
// Environment Configuration
// ============================================================================

// Load environment variables if Dotenv is available
if (class_exists('Dotenv\Dotenv')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__, ['.env', '.env.local', '.env.development', '.env.production'], false);
    $dotenv->ifPresent(['API_PROTOCOL', 'API_HOST'])->notEmpty();
    $dotenv->ifPresent(['API_PORT'])->isInteger();
    $dotenv->safeLoad();
}

// Set default environment variables for production (only if not already set)
$_ENV['API_PROTOCOL']  = $_ENV['API_PROTOCOL'] ?? 'https';
$_ENV['API_HOST']      = $_ENV['API_HOST'] ?? 'litcal.johnromanodorazio.com';
$_ENV['API_PORT']      = $_ENV['API_PORT'] ?? '';
$_ENV['API_BASE_PATH'] = $_ENV['API_BASE_PATH'] ?? '/api/dev';

// ============================================================================
// Build Base API URL (used by ApiClient for all API interactions)
// ============================================================================
// Centralize URL construction to ensure all API requests use the same base URL.
// ApiClient distributes this configuration to MetadataProvider, CalendarRequest,
// and any other components that need API access.

$apiPort    = !empty($_ENV['API_PORT']) ? ":{$_ENV['API_PORT']}" : '';
$apiBaseUrl = rtrim("{$_ENV['API_PROTOCOL']}://{$_ENV['API_HOST']}{$apiPort}{$_ENV['API_BASE_PATH']}", '/');

// ============================================================================
// Initialize ApiClient (Centralized API Configuration)
// ============================================================================
// Initialize ApiClient once with all configuration. This becomes immutable
// for the lifetime of the application. All components (MetadataProvider,
// CalendarRequest, etc.) will use this shared configuration.
//
// Note: $httpClient from createProductionClient() is already decorated with
// cache/logger middleware, so we only pass the httpClient to avoid double-wrapping.

ApiClient::getInstance([
    'apiUrl'     => $apiBaseUrl,
    'httpClient' => $httpClient  // Already decorated - don't pass cache/logger
]);

// ============================================================================
// Initialize Components
// ============================================================================

$apiOptions = new ApiOptions();
$apiOptions->acceptHeaderInput->hide();
Input::setGlobalWrapper('td');

$calendarSelectNations = new CalendarSelect();
$calendarSelectNations->label(true)->labelText('nation')
    ->id('national_calendar')->name('national_calendar')->setOptions(OptionsType::NATIONS)->allowNull(true);

$calendarSelectDioceses = new CalendarSelect();
$calendarSelectDioceses->label(true)->labelText('diocese')
    ->id('diocesan_calendar')->name('diocesan_calendar')->setOptions(OptionsType::DIOCESES)->allowNull(true);

if (isset($_POST) && !empty($_POST)) {
    $requestData    = [];
    $requestHeaders = ['Accept: application/json'];
    $requestPath    = '';
    $requestYear    = '';

    foreach ($_POST as $key => $value) {
        if (is_string($value)) {
            $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }
        switch ($key) {
            case 'year':
                if (false === is_int($value)) {
                    if (is_numeric($value)) {
                        $value = (int) $value;
                    }
                }
                if ($value >= 1970 && $value <= 9999) {
                    $requestYear = '/' . $value;
                }
                break;
            case 'year_type':
                if (null !== $value && !empty($value)) {
                    $requestData[$key] = $value;
                }
                $apiOptions->yearTypeInput->selectedValue($value);
                break;
            case 'epiphany':
            case 'ascension':
            case 'corpus_christi':
            case 'eternal_high_priest':
                // Only add to request data for General Roman Calendar (no nation/diocese selected)
                // and only if the value is not null or empty
                $nationalCalendar = $_POST['national_calendar'] ?? '';
                $diocesanCalendar = $_POST['diocesan_calendar'] ?? '';
                if (empty($nationalCalendar) && empty($diocesanCalendar) && null !== $value && !empty($value)) {
                    $requestData[$key] = $value;
                }
                $camelCaseKey = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $key))) . 'Input');
                $apiOptions->$camelCaseKey->selectedValue($value);
                break;
            case 'holydays_of_obligation':
                // Handle array input (multi-select) for General Roman Calendar only
                if (is_array($value) && !empty($value)) {
                    // Sanitize array values
                    $sanitizedValues = array_map(
                        fn($item) => is_string($item) ? htmlspecialchars($item, ENT_QUOTES, 'UTF-8') : $item,
                        $value
                    );

                    // Only add to request data when neither national nor diocesan calendar is selected
                    // (national/diocesan calendars have predefined holydays of obligation)
                    // and only if there are selected values
                    $nationalCalendar = $_POST['national_calendar'] ?? '';
                    $diocesanCalendar = $_POST['diocesan_calendar'] ?? '';
                    if (empty($nationalCalendar) && empty($diocesanCalendar) && !empty($sanitizedValues)) {
                        $requestData[$key] = $sanitizedValues;
                    }

                    $apiOptions->holydaysOfObligationInput->selectedValue($sanitizedValues);
                }
                break;
        }
    }

    $selectedDiocese = ( isset($_POST['diocesan_calendar']) && !empty($_POST['diocesan_calendar']) )
        ? htmlspecialchars($_POST['diocesan_calendar'], ENT_QUOTES, 'UTF-8')
        : false;
    $selectedNation  = ( isset($_POST['national_calendar']) && !empty($_POST['national_calendar']) )
        ? htmlspecialchars($_POST['national_calendar'], ENT_QUOTES, 'UTF-8')
        : false;
    $selectedLocale  = ( isset($_POST['locale']) && !empty($_POST['locale']) )
        ? htmlspecialchars($_POST['locale'], ENT_QUOTES, 'UTF-8')
        : null;
    if ($selectedLocale) {
        $requestHeaders[] = 'Accept-Language: ' . $selectedLocale;
    }

    if ($selectedDiocese && $selectedNation && false === CalendarSelect::isValidDioceseForNation($selectedDiocese, $selectedNation)) {
        $selectedDiocese = false;
        unset($_POST['diocesan_calendar']);
    }

    if ($selectedDiocese || $selectedNation) {
        $apiOptions->epiphanyInput->disabled();
        $apiOptions->ascensionInput->disabled();
        $apiOptions->corpusChristiInput->disabled();
        $apiOptions->eternalHighPriestInput->disabled();
        $apiOptions->holydaysOfObligationInput->disabled();
    }

    if ($selectedDiocese) {
        $requestPath = '/diocese/' . $selectedDiocese;
        if ($selectedNation) {
            $calendarSelectNations->selectedOption($selectedNation);
            $calendarSelectDioceses->nationFilter($selectedNation)->setOptions(OptionsType::DIOCESES_FOR_NATION);
            $calendarSelectDioceses->selectedOption($selectedDiocese);
        } else {
            // In order to set the `nation` based on the selected `diocese`, we would need info from the /calendars metadata route.
            // We haven't made any requests to the metadata route yet, so we can't get this info yet.
            // However we can also get this information from the calendar response in the `settings` object.
            // Doing so will mean that setting `nation` to empty while there is still a `diocese` selected will force the `nation` back to that of the diocese,
            // so setting `nation` to empty will not clear the `diocese` selection.
            // Only setting to a `nation` for which the `diocese` is invalid will clear the `diocese` selection.
        }
        $apiOptions->localeInput->setOptionsForCalendar('diocese', $selectedDiocese);
    } elseif ($selectedNation) {
        $requestPath = '/nation/' . $selectedNation;
        $calendarSelectNations->selectedOption($selectedNation);
        $calendarSelectDioceses->nationFilter($selectedNation)->setOptions(OptionsType::DIOCESES_FOR_NATION);
        $apiOptions->localeInput->setOptionsForCalendar('nation', $selectedNation);
    }

    // ========================================================================
    // Make Calendar Request using PSR-18 HTTP Client (via CalendarRequest)
    // ========================================================================
    // CalendarRequest automatically uses the ApiClient configuration
    // (HTTP client, logger, cache, API URL) that we initialized earlier.
    $webCalendarHtml = '';
    $requestUrl      = '';

    try {
        // Create CalendarRequest instance (automatically pulls from ApiClient)
        $calendarRequest = new CalendarRequest();

        // Build the request using fluent API
        if ($selectedDiocese) {
            $calendarRequest->diocese($selectedDiocese);
        } elseif ($selectedNation) {
            $calendarRequest->nation($selectedNation);
        }

        // Set year if provided
        if (!empty($requestData['year'] ?? null)) {
            $calendarRequest->year((int) $requestData['year']);
        } elseif (isset($_POST['year']) && is_numeric($_POST['year'])) {
            $calendarRequest->year((int) $_POST['year']);
        }

        // Set locale if provided
        if ($selectedLocale) {
            $calendarRequest->locale($selectedLocale);
        }

        // Set year type if provided
        if (!empty($requestData['year_type'] ?? null)) {
            $calendarRequest->yearType($requestData['year_type']);
        }

        // Set mobile feast settings (only for General Roman Calendar)
        if (!$selectedDiocese && !$selectedNation) {
            if (!empty($requestData['epiphany'] ?? null)) {
                $calendarRequest->epiphany($requestData['epiphany']);
            }
            if (!empty($requestData['ascension'] ?? null)) {
                $calendarRequest->ascension($requestData['ascension']);
            }
            if (!empty($requestData['corpus_christi'] ?? null)) {
                $calendarRequest->corpusChristi($requestData['corpus_christi']);
            }
            if (isset($requestData['eternal_high_priest'])) {
                $calendarRequest->eternalHighPriest((bool) $requestData['eternal_high_priest']);
            }
            if (!empty($requestData['holydays_of_obligation'] ?? null)) {
                $calendarRequest->holydaysOfObligation($requestData['holydays_of_obligation']);
            }
        }

        // Execute the request
        $LiturgicalCalendar = $calendarRequest->get();

        // Build request URL for display purposes
        $pathSegments = ['calendar'];
        if ($selectedDiocese) {
            $pathSegments[] = 'diocese';
            $pathSegments[] = $selectedDiocese;
        } elseif ($selectedNation) {
            $pathSegments[] = 'nation';
            $pathSegments[] = $selectedNation;
        }
        if (isset($_POST['year']) && is_numeric($_POST['year'])) {
            $pathSegments[] = $_POST['year'];
        }
        $requestUrl = $apiBaseUrl . '/' . implode('/', $pathSegments);

        if (true) {
            if (property_exists($LiturgicalCalendar, 'settings') && $LiturgicalCalendar->settings instanceof \stdClass) {
                $apiOptions->epiphanyInput->selectedValue($LiturgicalCalendar->settings->epiphany);
                $apiOptions->ascensionInput->selectedValue($LiturgicalCalendar->settings->ascension);
                $apiOptions->corpusChristiInput->selectedValue($LiturgicalCalendar->settings->corpus_christi);
                $apiOptions->eternalHighPriestInput->selectedValue($LiturgicalCalendar->settings->eternal_high_priest ? 'true' : 'false');
                $apiOptions->localeInput->selectedValue($LiturgicalCalendar->settings->locale);
                $apiOptions->yearTypeInput->selectedValue($LiturgicalCalendar->settings->year_type);
                $apiOptions->yearInput->selectedValue($LiturgicalCalendar->settings->year);
                $holyDaysOfObligationProperties = array_keys(array_filter((array) $LiturgicalCalendar->settings->holydays_of_obligation, fn (bool $v) => $v === true));
                $apiOptions->holydaysOfObligationInput->selectedValue($holyDaysOfObligationProperties);
                if ($selectedDiocese && false === $selectedNation) {
                    $calendarSelectNations->selectedOption($LiturgicalCalendar->settings->national_calendar);
                    $calendarSelectDioceses->nationFilter($LiturgicalCalendar->settings->national_calendar)
                            ->setOptions(OptionsType::DIOCESES_FOR_NATION)->selectedOption($selectedDiocese);
                }
            }

            $webCalendar = new WebCalendar($LiturgicalCalendar);
            $webCalendar->id('LitCalTable')
                        ->firstColumnGrouping(Grouping::BY_LITURGICAL_SEASON)
                        ->psalterWeekGrouping()
                        ->removeHeaderRow()
                        ->seasonColor(ColorAs::CSS_CLASS)
                        ->seasonColorColumns(Column::LITURGICAL_SEASON)
                        ->eventColor(ColorAs::INDICATOR)
                        ->eventColorColumns(Column::EVENT)
                        ->monthHeader()
                        ->dateFormat(DateFormat::DAY_ONLY)
                        ->columnOrder(ColumnOrder::GRADE_FIRST)
                        ->gradeDisplay(GradeDisplay::ABBREVIATED);
            $webCalendarHtml  = $webCalendar->buildTable();
            $webCalendarHtml .=  '<div style="text-align:center;border:3px ridge Green;background-color:LightBlue;width:75%;margin:10px auto;padding:10px;">' . $webCalendar->daysCreated() . ' event days created</div>';
        }
    } catch (\Exception $e) {
        // Handle any errors from CalendarRequest
        $webCalendarHtml = '<div class="col-12">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        if ($debugMode && $logger) {
            $logger->error('Calendar request failed', [
                'error'   => $e->getMessage(),
                'diocese' => $selectedDiocese,
                'nation'  => $selectedNation,
                'year'    => $_POST['year'] ?? null
            ]);
        }
    }
}

?><!DOCTYPE html>
<html>
<head>
    <title>Liturgical Calendar API Options Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" type="text/css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css" rel="stylesheet" type="text/css">
    <style>
        body {
            background-color: #f8f9fa;
        }

        #ApiOptionsForm fieldset {
            border: 1px solid lightgray;
            padding: 6px 12px;
            border-radius: 6px;
        }

        #ApiOptionsForm legend {
            float: none;
            width: auto;
            margin-left: 3px;
            padding: 0 6px;
            font-weight: bold;
            font-size: 1.1em;
            border: 1px solid lightgray;
            border-radius: 3px;
        }

        #LitCalSettings {
            width: 100%;
        }
        #LitCalSettings label {
            display: block;
            margin-bottom: 3px;
        }

        #LitCalTable {
            width: 90%;
            margin: 30px auto;
            /*border: 1px solid Blue;
            border-radius: 6px;*/
            padding: 10px;
            background: white; /*whitesmoke*/
            /**color: whitesmoke; */
            border-collapse: collapse;
            border-spacing: 1px;
        }

        #LitCalTable caption {
            caption-side: top;
            text-align: center;
        }

        #LitCalTable colgroup .col2 {
            width: 10%;
        }

        #LitCalTable td {
            padding: 4px 6px;
            border: 1px dashed lightgray;
        }

        #LitCalTable td.rotate {
            width: 1.5em;
            white-space: nowrap;
            text-align: center;
            vertical-align: middle;
        }

        #LitCalTable td.rotate div {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 1.8em;
            font-weight:bold;
            writing-mode: vertical-rl;
            transform: rotate(180.0deg);
        }

        #LitCalTable .monthHeader {
            text-align: center;
            background-color: #ECA;
            color: darkslateblue;
            font-weight: bold;
        }

        #LitCalTable .dateEntry {
            font-family: 'Trebuchet MS', 'Lucida Sans Unicode', 'Lucida Grande', 'Lucida Sans', Arial, sans-serif;
            font-size:.8em;
        }

        #LitCalTable .eventDetails {
            color: #BD752F;
        }

        #LitCalTable .liturgicalGrade {
            text-align: center;
            font-family:'Gill Sans', 'Gill Sans MT', Calibri, 'Trebuchet MS', sans-serif;
        }

        #LitCalTable .liturgicalGrade.liturgicalGrade_0 {
            visibility: hidden;
        }

        #LitCalTable .liturgicalGrade_0,
        #LitCalTable .liturgicalGrade_1,
        #LitCalTable .liturgicalGrade_2 {
            font-size: .9em;
        }

        #LitCalTable .liturgicalGrade_3 {
            font-size: .9em;
        }

        #LitCalTable .liturgicalGrade_4,
        #LitCalTable .liturgicalGrade_5 {
            font-size: 1em;
        }

        #LitCalTable .liturgicalGrade_6,
        #LitCalTable .liturgicalGrade_7 {
            font-size: 1em;
            font-weight: bold;
        }

        .liturgicalGrade.liturgicalGrade_0,
        .liturgicalGrade.liturgicalGrade_1,
        .liturgicalGrade.liturgicalGrade_2 {
            font-style: italic;
            color: gray;
        }

        #LitCalTable td.purple {
            background-color: plum;
            color: black;
        }

        #LitCalTable td.EASTER_TRIDUUM.purple {
            background-color: palevioletred;
            color: white;
        }

        #LitCalTable td.white {
            background-color: whitesmoke;
            color: black;
        }

        #LitCalTable td.red {
            background-color: lightpink;
            color: black;
        }

        #LitCalTable td.rose {
            background-color: mistyrose;
            color: black;
        }

        #LitCalTable td.green {
            background-color: lightgreen;
            color: black;
        }

        #LitCalMessages {
            width: 75%;
            margin:30px auto;
            border:1px solid darkslategray;
            padding:10px;
            background: lightgray;
        }

        #LitCalMessages th {
            font-size: 1.3em;
            padding: 10px;
        }

        #LitCalMessages td {
            padding: 5px;
            border-bottom: 1px solid White;
        }

        #LitCalMessages td:first-child {
            border-right: 1px groove White;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="text-center mb-4">Options Form for PHP example</h1>
                <form method="post">
                    <table id="LitCalSettings">
                    <?php
                    echo '<tr>';
                    echo '<td colspan="1">' . $calendarSelectNations->getSelect() . '</td>';
                    echo '<td colspan="2">' . $calendarSelectDioceses->getSelect() . '</td>';
                    echo $apiOptions->getForm(PathType::ALL_PATHS);
                    echo '</tr>';

                    echo '<tr>';
                    echo $apiOptions->getForm(PathType::BASE_PATH);
                    echo '</tr>';
                    ?>
                    </table>
                    <button type="submit" class="btn btn-primary mt-2">Submit</button></button>
                </form>
            </div>
        </div>
        <div class="row mb-4">
            <?php
            if (isset($requestData) && !empty($requestData)) {
                echo '<h3><b>Request URL</b></h3>';
                echo '<div class="col-12">' . $requestUrl . '</div>';
                echo '<h3><b>Request Data</b></h3>';
                foreach ($requestData as $key => $value) {
                    $displayValue = match (true) {
                        $value === null || $value === '' => 'null',
                        is_array($value) => htmlspecialchars(implode(', ', $value)),
                        default => htmlspecialchars($value)
                    };
                    echo '<div class="col-2"><b>' . htmlspecialchars($key) . '</b>: ' . $displayValue . '</div>';
                }
                echo '<h3><b>Request Headers</b></h3>';
                foreach ($requestHeaders as $key => $value) {
                    echo '<div class="col-2"><b>' . $key . '</b>: ' . $value . '</div>';
                }
                echo $webCalendarHtml;
            } else {
                echo '<div class="col-12">No POST data (perhaps click on Submit?)</div>';
            }
            echo '<input type="hidden" id="selectedLocale2" value="' . ( $selectedLocale ?? '' ) . '">';
            ?>
        </div>
    </div>
</body>
</html>
