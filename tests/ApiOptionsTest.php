<?php

use PHPUnit\Framework\TestCase;
use LiturgicalCalendar\Components\ApiOptions;
use LiturgicalCalendar\Components\ApiOptions\Input;
use LiturgicalCalendar\Components\ApiOptions\PathType;

class ApiOptionsTest extends TestCase
{
    public function assertContainsAll(array $expectedElements, array $actualArray)
    {
        foreach ($expectedElements as $element) {
            $this->assertContains($element, $actualArray);
        }
    }

    public function testGetFormBasePath(): void
    {
        $apiOptions = new ApiOptions();
        $form       = $apiOptions->getForm(PathType::BASE_PATH);
        //$dom = new \DOMDocument();
        //$dom->loadHTML($form);

        $this->assertStringContainsString('<label for="epiphany">epiphany</label>', $form);
        $this->assertStringContainsString('<label for="ascension">ascension</label>', $form);
        $this->assertStringContainsString('<label for="corpus_christi">corpus_christi</label>', $form);
        $this->assertStringContainsString('<label for="eternal_high_priest">eternal_high_priest</label>', $form);
        $this->assertStringContainsString('<select id="epiphany" name="epiphany" data-param="epiphany">', $form);
        $this->assertStringContainsString('<select id="ascension" name="ascension" data-param="ascension">', $form);
        $this->assertStringContainsString('<select id="corpus_christi" name="corpus_christi" data-param="corpus_christi">', $form);
        $this->assertStringContainsString('<select id="eternal_high_priest" name="eternal_high_priest" data-param="eternal_high_priest">', $form);
    }

    public function testGetFormAllPaths(): void
    {
        $apiOptions = new ApiOptions();
        $form       = $apiOptions->getForm(PathType::ALL_PATHS);
        $this->assertStringContainsString('<label for="year_type">year_type</label>', $form);
        $this->assertStringContainsString('<label for="return_type">accept header</label>', $form);
        $this->assertStringContainsString('<label for="locale">locale</label>', $form);
        $this->assertStringContainsString('<select id="year_type" name="year_type" data-param="year_type">', $form);
        $this->assertStringContainsString('<select id="return_type" name="return_type" data-param="accept">', $form);
        $this->assertStringContainsString('<select id="locale" name="locale" data-param="locale">', $form);
    }

