<?php
/**
 * Shuriken Reviews Rate Limit Cache Interface
 *
 * @package Shuriken_Reviews
 * @since 1.15.6
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface Shuriken_Rate_Limit_Cache_Interface
 *
 * Persistent transient storage for rolling vote counters and cooldowns.
 *
 * @since 1.15.6
 */
interface Shuriken_Rate_Limit_Cache_Interface {

    /**
     * Retrieve a rolling-window vote counter.
     *
     * @param int         $user_id User ID (0 for guests).
     * @param string|null $user_ip Guest IP address.
     * @param string      $window  Window name (`hourly` or `daily`).
     * @return array{count: int, resets_at: int}|false Counter data, or false on miss.
     */
    public function get_counter(int $user_id, ?string $user_ip, string $window): array|false;

    /**
     * Store a rolling-window vote counter.
     *
     * @param int         $user_id  User ID (0 for guests).
     * @param string|null $user_ip  Guest IP address.
     * @param string      $window   Window name (`hourly` or `daily`).
     * @param int         $count    Current vote count.
     * @param int         $resets_at Site-local Unix timestamp when the oldest vote leaves the window.
     * @return bool True when both transient entries were stored.
     */
    public function set_counter(int $user_id, ?string $user_ip, string $window, int $count, int $resets_at): bool;

    /**
     * Atomically increment an existing rolling-window counter when possible.
     *
     * A missing or unsupported counter is deleted so the next read falls back
     * to the database rather than serving a stale undercount.
     *
     * @param int         $user_id User ID (0 for guests).
     * @param string|null $user_ip Guest IP address.
     * @param string      $window  Window name (`hourly` or `daily`).
     * @return void
     */
    public function increment_counter(int $user_id, ?string $user_ip, string $window): void;

    /**
     * Delete both rolling-window counters for an identity.
     *
     * @param int         $user_id User ID (0 for guests).
     * @param string|null $user_ip Guest IP address.
     * @return void
     */
    public function delete_counters(int $user_id, ?string $user_ip): void;

    /**
     * Retrieve a cached last-vote timestamp.
     *
     * An empty string is a cached "no prior vote" result; false is a cache miss.
     *
     * @param int         $rating_id    Rating ID.
     * @param int         $user_id      User ID (0 for guests).
     * @param string|null $user_ip      Guest IP address.
     * @param int|null    $context_id   Optional context ID.
     * @param string|null $context_type Optional context type.
     * @return string|false Last-vote datetime, empty string, or false on miss.
     */
    public function get_cooldown(int $rating_id, int $user_id, ?string $user_ip, ?int $context_id, ?string $context_type): string|false;

    /**
     * Store a last-vote timestamp for the cooldown duration.
     *
     * @param int         $rating_id     Rating ID.
     * @param int         $user_id       User ID (0 for guests).
     * @param string|null $user_ip       Guest IP address.
     * @param int|null    $context_id    Optional context ID.
     * @param string|null $context_type  Optional context type.
     * @param string|null $last_vote_time Last-vote datetime, or null for none.
     * @param int         $ttl           Cache lifetime in seconds.
     * @return bool True on success.
     */
    public function set_cooldown(int $rating_id, int $user_id, ?string $user_ip, ?int $context_id, ?string $context_type, ?string $last_vote_time, int $ttl): bool;
}
