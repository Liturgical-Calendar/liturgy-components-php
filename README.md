[![CodeFactor](https://www.codefactor.io/repository/github/liturgical-calendar/liturgy-components-php/badge)](https://www.codefactor.io/repository/github/liturgical-calendar/liturgy-components-php)
![php version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FLiturgical-Calendar%2Fliturgy-components-php%2Fmain%2Fcomposer.json&query=require.php&label=php)
[![Packagist](https://img.shields.io/packagist/v/liturgical-calendar/components.svg)](https://packagist.org/packages/liturgical-calendar/components)
[![Packagist stats](https://img.shields.io/packagist/dt/liturgical-calendar/components.svg)](https://packagist.org/packages/liturgical-calendar/components/stats)
<a href="https://translate.johnromanodorazio.com/engage/liturgical-calendar/" target="_blank" title="ApiOptions translations">
<img src="https://translate.johnromanodorazio.com/widget/liturgical-calendar/liturgy-components-php-apioptions/svg-badge.svg" alt="Stato traduzione ApiOptions" />
</a>
<a href="https://translate.johnromanodorazio.com/engage/liturgical-calendar/" target="_blank" title="WebCalendar translations">
<img src="https://translate.johnromanodorazio.com/widget/liturgical-calendar/liturgy-components-php-webcalendar/svg-badge.svg" alt="Stato traduzione WebCalendar" />
</a>

# Liturgical Calendar Components for PHP
A collection of reusable frontend components, that work with the Liturgical Calendar API
(currently hosted at https://litcal.johnromanodorazio.com/api/dev/).

## Installing the package
Installing the package in your project is as simple as `composer require liturgical-calendar/components`.
Include in your project's PHP script with `include_once 'vendor/autoload.php';` (adjust the path to vendor/autoload.php accordingly).

Note that this package requires <b>PHP >= 8.1</b>, seeing it makes use of [Enums](https://www.php.net/manual/en/language.types.enumerations.php) (which were introduced in PHP 8.1). It also requires PHP `ext-intl`. To check if you have all the requirements you can run `composer check-platform-reqs --no-dev`. If you intend on contributing to the repository and installing development requirements, you should run `composer check-platform-reqs`.

### CalendarSelect
Produces an html <kbd>\<select\></kbd> element the <kbd>\<option\></kbd>s of which are populated with data
from the Liturgical Calendar API `/calendars` route. Can be instantiated passing in an array of options
with the following keys:
  - `url`: The URL of the liturgical calendar metadata API endpoint.
           Defaults to https://litcal.johnromanodorazio.com/api/dev/calendars.
  - `locale`: The locale to use for the calendar select. Defaults to 'en'.
               This is the locale that will be used to translate and order the names of the countries.
               This should be a valid PHP locale string, such as 'en' or 'es' or 'en_US' or 'es_ES'.
  - `class`: The class or classes to apply to the select element, default `calendarSelect`.
  - `id`:    The id to apply to the select element, default `calendarSelect`.
  - `name`:  The name to apply to the select element, default `calendarSelect`.
  - `setOptions`: The type of select options to return. Must be a valid case of the `OptionsType` enum. Valid cases are
                   `OptionsType::NATIONS`, `OptionsType::DIOCESES`, `OptionsType::DIOCESES_FOR_NATION`, or `OptionsType::ALL`, default `OptionsType::ALL`.
  - `nationFilter`: When `setOptions` is set to `OptionsType::DIOCESES_FOR_NATION`, this is the nation for which dioceses will be filtered, default `null`.
                     This option MUST be set, and MUST NOT be `null` or empty, when `setOptions` is set to `OptionsType::DIOCESES_FOR_NATION`,
                     otherwise an exception will occur.
  - `selectedOption`: Set one of the options in the select as the default selected option, by value, default `null`.
  - `label`: A boolean indicating whether to include a label element or not, default `false`.
  - `labelText`: The text to use for the label element, default `"Select a calendar"`.
  - `allowNull`: Whether an option with an empty value should be added as the first option of the select, to allow the user to submit a null value, default `false`.
  - `disabled`: Whether to set the `disabled` attribute on the select element, default `false`.

> [!CAUTION]
> When `setOptions` is set to `OptionsType::DIOCESES_FOR_NATION`, the `nationFilter` option MUST also be set, otherwise an exception will occur.

To produce the <kbd>\<select\></kbd> element, call the `->getSelect()` method on the `CalendarSelect` instance.

Example:
```php
<?php
include_once 'vendor/autoload.php';
use LiturgicalCalendar\Components\CalendarSelect;

$options = [
  'locale'    => 'it', // set the locale to Italian
  'class'     => 'form-select',
  'id'        => 'calendarSelect',
  'label'     => true,
  'labelText' => _("Select a calendar")
];
$CalendarSelect = new CalendarSelect($options);

echo $CalendarSelect->getSelect();
```

The options can also be set by using the methods of the same name after instantiating the `CalendarSelect` instance,
rather than passing them into the constructor. These methods allow for chaining.

Example:
```php
<?php
include_once 'vendor/autoload.php';
use LiturgicalCalendar\Components\CalendarSelect;
use LiturgicalCalendar\Components\CalendarSelect\OptionsType;

$CalendarSelect = new CalendarSelect();
$CalendarSelect->nationFilter('NL')->setOptions(OptionsType::DIOCESES_FOR_NATION)->locale('it')->class('form-select')->id('diocesan_calendar')->name('diocesan_calendar')->label(true)->labelText('diocese');

echo $CalendarSelect->getSelect();
```

> [!CAUTION]
> When using the `->setOptions()` method with a value of `OptionsType::DIOCESES_FOR_NATION`,
> the `->nationFilter()` method <b>MUST</b> be called <b>BEFORE</b> calling the `->setOptions()` method, otherwise an exception will occur.


### ApiOptions
Produces a number of html <kbd>\<select\></kbd> elements, with <kbd>\<option\></kbd>s that correspond to the values of parameters
that can be sent in a request to the Liturgical Calendar API `/calendar` route.
The only <kbd>\<select\></kbd> element with options that are populated from the Liturgical API `/calendars` route
is that of the `locale` parameter, with current supported language locales.

To produce the <kbd>\<select\></kbd> elements, call the `->getForm()` method on the `ApiOptions` instance.
Here is an example of the most basic usage:
```php
<?php
require 'vendor/autoload.php';
use LiturgicalCalendar\Components\ApiOptions;
$apiOptions = new ApiOptions();
echo $apiOptions->getForm();
```

Output:
```html
<label>epiphany</label>
<select data-param="epiphany">
    <option value="">--</option>
    <option value="JAN6">January 6th</option>
    <option value="SUNDAY_JAN2_JAN8">Sunday between January 2nd and 8th</option>
</select>
<label>ascension</label>
<select data-param="ascension">
    <option value="">--</option>
    <option value="THURSDAY">Thursday</option>
    <option value="SUNDAY">Sunday</option>
</select>
<label>corpus_christi</label>
<select data-param="corpus_christi">
    <option value="">--</option>
    <option value="THURSDAY">Thursday</option>
    <option value="SUNDAY">Sunday</option>
</select>
<label>eternal_high_priest</label>
<select data-param="eternal_high_priest">
    <option value="">--</option>
    <option value="true">true</option>
    <option value="false">false</option>
</select>
<label>locale</label>
<select data-param="locale">
    <option value="nl">Dutch</option>
    <option value="en">English</option>
    <option value="fr">French</option>
    <option value="de">German</option>
    <option value="hu">Hungarian</option>
    <option value="id">Indonesian</option>
    <option value="it">Italian</option>
    <option value="la" selected="">Latin</option>
    <option value="pt">Portuguese</option>
    <option value="sk">Slovak</option>
    <option value="es">Spanish</option>
    <option value="vi">Vietnamese</option>
</select>
<label>year_type</label>
<select data-param="year_type">
    <option value="LITURGICAL">liturgical</option>
    <option value="CIVIL">civil</option>
</select>
<label>accept header</label>
<select data-param="accept">
    <option value="application/json">application/json</option>
    <option value="application/xml">application/xml</option>
    <option value="application/yaml">application/yaml</option>
    <option value="text/calendar">text/calendar</option>
</select>
```

#### Differentiate parameters according to API path
The <kbd>\<select\></kbd> elements that are output can be differentiated between those that correspond to parameters
that can be sent on any path of the `/calendar/*` route (therefore for any Liturgical Calendar requested whether General Roman, national or diocesan),
and those that only make sense on the base `/calendar` route (therefore only for the General Roman calendar). To differentiate the output,
pass in the `PathType` enum with one of the two possible enum values:
```php
<?php
require 'vendor/autoload.php';
use LiturgicalCalendar\Components\ApiOptions;
use LiturgicalCalendar\Components\ApiOptions\PathType;
$apiOptions = new ApiOptions();
echo $apiOptions->getForm(PathType::BASE_PATH);
echo '<br>';
echo $apiOptions->getForm(PathType::ALL_PATHS);
```

The output will be similar to the previous output, with a `<br>` separating the `year_type` and `accept header` <kbd>\<select\></kbd> elements
from the other <kbd>\<select\></kbd> elements.

#### Set locale for language names and display values
We can change the `locale` for the `ApiOptions` component, which will affect:
  * the `locale` select element, so that the language names in the select options are displayed according to the given locale
  * the display values of the `eternal_high_priest` select element (since the final value is a boolean, the display values are simply
    text representations of boolean values, and not the actual value that is sent in an API request)
  * the display values of the `epiphany` select element (which are descriptive to make them more comprehensible)
  * the display values of the `ascension` and `corpus_christ` select elements ("Sunday" and "Thursday" will be displayed according to the given locale)

```php
<?php
require 'vendor/autoload.php';
use LiturgicalCalendar\Components\ApiOptions;
use LiturgicalCalendar\Components\ApiOptions\PathType;
$options = [
    'locale'    => 'it-IT'
];
$apiOptions = new ApiOptions($options);
echo $apiOptions->getForm(PathType::BASE_PATH);
echo '<br>';
echo $apiOptions->getForm(PathType::ALL_PATHS);
```

The `locale` select will now look like this:
```html
<select data-param="locale">
    <option value="fr">francese</option>
    <option value="id">indonesiano</option>
    <option value="en">inglese</option>
    <option value="it">italiano</option>
    <option value="la" selected="">latino</option>
    <option value="nl">olandese</option>
    <option value="pt">portoghese</option>
    <option value="sk">slovacco</option>
    <option value="es">spagnolo</option>
    <option value="de">tedesco</option>
    <option value="hu">ungherese</option>
    <option value="vi">vietnamita</option>
</select>
```

#### Set a wrapper and a label
We can optionally set a <kbd>\<form\></kbd> or <kbd>\<div\></kbd> wrapper around the whole of the output,
and we can also set the `class` and `id` of the wrapper element, and we can also set a `label` for the form
(which will be included within the wrapper element):
```php
<?php
require 'vendor/autoload.php';
use LiturgicalCalendar\Components\ApiOptions;
$options = [
    'locale'    => 'it-IT',
    'wrapper'   => 'div', //we can set a string representing the 'as' html element
    'formLabel' => 'h5'   //we can set a string representing the 'as' html element
];
$apiOptions = new ApiOptions($options);
$apiOptions->wrapper->class('calendarOptions')->id('calendarOptions');
$apiOptions->formLabel->text('Liturgical Calendar API Request Options');
echo $apiOptions->getForm();
```

```html
<div class="calendarOptions" id="calendarOptions"> <!-- wrapper element -->
  <h5>Liturgical Calendar API Request Options</h5> <!-- form label element -->
  <label>epiphany</label>
  <select>...
</div>
```

#### Set a common wrapper element for each of the form select inputs
The `ApiOptions` component allows for fine grained control via a number of methods.

For example we can set a common wrapper element that will be wrapped around each of the form select elements,
via the `Input::setGlobalWrapper()` and `Input::setGlobalWrapperClass()` methods.
We can also set a common class to be used on all of the form select elements,
via the `Input::setGlobalInputClass()` method.

```php
<?php
require 'vendor/autoload.php';
use LiturgicalCalendar\Components\ApiOptions;
use LiturgicalCalendar\Components\ApiOptions\Input;

$options = [
    "locale"    => "it-IT",
    "wrapper"   => true, //we can simply set a boolean, then set the 'as' html element afterwards by using the ->as() method
    "formLabel" => true  //we can simply set a boolean, then set the 'as' html element afterwards by using the ->as() method
];

$apiOptions = new ApiOptions($options);

$apiOptions->wrapper->as('div')->class('calendarOptions')->id('calendarOptions');
$apiOptions->formLabel->as('h5')->text('Liturgical Calendar API Request Options');

Input::setGlobalWrapper('div');
Input::setGlobalWrapperClass('form-group');
Input::setGlobalInputClass('form-select');
echo $apiOptions->getForm();
```

> [!NOTE]
> Other than setting the `as` html element as a string value in the `wrapper` option, or enabling the `wrapper` with a boolean value, we can also set `wrapper` to an associative array with the desired options.
> The following three examples are equivalent:
> ```php
> // EXAMPLE 1
> $options = [
>     'wrapper'   => 'div'
> ];
> $apiOptions = new ApiOptions($options);
> $apiOptions->wrapper->class('calendarOptions')->id('calendarOptions');
>
> // EXAMPLE 2
> $options = [
>     'wrapper'   => [ 'as' => 'div', 'class' => 'calendarOptions', 'id' => 'calendarOptions' ]
> ];
> $apiOptions = new ApiOptions($options);
>
> // EXAMPLE 3
> $options = [
>     'wrapper'   => true
> ];
> $apiOptions = new ApiOptions($options);
> $apiOptions->wrapper->as('div')->class('calendarOptions')->id('calendarOptions');
> ```

> [!NOTE]
> Other than setting the `as` html element as a string value in the `formLabel` option, or enabling the `formLabel` with a boolean value, we can also set `formLabel` to an associative array with the desired options.
> The following three examples are equivalent:
> ```php
> // EXAMPLE 1
> $options = [
>     'formLabel'   => 'h5'
> ];
> $apiOptions = new ApiOptions($options);
> $apiOptions->formLabel->text('Liturgical Calendar API Request Options');
>
> // EXAMPLE 2
> $options = [
>     'formLabel'   => [ 'as' => 'h5', 'text' => 'Liturgical Calendar API Request Options' ]
> ];
> $apiOptions = new ApiOptions($options);
>
> // EXAMPLE 3
> $options = [
>     'formLabel'   => true
> ];
> $apiOptions = new ApiOptions($options);
> $apiOptions->formLabel->as('h5')->text('Liturgical Calendar API Request Options');
> ```

Output:

```html
<div class="calendarOptions" id="calendarOptions"> <!-- wrapper element -->
  <h5>Liturgical Calendar API Request Options</h5> <!-- form label element -->
  <div class="form-group">
    <label>epiphany</label>
    <select class="form-select">...</select>
  </div>
  <div class="form-group">
    <label>ascension</label>
    <select class="form-select">...</select>
  </div>
</div>
```

#### Fine grain control of single form select inputs
Usually we would want to have the same wrapper and wrapper classes and select element classes on all of the form select inputs.
However, if we do need for any reason to have finer grained control on a specific select element, say for example we would like
to set an `id` attribute on a specific select element, we can do so by targeting the relative input. The select inputs are available
on the `ApiOptions` instance as the following properties:
 * `epiphanyInput`
 * `ascensionInput`
 * `corpusChristiInput`
 * `eternalHighPriestInput`
 * `yearTypeInput`
 * `localeInput`
 * `acceptHeaderInput`

Each of these has it's own `->class()`, `->id()`, `->labelClass()`, `->wrapper()`, and `->wrapperClass()` methods.
If a global input wrapper or input class is also set, the single input's fine-grained methods will override the global settings
for the specific input instance.

```php
<?php
require 'vendor/autoload.php';
use LiturgicalCalendar\Components\ApiOptions;
use LiturgicalCalendar\Components\ApiOptions\Input;
$options = [
    'locale'    => 'it-IT',
    'wrapper'   => true,
    'formLabel' => true
];
$apiOptions = new ApiOptions($options);
$apiOptions->wrapper->as('div')->class('calendarOptions')->id('calendarOptions');
$apiOptions->formLabel->as('h5')->text('Liturgical Calendar API Request Options');
$apiOptions->epiphanyInput->class('epiphany-input')->id('epiphanyInput')->labelClass('epiphany-label')->wrapper('div')->wrapperClass('epiphany-wrapper');
echo $apiOptions->getForm();
```

Output:
```html
<div class="calendarOptions" id="calendarOptions"> <!-- wrapper element -->
  <h5>Liturgical Calendar API Request Options</h5> <!-- form label element -->
  <div class="epiphany-wrapper">
    <label class="epiphany-label">epiphany</label>
    <select class="epiphany-input" id="epiphanyInput">...</select>
  </div>
  ...
</div>
```

### WebCalendar

A WebCalendar is instantiated with a response object from the Liturgical Calendar API. It does not currently take care of making the request,
the request must first be sent to the API and the response must be transformed into an object, and passed in to the WebCalendar constructor.

```php
<?php
require 'vendor/autoload.php';

use LiturgicalCalendar\Components\WebCalendar;

// build your request here
// $response = curl_exec($ch);

// Get an object from the response
$LiturgicalCalendar = json_decode($response);

// If we have successfully obtained an object, pass it into the WebCalendar constructor
if (JSON_ERROR_NONE === json_last_error()) {
    $webCalendar = new WebCalendar($LiturgicalCalendar);
    $table = $webCalendar->buildTable();
    echo $table;
} else {
    echo '<div class="col-12">JSON error: ' . json_last_error_msg() . '</div>';
}
```

#### CSS classes
Most of the table styling should be handled with CSS styling rules.

To this end, a number of CSS classes are created by default in the resulting table.
* <kbd>&lt;colgroup&gt;</kbd>: each <kbd>&lt;col&gt;</kbd> element within the table's <kbd>&lt;colgroup&gt;</kbd> will have a class of `colN` where `N` is the number of the column, starting from 1. This allows to set for example the width styling of specific columns, rather than relying on the browser to calculate the width automatically.
* the first column has a default class of `rotate` which allows for a CSS rule that will rotate the text such as:
   ```css
   #LitCalTable td.rotate div {
     writing-mode: vertical-rl;
     transform: rotate(180.0deg);
   }
   ```
   Additionally, if the first column grouping is set to `Grouping::BY_MONTH` (see [Chainable methods](https://github.com/Liturgical-Calendar/liturgy-components-php#chainable-methods) below), each cell of the column will have class `month`.
   If instead the grouping is set to `Grouping::BY_LITURGICAL_SEASON`, each cell of the column will have additional classes `season {LITURGICAL_SEASON}` where `{LITURGICAL_SEASON}` is a value of `ADVENT`, `CHRISTMAS`, `LENT`, `EASTER_TRIDUUM`, `EASTER` or `ORDINARY_TIME`.
* if Month header rows are enabled, each Month header cell will have a class of `monthHeader`
* Date column cells have a class of `dateEntry`
* Event details column cells have a class of `eventDetails liturgicalGrade_{GRADE}` where `{GRADE}` is the numerical rank of the festivity, where:
   * 0 = weekday
   * 1 = commemoration
   * 2 = optional memorial
   * 3 = memorial
   * 4 = feast
   * 5 = feast of the Lord
   * 6 = solemnity
   * 7 = higher solemnity
* Liturgical grade column cells have a class of `liturgicalGrade liturgicalGrade_{GRADE}` (as above)
* if Psalter week grouping is enabled, Psalter week column cells will have a class of `psalterWeek`

> [!NOTE]
> The WebCalendar component currently suppresses the `grade_display` for celebrations of rank 7, since it is more explanatory than actually useful for display in a web calendar, having a value along the lines of *'celebration with precedence over solemnities'*.


#### Chainable methods

The WebCalendar instance also has a number of methods that allow to further adjust and customize the layout of the calendar.
These methods allow for chaining, making it easy to call them one after the other:
```php
use LiturgicalCalendar\Components\WebCalendar;
use LiturgicalCalendar\Components\WebCalendar\Grouping;
use LiturgicalCalendar\Components\WebCalendar\ColorAs;
use LiturgicalCalendar\Components\WebCalendar\Column;
use LiturgicalCalendar\Components\WebCalendar\DateFormat;

// make your request and get an object from the response...

    $webCalendar = new WebCalendar($LiturgicalCalendar);
    $webCalendar->id('LitCalTable')
                ->class('.liturgicalCalendar')
                ->firstColumnGrouping(Grouping::BY_LITURGICAL_SEASON)
                ->psalterWeekGrouping()
                ->removeHeaderRow()
                ->removeCaption()
                ->seasonColor(ColorAs::CSS_CLASS)
                ->seasonColorColumns(Column::LITURGICAL_SEASON)
                ->eventColor(ColorAs::INDICATOR)
                ->eventColorColumns(Column::EVENT)
                ->monthHeader()
                ->dateFormat(DateFormat::DAY_ONLY);
```

* `id(string $id)`: sets the `id` attribute of the `<table>` element
* `class(string $class)`: sets the `class` attribute of the `<table>` element
* `firstColumnGrouping(Grouping $grouping)`: sets the grouping for the first column. Can take an enum value of:
   * `Grouping::BY_MONTH`: the first column will contain month groupings
   * `Grouping::BY_LITURGICAL_SEASON`: the first column will contain liturgical season groupings
* `psalterWeekGrouping(bool $boolVal = true)`: sets whether the psalter week column is produced.
   It is always the last column, and liturgical events within the same Psalter week are grouped together.
* `removeHeaderRow(bool $removeHeaderRow = true)`: sets whether the header row should be removed from the table
* `removeCaption(bool $removeCaption = true)`: sets whether the table caption should be removed from the table
* `seasonColor(ColorAs $colorAs)`: sets how the season color is applied to the table. Can take an enum value of:
   * `ColorAs::CSS_CLASS`: the season color will be applied to given column cells as a class (value of `green`, `red`, `white`, `purple`, `pink`)
   * `ColorAs::BACKGROUND`: the season color will be applied to given column cells as an inline style
   * `ColorAs::INDICATOR`: a small circle with background color corresponding to the season color will be created in given column cells
   * `ColorAs::NONE`: none of the above
* `seasonColorColumns(Column|int $columnFlags = Column::NONE)`: sets which columns should be affected by the `seasonColor` settings. The method takes a `Column` enum as parameter, the available enum cases are:
   * `Column::LITURGICAL_SEASON`
   * `Column::MONTH`
   * `Column::DATE`
   * `Column::EVENT`
   * `Column::GRADE`
   * `Column::PSALTER_WEEK`
   * `Column::ALL`
   * `Column::NONE`

  The `Column` enum values are bitfield values, so they can be combined with a bitwise OR operator `|`, but being an enum, the values are obtained with `Column::LITURGICAL_SEASON->value`, `Column::MONTH->value`, etc. A bitwise combination of columns would look like: `seasonColorColumns(Column::LITURGICAL_SEASON->value | Column::DATE->value | Column::PSALTER_WEEK->value)`. As a convenience, we have a `Column::ALL` enum case that represents the OR'd value of all columns, and a `Column::NONE` enum case the represents a zero value, effectively disabling all columns from any season color effects.
* `eventColor(ColorAs $colorAs)`: sets how the color for the single liturgical celebration is applied to the table. See `seasonColor` above for the `ColorAs` enum cases.
* `eventColorColumns(Columns|int $columnFlags = Column::NONE)`: sets which columns should be affected by the `eventColor` settings. See the `seasonColorColumns` method above for usage of the `Column` enum cases.
* `monthHeader(bool $monthHeader = true)`: sets whether month headers should be produced at the start of each month
* `dateFormat(DateFormat $dateFormat = DateFormat::FULL)`: sets how the date should be displayed in the Date column. The `DateFormat` enum cases correspond to a selection of `IntlDateFormatter` constants:
   * `DateFormat::FULL`: The full date format for the locale, e.g. "Friday, March 3, 2023" or "venerdì 3 marzo 2023".
   * `DateFormat::LONG`: The long date format for the locale, e.g. "March 3, 2023" or "3 marzo 2023".
   * `DateFormat::MEDIUM`: The medium date format for the locale, e.g. "Mar 3, 2023" or "3 mar 2023".
   * `DateFormat::SHORT`: The short date format for the locale, e.g. "3/3/23" or "03/03/23".
   * `DateFormat::DAY_ONLY`: Only the day of the month and the weekday, e.g. "3 Friday" or "3 venerdì".

#### Non chainable methods

There a few methods that return a value, and therefore do not allow for chaining, because they do not return the instance but rather a value.

* `getLocale()`: returns the locale that the WebCalendar instance is currently set to. Note that the locale can only be set by the Liturgical Object that is passed into the WebCalendar constructor.
* `daysCreated()`: returns the count of days on which liturgical events take place in the current WebCalendar. Note that this will only return a value after `buildTable()` is called. The value will vary depending on whether the year requested is a leap year or not, and on whether a CIVIL or LITURGICAL year is being produced.
* `buildTable()`: returns an HTML string with the table containing the Liturgical Calendar, built according to the settings from the chainable methods.

## Examples
An examples folder has been included in the repo to allow for easy testing. Currently there is a `/webcalendar` subfolder with an example of usage of the `WebCalendar` component. To quickly test locally, `cd` into the `/webcalendar` subfolder and run `php -S localhost:3000`, then open your browser at `http://localhost:3000`. You should see a form with `ApiOptions` and `CalendarSelect`. Click on <kbd>Submit</kbd> to see the actual web calendar.

Note that this will use the default remote API. If you would like to test against a local instance of the API, first make sure that you have installed the development requirements with `composer install` (whereas `composer install --no-dev` would install without development requirements).
With development requirements in place, copy `/examples/webcalendar/.env.example` to `/examples/webcalendar/.env.development`, and set the value of `API_PORT` to the port of your local API instance. If you are spawning an instance of the API on port 8000, then set `API_PORT` to 8000. As long as `APP_ENV` is set to `development`, the `/examples/webcalendar` example will use your local API instance instead of the remote production API. For more information on spawning a local instance of the API, see the [Liturgical Calendar API Readme - testing locally](https://github.com/Liturgical-Calendar/LiturgicalCalendarAPI?tab=readme-ov-file#testing-locally).

## Tests
The package includes a few unit tests to ensure that the component is working as expected.
In order to run the tests, clone the package repository locally and install the dev dependencies:
```bash session
git clone https://github.com/Liturgical-Calendar/liturgy-components-php.git
cd liturgy-components-php
composer install --no-dev
```

Then run the `composer test` script, which calls the included PHPUnit package to run the tests in the `tests` folder.

To run a single test class or a single method within a class, use the `composer test-filter` script
followed by the desired `Class` or `Class::method`, e.g.
`composer test-filter CalendarSelectTest::testIsValidLocale`.

## Translations
The few translatable strings in the component are handled via weblate. Click on the following badges to contribute to the translations.

### ApiOptions translations

<a href="https://translate.johnromanodorazio.com/engage/liturgical-calendar/" target="_blank">
<img src="https://translate.johnromanodorazio.com/widget/liturgical-calendar/liturgy-components-php-apioptions/multi-auto.svg" alt="Stato traduzione" />
</a>
<a href="https://translate.johnromanodorazio.com/engage/liturgical-calendar/" target="_blank">
<img src="https://translate.johnromanodorazio.com/widget/liturgical-calendar/liturgy-components-php-apioptions/287x66-white.png" alt="Stato traduzione" />
</a>

### WebCalendar translations
<a href="https://translate.johnromanodorazio.com/engage/liturgical-calendar/" target="_blank">
<img src="https://translate.johnromanodorazio.com/widget/liturgical-calendar/liturgy-components-php-webcalendar/multi-auto.svg" alt="Stato traduzione" />
</a>
<a href="https://translate.johnromanodorazio.com/engage/liturgical-calendar/" target="_blank">
<img src="https://translate.johnromanodorazio.com/widget/liturgical-calendar/liturgy-components-php-webcalendar/287x66-white.png" alt="Stato traduzione" />
</a>