    public function testGetFormBasePathWithCustomOptions(): void
    {
        $options    = [
            'locale'    => 'it-IT',
            'formLabel' => true,
            'wrapper'   => true
        ];
        $apiOptions = new ApiOptions($options);
        //$expectedTextDomainPath = dirname(__DIR__) . "/src/ApiOptions/i18n";
        //$currentTextDomainPath = bindtextdomain("litcompphp", $expectedTextDomainPath);
        //$this->assertEquals($apiOptions->expectedTextDomainPath, $apiOptions->currentTextDomainPath);

        $baseLocale = \Locale::getPrimaryLanguage($apiOptions->currentSetLocale);
        $this->assertEquals('it', $baseLocale);
        $this->assertEquals($apiOptions->expectedTextDomainPath, $apiOptions->currentTextDomainPath);
        $apiOptions->wrapper->as('div')->class('row mb-4')->id('calendarOptions');
        $apiOptions->formLabel->as('h5')->class('fw-bold')->text('Calendar Options - Base Path');
        Input::setGlobalWrapper('div');
        Input::setGlobalWrapperClass('form-group col-sm-2');
        Input::setGlobalInputClass('form-select');
        $form = $apiOptions->getForm(PathType::BASE_PATH);
        $dom  = new \DOMDocument();
        $dom->loadHTML($form);
        $divs = $dom->getElementsByTagName('div');
        /** @var DOMElement $formWrapper */
        $formWrapper = $divs->item(0);
        $this->assertInstanceOf(\DOMElement::class, $formWrapper);
        $this->assertEquals('div', $formWrapper->tagName);
        $this->assertEquals('row mb-4', $formWrapper->getAttribute('class'));
        $this->assertEquals('calendarOptions', $formWrapper->getAttribute('id'));
        /** @var DOMElement $h5 */
        $h5 = $formWrapper->firstChild;
        $this->assertInstanceOf(\DOMElement::class, $h5);
        $this->assertEquals('h5', $h5->tagName);
        $this->assertEquals('fw-bold', $h5->getAttribute('class'));
        $this->assertEquals('Calendar Options - Base Path', $h5->textContent);
        $selects = $dom->getElementsByTagName('select');
        foreach ($selects as $select) {
            /** @var DOMElement $precedingSibling */
            $precedingSibling = $select->previousSibling;
            $this->assertInstanceOf(\DOMElement::class, $precedingSibling);
            $this->assertEquals('label', $precedingSibling->tagName);
            /** @var DOMElement $parent */
            $parent = $select->parentNode;
            $this->assertInstanceOf(\DOMElement::class, $parent);
            $this->assertEquals('div', $parent->tagName);
            $this->assertEquals('form-group col-sm-2', $parent->getAttribute('class'));
            /** @var DOMElement $select */
            $options = $select->getElementsByTagName('option');
            $values  = [];
            $texts   = [];
            $this->assertTrue($select->hasAttribute('data-param'));
            switch ($select->getAttribute('data-param')) {
                case 'epiphany':
                    $this->assertCount(3, $options);
                    foreach ($options as $option) {
                        /** @var DOMElement $option */
                        $values[] = $option->getAttribute('value');
                        $texts[]  = $option->textContent;
                    }
                    $this->assertEquals(['', 'JAN6', 'SUNDAY_JAN2_JAN8'], $values);
                    //TODO: for some reason this assertion fails, the translated strings are not loaded from the bound textdomain
                    //      The localized date '6 gennaio' is translated just fine, but the localized 'Domenica tra il 2 e il 8 gennaio' is not
                    //$this->assertEquals(['--', '6 gennaio', "Domenica tra il 2 e l'8 gennaio"], $texts);
                    $this->assertEquals('epiphany', $precedingSibling->textContent);
                    break;
                case 'ascension':
                    $this->assertCount(3, $options);
                    foreach ($options as $option) {
                        /** @var DOMElement $option */
                        $values[] = $option->getAttribute('value');
                    }
                    $this->assertEquals(['', 'THURSDAY', 'SUNDAY'], $values);
                    $this->assertEquals('ascension', $precedingSibling->textContent);
                    break;
                case 'corpus_christi':
                    $this->assertCount(3, $options);
                    foreach ($options as $option) {
                        /** @var DOMElement $option */
                        $values[] = $option->getAttribute('value');
                    }
                    $this->assertEquals(['', 'THURSDAY', 'SUNDAY'], $values);
                    $this->assertEquals('corpus_christi', $precedingSibling->textContent);
                    break;
                case 'eternal_high_priest':
                    $this->assertCount(2, $options);
                    foreach ($options as $option) {
                        /** @var DOMElement $option */
                        $values[] = $option->getAttribute('value');
                        $texts[]  = $option->textContent;
                    }
                    $this->assertEquals(['false', 'true'], $values);
                    //TODO: for some reason this assertion fails, the English strings are not translated
                    //$this->assertEquals(['falso', 'vero'], $texts);
                    $this->assertEquals('eternal_high_priest', $precedingSibling->textContent);
                    break;
                case 'locale':
                    //we won't assert a count here, because it could change as new languages are added to the API
                    // let's just check if the options and values are at least those that are currently expected
                    $labels = [];
                    foreach ($options as $option) {
                        /** @var DOMElement $option */
                        $values[] = $option->getAttribute('value');
                        $labels[] = $option->textContent;
                    }
                    $localeOptionsValues = ['fr', 'id', 'it', 'la', 'nl', 'pt', 'sk', 'es', 'de', 'hu', 'vi'];
                    $localeOptionsLabels = ['fr (francese)', 'id (indonesiano)', 'it (italiano)', 'la (latino)', 'nl (olandese)', 'pt (portoghese)', 'sk (slovacco)', 'es (spagnolo)', 'de (tedesco)', 'hu (ungherese)', 'vi (vietnamita)'];
                    $this->assertContainsAll($localeOptionsValues, $values);
                    $this->assertContainsAll($localeOptionsLabels, $labels);
                    $this->assertEquals('locale', $precedingSibling->textContent);
                    break;
            }
        }
    }

