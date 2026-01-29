<?php
/**
 * Shuriken Reviews Rate Limiter Interface
 *
 * Defines the contract for rate limiting operations.
 * This interface enables testability via mock implementations.
 *
 * @package Shuriken_Reviews
 * @since 1.10.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface Shuriken_Rate_Limiter_Interface
 *
 * Contract for rate limiting operations.
 *
 * @since 1.10.0
 */
interface Shuriken_Rate_Limiter_Interface {

    /**
     * Check if a user can submit a vote
     *
     * Verifies all rate limiting conditions (cooldown, hourly, daily).
     * Throws an appropriate exception if any limit is exceeded.
     *
     * @param int         $user_id   User ID (0 for guests).
     * @param string|null $user_ip   User IP address (required for guests).
     * @param int         $rating_id Rating ID being voted on.
     * @return bool True if vote is allowed.
     * @throws Shuriken_Rate_Limit_Exception If any limit is exceeded.
     */
    public function can_vote($user_id, $user_ip, $rating_id);

    /**
     * Get the current rate limit settings
     *
     * Returns filtered settings (can be modified via hooks).
     *
     * @param int $user_id User ID (0 for guests).
     * @return array {
     *     Rate limit settings.
     *
     *     @type bool $enabled      Whether rate limiting is enabled.
     *     @type int  $cooldown     Seconds between votes on same item.
     *     @type int  $hourly_limit Maximum votes per hour.
     *     @type int  $daily_limit  Maximum votes per day.
     * }
     */
    public function get_limits($user_id);

    /**
     * Get current usage for a user/guest
     *
     * @param int         $user_id User ID (0 for guests).
     * @param string|null $user_ip User IP address (required for guests).
     * @return array {
     *     Current usage statistics.
     *
     *     @type int $hourly_votes Number of votes in the last hour.
     *     @type int $daily_votes  Number of votes in the last 24 hours.
     * }
     */
    public function get_usage($user_id, $user_ip);

    /**
     * Get remaining cooldown time for a specific rating
     *
     * @param int         $user_id   User ID (0 for guests).
     * @param string|null $user_ip   User IP address (required for guests).
     * @param int         $rating_id Rating ID.
     * @return int Seconds remaining until user can vote again (0 if no cooldown).
     */
    public function get_cooldown_remaining($user_id, $user_ip, $rating_id);

    /**
     * Check if a user bypasses rate limiting
     *
     * Administrators bypass by default, extendable via filter.
     *
     * @param int         $user_id User ID (0 for guests).
     * @param string|null $user_ip User IP address.
     * @return bool True if user bypasses rate limiting.
     */
    public function should_bypass($user_id, $user_ip);
}
