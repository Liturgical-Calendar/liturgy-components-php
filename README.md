# Liturgical Calendar Components for PHP
A collection of reusable frontend components, that work with the Liturgical Calendar API
(currently hosted at https://litcal.johnromanodorazio.com/api/dev/).

## Installing the package
Installing the package in your project is as simple as `composer install liturgical-calendar/components`.

Include in your project with `include_once 'vendor/autoload.php';` (adjust the path to vendor/autoload.php accordingly).

### CalendarSelect
Produces an html select element the options of which are populated with data
from the Liturgical Calendar API `/calendars` route. Can be instantiated with an array of options
with the following keys:
  - `url`: The URL of the liturgical calendar metadata API endpoint.
           Defaults to https://litcal.johnromanodorazio.com/api/dev/calendars.
  - `locale`: The locale to use for the calendar select. Defaults to 'en'.
               This is the locale that will be used to translate and order the names of the countries.
               This should be a valid PHP locale string, such as 'en' or 'es' or 'en_US' or 'es_ES'.

To produce the `<select>` element, call the `->getSelect()` method on the `CalendarSelect` instance.
You may optionally pass in an array of options that can have the following keys:
  - `class`: The class or classes to apply to the select element.
  - `id`:    The id to apply to the select element.
  - `options`: The type of select options to return.  Valid values are
               `'nations'`, `'diocesesGrouped'`, or `'all'`.
  - `label`: A boolean indicating whether to include a label element or not.
  - `labelStr`: The string to use for the label element.

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