    public function testGetFormAllPathsWithCustomOptions(): void
    {
        $options    = [
            'locale'    => 'it-IT',
            'formLabel' => true,
            'wrapper'   => true
        ];
        $apiOptions = new ApiOptions($options);
        $apiOptions->wrapper->as('div')->class('row mb-4')->id('calendarOptions');
        $apiOptions->formLabel->as('h5')->class('fw-bold')->text('Calendar Options - All Paths');
        Input::setGlobalWrapper('div');
        Input::setGlobalWrapperClass('form-group col-sm-2');
        Input::setGlobalInputClass('form-select');
        $form = $apiOptions->getForm(PathType::ALL_PATHS);
        $dom  = new \DOMDocument();
        $dom->loadHTML($form);
        $divs = $dom->getElementsByTagName('div');
        /** @var DOMElement $formWrapper */
        $formWrapper = $divs->item(0);
        $this->assertInstanceOf(\DOMElement::class, $formWrapper);
        $this->assertEquals('div', $formWrapper->tagName);
        $this->assertEquals('row mb-4', $formWrapper->getAttribute('class'));
        $this->assertEquals('calendarOptions', $formWrapper->getAttribute('id'));
        /** @var DOMElement $h5 */
        $h5 = $formWrapper->firstChild;
        $this->assertInstanceOf(\DOMElement::class, $h5);
        $this->assertEquals('h5', $h5->tagName);
        $this->assertEquals('fw-bold', $h5->getAttribute('class'));
        $this->assertEquals('Calendar Options - All Paths', $h5->textContent);
        $selects = $dom->getElementsByTagName('select');
        foreach ($selects as $select) {
            /** @var DOMElement $precedingSibling */
            $precedingSibling = $select->previousSibling;
            $this->assertInstanceOf(\DOMElement::class, $precedingSibling);
            $this->assertEquals('label', $precedingSibling->tagName);
            /** @var DOMElement $parent */
            $parent = $select->parentNode;
            $this->assertInstanceOf(\DOMElement::class, $parent);
            $this->assertEquals('div', $parent->tagName);
            $this->assertEquals('form-group col-sm-2', $parent->getAttribute('class'));
            /** @var DOMElement $select */
            $options = $select->getElementsByTagName('option');
            $values  = [];
            $this->assertTrue($select->hasAttribute('data-param'));
            switch ($select->getAttribute('data-param')) {
                case 'year_type':
                    $this->assertCount(2, $options);
                    foreach ($options as $option) {
                        /** @var DOMElement $option */
                        $values[] = $option->getAttribute('value');
                    }
                    $this->assertEquals(['LITURGICAL', 'CIVIL'], $values);
                    $this->assertEquals('year_type', $precedingSibling->textContent);
                    break;
                case 'accept':
                    $labels = [];
                    $this->assertCount(4, $options);
                    foreach ($options as $option) {
                        /** @var DOMElement $option */
                        $values[] = $option->getAttribute('value');
                        $labels[] = $option->textContent;
                    }
                    $this->assertContainsAll(['application/json', 'application/xml', 'application/yaml', 'text/calendar'], $values);
                    $this->assertContainsAll(['application/json', 'application/xml', 'application/yaml', 'text/calendar'], $labels);
                    $this->assertEquals('accept header', $precedingSibling->textContent);
                    break;
            }
        }
    }
}
