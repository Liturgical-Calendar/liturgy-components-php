<?php

namespace LiturgicalCalendar\Components\Cache;

use Psr\SimpleCache\CacheInterface;

/**
 * Simple in-memory cache for development and testing
 * Request-scoped only (no persistence)
 *
 * Implements PSR-16 Simple Cache interface
 */
class ArrayCache implements CacheInterface
{
    /** @var array<string, mixed> */
    private array $cache = [];

    /** @var array<string, int> */
    private array $expiry = [];

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!isset($this->cache[$key])) {
            return $default;
        }

        // Check expiry
        if (isset($this->expiry[$key]) && time() > $this->expiry[$key]) {
            unset($this->cache[$key], $this->expiry[$key]);
            return $default;
        }

        return $this->cache[$key];
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param null|int|\DateInterval $ttl
     * @return bool
     */
    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $this->cache[$key] = $value;

        if ($ttl !== null) {
            $seconds = $ttl instanceof \DateInterval
                ? max(0, ( new \DateTime() )->add($ttl)->getTimestamp() - time())
                : $ttl;

            $this->expiry[$key] = time() + $seconds;
        }

        return true;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool
    {
        unset($this->cache[$key], $this->expiry[$key]);
        return true;
    }

    /**
     * @return bool
     */
    public function clear(): bool
    {
        $this->cache  = [];
        $this->expiry = [];
        return true;
    }

    /**
     * @param iterable<string> $keys
     * @param mixed $default
     * @return iterable<string, mixed>
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    /**
     * @param iterable<string, mixed> $values
     * @param null|int|\DateInterval $ttl
     * @return bool
     */
    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    /**
     * @param iterable<string> $keys
     * @return bool
     */
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return true;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }
}
