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
        $form = $apiOptions->getForm(PathType::BASE_PATH);
        $this->assertStringContainsString('<label>epiphany</label>', $form);
        $this->assertStringContainsString('<label>ascension</label>', $form);
        $this->assertStringContainsString('<label>corpus_christi</label>', $form);
        $this->assertStringContainsString('<label>eternal_high_priest</label>', $form);
        $this->assertStringContainsString('<label>locale</label>', $form);
        $this->assertStringContainsString('<select data-param="epiphany">', $form);
        $this->assertStringContainsString('<select data-param="ascension">', $form);
        $this->assertStringContainsString('<select data-param="corpus_christi">', $form);
        $this->assertStringContainsString('<select data-param="eternal_high_priest">', $form);
        $this->assertStringContainsString('<select data-param="locale">', $form);
    }

    public function testGetFormAllPaths(): void
    {
        $apiOptions = new ApiOptions();
        $form = $apiOptions->getForm(PathType::ALL_PATHS);
        $this->assertStringContainsString('<label>year_type</label>', $form);
        $this->assertStringContainsString('<label>accept header</label>', $form);
        $this->assertStringContainsString('<select data-param="year_type">', $form);
        $this->assertStringContainsString('<select data-param="accept">', $form);
    }

    public function testGetFormBasePathWithCustomOptions(): void
    {
        $options = [
            "locale"    => "it-IT",
            "formLabel" => true,
            "wrapper"   => true
        ];
        $apiOptions = new ApiOptions($options);
        $apiOptions->wrapper->as('div')->class('row mb-4')->id('calendarOptions');
        $apiOptions->formLabel->as('h5')->class('fw-bold')->text('Calendar Options - Base Path');
        Input::setGlobalWrapper('div');
        Input::setGlobalWrapperClass('form-group col-sm-2');
        Input::setGlobalInputClass('form-select');
        $form = $apiOptions->getForm(PathType::BASE_PATH);
        $dom = new \DOMDocument();
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
            $values = [];
            $this->assertTrue($select->hasAttribute('data-param'));
            switch ($select->getAttribute('data-param')) {
                case 'epiphany':
                    $this->assertCount(3, $options);
                    foreach ($options as $option) {
                        /** @var DOMElement $option */
                        $values[] = $option->getAttribute('value');
                    }
                    $this->assertEquals(['', 'JAN6', 'SUNDAY_JAN2_JAN8'], $values);
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
                    $this->assertCount(3, $options);
                    foreach ($options as $option) {
                        /** @var DOMElement $option */
                        $values[] = $option->getAttribute('value');
                    }
                    $this->assertEquals(['', 'true', 'false'], $values);
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
                    $localeOptionsValues = ["fr", "id", "it", "la", "nl", "pt", "sk", "es", "de", "hu", "vi"];
                    $localeOptionsLabels = ["francese", "indonesiano", "italiano", "latino", "olandese", "portoghese", "slovacco", "spagnolo", "tedesco", "ungherese", "vietnamita"];
                    $this->assertContainsAll($localeOptionsValues, $values);
                    $this->assertContainsAll($localeOptionsLabels, $labels);
                    $this->assertEquals('locale', $precedingSibling->textContent);
                    break;
            }
        }
    }

    public function testGetFormAllPathsWithCustomOptions(): void
    {
        $options = [
            "locale"    => "it-IT",
            "formLabel" => true,
            "wrapper"   => true
        ];
        $apiOptions = new ApiOptions($options);
        $apiOptions->wrapper->as('div')->class('row mb-4')->id('calendarOptions');
        $apiOptions->formLabel->as('h5')->class('fw-bold')->text('Calendar Options - All Paths');
        Input::setGlobalWrapper('div');
        Input::setGlobalWrapperClass('form-group col-sm-2');
        Input::setGlobalInputClass('form-select');
        $form = $apiOptions->getForm(PathType::ALL_PATHS);
        $dom = new \DOMDocument();
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
            $values = [];
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