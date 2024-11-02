[![CodeFactor](https://www.codefactor.io/repository/github/liturgical-calendar/liturgy-components-php/badge)](https://www.codefactor.io/repository/github/liturgical-calendar/liturgy-components-php)
# Liturgical Calendar Components for PHP
A collection of reusable frontend components, that work with the Liturgical Calendar API
(currently hosted at https://litcal.johnromanodorazio.com/api/dev/).

## Installing the package
Installing the package in your project is as simple as `composer require liturgical-calendar/components`. To install without dev dependencies, after we have `composer require`ed the package: `composer install --no-dev`.

Include in your project's PHP script with `include_once 'vendor/autoload.php';` (adjust the path to vendor/autoload.php accordingly).

Note that this package requires PHP >= 8.1, seeing it makes use of Enums (which were introduced in PHP 8.1).

### CalendarSelect
Produces an html <kbd>\<select\></kbd> element the options of which are populated with data
from the Liturgical Calendar API `/calendars` route. Can be instantiated with an array of options
with the following keys:
  - `url`: The URL of the liturgical calendar metadata API endpoint.
           Defaults to https://litcal.johnromanodorazio.com/api/dev/calendars.
  - `locale`: The locale to use for the calendar select. Defaults to 'en'.
               This is the locale that will be used to translate and order the names of the countries.
               This should be a valid PHP locale string, such as 'en' or 'es' or 'en_US' or 'es_ES'.

To produce the <kbd>\<select\></kbd> element, call the `->getSelect()` method on the `CalendarSelect` instance.
You may optionally pass in an array of options that can have the following keys:
  - `class`: The class or classes to apply to the select element, default `calendarSelect`.
  - `id`:    The id to apply to the select element, default `calendarSelect`.
  - `options`: The type of select options to return.  Valid values are
               `'nations'`, `'diocesesGrouped'`, or `'all'`, default `all`.
  - `label`: A boolean indicating whether to include a label element or not, default `false`.
  - `labelStr`: The string to use for the label element, default `"Select a calendar"`.

Example:
```php
<?php
include_once 'vendor/autoload.php';
use LiturgicalCalendar\Components\CalendarSelect;

$options = ['locale' => 'it']; // set the locale to Italian
$CalendarSelect = new CalendarSelect($options); // use the default API url, but set the locale to Italian

echo $CalendarSelect->getSelect([
                        'class'    => 'form-select',
                        'id'       => 'calendarSelect',
                        'options'  => 'all',
                        'label'    => true,
                        'labelStr' => _("Select calendar")
                    ]);
```

### ApiOptions
Produces a number of html <kbd>\<select\></kbd> elements, with options that correspond to the values of parameters
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
  * the `locale` select element, so that the language names in the select options are output according to the set locale
  * the display values of the `eternal_high_priest` select element (since the final value is a boolean, the display values are simply
    text representations of boolean values, and not the actual value that is sent in an API request)
  * the display values of the `epiphany` select element (which are descriptive to make them more comprehensible)

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
    "formLabel" => true  //we can simply set a boolean, then set the 'as' html element aftereards by using the ->as() method
];
$apiOptions = new ApiOptions($options);
$apiOptions->wrapper->as('div')->class('calendarOptions')->id('calendarOptions'); //we could also have set 'as', 'class' and 'id' directly in the $options
                                                                                  // array by passing an array [ 'as' => 'div', 'class' => 'calendarOptions',
                                                                                  // 'id' => 'calendarOptions' ] to the 'wrapper'
$apiOptions->formLabel->as('h5')->text('Liturgical Calendar API Request Options');//we could also have set 'as' and 'text' directly in the $options
                                                                                  // array by passing an array [ 'as' => 'h5', 'text' => '...' ]
                                                                                  // to 'formLabel'
Input::setGlobalWrapper('div');
Input::setGlobalWrapperClass('form-group');
Input::setGlobalInputClass('form-select');
echo $apiOptions->getForm();
```

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
    "locale"    => "it-IT",
    "wrapper"   => true,
    "formLabel" => true
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

## Tests
The package includes a few unit tests to ensure that the component is working as expected.
In order to run the tests, install the package with the dev dependencies:
```php
composer require liturgical-calendar/components
composer install
```

Then run the `composer test` script, which calls the included PHPUnit package to run the tests in the `tests` folder.

To run a single test class or a single method within a class, use the `composer test-filter` script
followed by the desired `Class` or `Class::method`, e.g.
`composer test-filter CalendarSelectTest::testIsValidLocale`.
