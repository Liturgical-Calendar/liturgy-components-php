<?php

namespace LiturgicalCalendar\Components\Tests\Cache;

use PHPUnit\Framework\TestCase;
use LiturgicalCalendar\Components\Cache\ArrayCache;

/**
 * Test suite for ArrayCache PSR-16 implementation
 */
class ArrayCacheTest extends TestCase
{
    private ArrayCache $cache;

    protected function setUp(): void
    {
        $this->cache = new ArrayCache();
    }

    public function testGetReturnsDefaultWhenKeyNotSet(): void
    {
        $this->assertNull($this->cache->get('nonexistent'));
        $this->assertEquals('default', $this->cache->get('nonexistent', 'default'));
    }

    public function testSetAndGet(): void
    {
        $this->assertTrue($this->cache->set('key', 'value'));
        $this->assertEquals('value', $this->cache->get('key'));
    }

    public function testSetWithIntegerTtl(): void
    {
        $this->cache->set('key', 'value', 1); // 1 second TTL
        $this->assertEquals('value', $this->cache->get('key'));

        // Sleep to exceed TTL
        sleep(2);
        $this->assertNull($this->cache->get('key'));
    }

    public function testSetWithDateIntervalTtl(): void
    {
        $ttl = new \DateInterval('PT1S'); // 1 second
        $this->cache->set('key', 'value', $ttl);
        $this->assertEquals('value', $this->cache->get('key'));

        sleep(2);
        $this->assertNull($this->cache->get('key'));
    }

    public function testDelete(): void
    {
        $this->cache->set('key', 'value');
        $this->assertEquals('value', $this->cache->get('key'));

        $this->assertTrue($this->cache->delete('key'));
        $this->assertNull($this->cache->get('key'));
    }

    public function testClear(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        $this->cache->set('key3', 'value3');

        $this->assertTrue($this->cache->clear());

        $this->assertNull($this->cache->get('key1'));
        $this->assertNull($this->cache->get('key2'));
        $this->assertNull($this->cache->get('key3'));
    }

    public function testGetMultiple(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');

        $values = $this->cache->getMultiple(['key1', 'key2', 'key3'], 'default');

        $this->assertIsIterable($values);
        $valuesArray = iterator_to_array($values);

        $this->assertEquals('value1', $valuesArray['key1']);
        $this->assertEquals('value2', $valuesArray['key2']);
        $this->assertEquals('default', $valuesArray['key3']);
    }

    public function testSetMultiple(): void
    {
        $values = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];

        $this->assertTrue($this->cache->setMultiple($values));

        $this->assertEquals('value1', $this->cache->get('key1'));
        $this->assertEquals('value2', $this->cache->get('key2'));
        $this->assertEquals('value3', $this->cache->get('key3'));
    }

    public function testSetMultipleWithTtl(): void
    {
        $values = [
            'key1' => 'value1',
            'key2' => 'value2',
        ];

        $this->cache->setMultiple($values, 2);

        // Verify values are immediately available
        $this->assertEquals('value1', $this->cache->get('key1'));
        $this->assertEquals('value2', $this->cache->get('key2'));

        // Sleep longer than TTL to ensure expiry
        sleep(3);

        // Both values should be expired
        $this->assertNull($this->cache->get('key1'));
        $this->assertNull($this->cache->get('key2'));
    }

    public function testDeleteMultiple(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        $this->cache->set('key3', 'value3');

        $this->assertTrue($this->cache->deleteMultiple(['key1', 'key3']));

        $this->assertNull($this->cache->get('key1'));
        $this->assertEquals('value2', $this->cache->get('key2'));
        $this->assertNull($this->cache->get('key3'));
    }

    public function testHas(): void
    {
        $this->assertFalse($this->cache->has('key'));

        $this->cache->set('key', 'value');
        $this->assertTrue($this->cache->has('key'));

        $this->cache->delete('key');
        $this->assertFalse($this->cache->has('key'));
    }

    public function testHasReturnsFalseForExpiredKey(): void
    {
        $this->cache->set('key', 'value', 1);
        $this->assertTrue($this->cache->has('key'));

        sleep(2);
        $this->assertFalse($this->cache->has('key'));
    }

    public function testStoresDifferentDataTypes(): void
    {
        // String
        $this->cache->set('string', 'value');
        $this->assertEquals('value', $this->cache->get('string'));

        // Integer
        $this->cache->set('integer', 42);
        $this->assertEquals(42, $this->cache->get('integer'));

        // Float
        $this->cache->set('float', 3.14);
        $this->assertEquals(3.14, $this->cache->get('float'));

        // Boolean
        $this->cache->set('bool', true);
        $this->assertTrue($this->cache->get('bool'));

        // Array
        $this->cache->set('array', ['a', 'b', 'c']);
        $this->assertEquals(['a', 'b', 'c'], $this->cache->get('array'));

        // Object
        $obj      = new \stdClass();
        $obj->foo = 'bar';
        $this->cache->set('object', $obj);
        $this->assertEquals($obj, $this->cache->get('object'));
    }
}
