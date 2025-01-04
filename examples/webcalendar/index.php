<?php

require '../../vendor/autoload.php';
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

error_reporting(E_ALL);
ini_set('display_errors', '1');

$options = null;
if (class_exists('Dotenv\Dotenv')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__, ['.env', '.env.local', '.env.development', '.env.production'], false);
    $dotenv->ifPresent(['API_PROTOCOL', 'API_HOST'])->notEmpty();
    $dotenv->ifPresent(['API_PORT'])->isInteger();
    $dotenv->safeLoad();
    if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'development') {
        if (false === isset($_ENV['API_PROTOCOL']) || false === isset($_ENV['API_HOST']) || false === isset($_ENV['API_PORT'])) {
            die("API_PROTOCOL, API_HOST and API_PORT must be defined in .env.development or similar dotenv when APP_ENV is development");
        }
        $options = ['url' => "{$_ENV['API_PROTOCOL']}://{$_ENV['API_HOST']}:{$_ENV['API_PORT']}"];
    }
}

$apiOptions = new ApiOptions($options);
$apiOptions->acceptHeaderInput->hide();
Input::setGlobalWrapper('td');

$calendarSelectNations = new CalendarSelect($options);
$calendarSelectNations->label(true)->labelText('nation')
    ->id('national_calendar')->name('national_calendar')->setOptions(OptionsType::NATIONS)->allowNull(true);

$calendarSelectDioceses = new CalendarSelect($options);
$calendarSelectDioceses->label(true)->labelText('diocese')
    ->id('diocesan_calendar')->name('diocesan_calendar')->setOptions(OptionsType::DIOCESES)->allowNull(true);

if (isset($_POST) && !empty($_POST)) {
    $requestData = [];
    $requestHeaders = [
        'Accept: application/json'
    ];
    $requestPath = '';
    $requestYear = '';
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
                if (false === isset($_POST['national_calendar']) && false === isset($_POST['diocesan_calendar'])) {
                    $requestData[$key] = $value;
                }
                $camelCaseKey = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $key))) . 'Input');
                $apiOptions->$camelCaseKey->selectedValue($value);
                break;
        }
    }

    $selectedDiocese = (isset($_POST['diocesan_calendar']) && !empty($_POST['diocesan_calendar']))
        ? htmlspecialchars($_POST['diocesan_calendar'], ENT_QUOTES, 'UTF-8')
        : false;
    $selectedNation = (isset($_POST['national_calendar']) && !empty($_POST['national_calendar']))
        ? htmlspecialchars($_POST['national_calendar'], ENT_QUOTES, 'UTF-8')
        : false;
    $selectedLocale = (isset($_POST['locale']) && !empty($_POST['locale']))
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

    $requestUrl = "{$_ENV['API_PROTOCOL']}://{$_ENV['API_HOST']}:{$_ENV['API_PORT']}/calendar{$requestPath}{$requestYear}";

    $webCalendarHtml = '';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $requestUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($requestData));
    $response = curl_exec($ch);
    curl_close($ch);
    if ($response) {
        //echo '<pre>' . $response . '</pre>';
        $LiturgicalCalendar = json_decode($response);
        if (JSON_ERROR_NONE === json_last_error()) {
            $apiOptions->epiphanyInput->selectedValue($LiturgicalCalendar->settings->epiphany);
            $apiOptions->ascensionInput->selectedValue($LiturgicalCalendar->settings->ascension);
            $apiOptions->corpusChristiInput->selectedValue($LiturgicalCalendar->settings->corpus_christi);
            $apiOptions->eternalHighPriestInput->selectedValue($LiturgicalCalendar->settings->eternal_high_priest ? 'true' : 'false');
            $apiOptions->localeInput->selectedValue($LiturgicalCalendar->settings->locale);
            $apiOptions->yearTypeInput->selectedValue($LiturgicalCalendar->settings->year_type);
            $apiOptions->yearInput->selectedValue($LiturgicalCalendar->settings->year);
            if ($selectedDiocese && false === $selectedNation) {
                $calendarSelectNations->selectedOption($LiturgicalCalendar->settings->national_calendar);
                $calendarSelectDioceses->nationFilter($LiturgicalCalendar->settings->national_calendar)
                        ->setOptions(OptionsType::DIOCESES_FOR_NATION)->selectedOption($selectedDiocese);
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
            $webCalendarHtml = $webCalendar->buildTable();
            $webCalendarHtml .=  '<div style="text-align:center;border:3px ridge Green;background-color:LightBlue;width:75%;margin:10px auto;padding:10px;">' . $webCalendar->daysCreated() . ' event days created</div>';
        } else {
            $webCalendarHtml = '<div class="col-12">JSON error: ' . json_last_error_msg() . '</div>';
        }
    } else {
        $webCalendarHtml = '<div class="col-12">No response</div>';
    }
}

?><!DOCTYPE html>
<html>
<head>
    <title>Liturgical Calendar API Options Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" type="text/css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css" rel="stylesheet" type="text/css">
    <style>
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

        #LitCalTable .liturgicalGrade_0, #LitCalTable .liturgicalGrade_1, #LitCalTable .liturgicalGrade_2 {
            font-size: .9em;
        }

        #LitCalTable .liturgicalGrade_3 {
            font-size: .9em;
        }

        #LitCalTable .liturgicalGrade_4, #LitCalTable .liturgicalGrade_5 {
            font-size: 1em;
        }

        #LitCalTable .liturgicalGrade_6, #LitCalTable .liturgicalGrade_7 {
            font-size: 1em;
            font-weight: bold;
        }

        .liturgicalGrade.liturgicalGrade_0, .liturgicalGrade.liturgicalGrade_1, .liturgicalGrade.liturgicalGrade_2 {
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

        #LitCalTable td.pink {
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
                    echo '<div class="col-2"><b>' . $key . '</b>: ' . ($value === null || empty($value) ? 'null' : $value) . '</div>';
                }
                echo '<h3><b>Request Headers</b></h3>';
                foreach ($requestHeaders as $key => $value) {
                    echo '<div class="col-2"><b>' . $key . '</b>: ' . $value . '</div>';
                }
                echo $webCalendarHtml;
            } else {
                echo '<div class="col-12">No POST data (perhaps click on Submit?)</div>';
            }
            echo '<input type="hidden" id="selectedLocale2" value="' . ($selectedLocale ?? '') . '">';
            ?>
        </div>
    </div>
</body>
</html>
