<?php
/**
 * Shuriken Reviews Cache Interface
 *
 * @package Shuriken_Reviews
 * @since 1.15.6
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface Shuriken_Cache_Interface
 *
 * TTL-based object cache backed by wp_cache_*.
 *
 * @since 1.15.6
 */
interface Shuriken_Cache_Interface {

    /**
     * Default wp_cache group for plugin cache entries.
     */
    public const GROUP = 'shuriken';

    /**
     * Build a namespaced cache key from stable parts.
     *
     * @param string ...$parts Cache key components.
     * @return string
     */
    public function key(string ...$parts): string;

    /**
     * Whether the persistent statistics layer is enabled.
     *
     * @return bool
     */
    public function is_enabled(): bool;

    /**
     * Retrieve a cached value.
     *
     * @param string $key Cache key.
     * @return mixed Cached value, or false on miss.
     */
    public function get(string $key): mixed;

    /**
     * Store a value in the cache.
     *
     * @param string   $key   Cache key.
     * @param mixed    $value Value to store.
     * @param int|null $ttl   TTL in seconds; null uses the default stats TTL.
     * @return bool True on success.
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    /**
     * Delete a cached value.
     *
     * @param string $key Cache key.
     * @return bool True when the key was deleted.
     */
    public function delete(string $key): bool;

    /**
     * Default TTL for statistics cache entries (seconds).
     *
     * @return int
     */
    public function get_default_ttl(): int;

}
