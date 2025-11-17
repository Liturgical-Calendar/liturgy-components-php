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
        // Use mock time provider to control time
        $currentTime  = 1000;
        $timeProvider = function () use (&$currentTime): int {
            return $currentTime;
        };
        $cache        = new ArrayCache($timeProvider);

        $cache->set('key', 'value', 10); // 10 second TTL
        $this->assertEquals('value', $cache->get('key'));

        // Advance time past expiry (1000 + 10 = 1010, set to 1011)
        $currentTime = 1011;
        $this->assertNull($cache->get('key'));
    }

    public function testSetWithDateIntervalTtl(): void
    {
        // Use mock time provider to control time
        $currentTime  = 1000;
        $timeProvider = function () use (&$currentTime): int {
            return $currentTime;
        };
        $cache        = new ArrayCache($timeProvider);

        $ttl = new \DateInterval('PT10S'); // 10 seconds
        $cache->set('key', 'value', $ttl);
        $this->assertEquals('value', $cache->get('key'));

        // Advance time past expiry (1000 + 10 = 1010, set to 1011)
        $currentTime = 1011;
        $this->assertNull($cache->get('key'));
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

        $values      = $this->cache->getMultiple(['key1', 'key2', 'key3'], 'default');
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
        // Use mock time provider to control time
        $currentTime  = 1000;
        $timeProvider = function () use (&$currentTime): int {
            return $currentTime;
        };
        $cache        = new ArrayCache($timeProvider);

        $values = [
            'key1' => 'value1',
            'key2' => 'value2',
        ];

        $cache->setMultiple($values, 10); // 10 second TTL

        // Verify values are immediately available
        $this->assertEquals('value1', $cache->get('key1'));
        $this->assertEquals('value2', $cache->get('key2'));

        // Advance time past expiry (1000 + 10 = 1010, set to 1011)
        $currentTime = 1011;

        // Both values should be expired
        $this->assertNull($cache->get('key1'));
        $this->assertNull($cache->get('key2'));
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
        // Use mock time provider to control time
        $currentTime  = 1000;
        $timeProvider = function () use (&$currentTime): int {
            return $currentTime;
        };
        $cache        = new ArrayCache($timeProvider);

        $cache->set('key', 'value', 10); // 10 second TTL
        $this->assertTrue($cache->has('key'));

        // Advance time past expiry (1000 + 10 = 1010, set to 1011)
        $currentTime = 1011;
        $this->assertFalse($cache->has('key'));
    }

    public function testHasReturnsTrueForNullValue(): void
    {
        // Explicitly store null as a value
        $this->cache->set('null_key', null);

        // has() should return true even though the value is null
        $this->assertTrue($this->cache->has('null_key'));

        // get() should return null (the stored value, not the default)
        $this->assertNull($this->cache->get('null_key'));
        $this->assertNull($this->cache->get('null_key', 'default'));
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
