<?php
/**
 * Shuriken Reviews Rate Limiter
 *
 * Handles vote rate limiting to prevent abuse and spam.
 * Supports cooldown between votes, hourly limits, and daily limits.
 *
 * @package Shuriken_Reviews
 * @since 1.10.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_Rate_Limiter
 *
 * Manages vote rate limiting for members and guests.
 *
 * @since 1.10.0
 */
class Shuriken_Rate_Limiter implements Shuriken_Rate_Limiter_Interface {

    /**
     * Default cooldown in seconds between votes on the same rating.
     */
    public const COOLDOWN_DEFAULT = 60;

    /**
     * Default hourly vote limit for guests.
     */
    public const GUEST_HOURLY_LIMIT_DEFAULT = 10;

    /**
     * Default hourly vote limit for members.
     */
    public const MEMBER_HOURLY_LIMIT_DEFAULT = 30;

    /**
     * Default daily vote limit for guests.
     */
    public const GUEST_DAILY_LIMIT_DEFAULT = 30;

    /**
     * Default daily vote limit for members.
     */
    public const MEMBER_DAILY_LIMIT_DEFAULT = 100;

    /**
     * @var Shuriken_Vote_Repository Vote repository
     */
    private Shuriken_Vote_Repository $db;

    /**
     * Constructor
     *
     * @param Shuriken_Vote_Repository $db Vote repository.
     */
    public function __construct(Shuriken_Vote_Repository $db) {
        $this->db = $db;
    }

    /**
     * Check if a user can submit a vote
     *
     * @param int         $user_id      User ID (0 for guests).
     * @param string|null $user_ip      User IP address (required for guests).
     * @param int         $rating_id    Rating ID being voted on.
     * @param int|null    $context_id   Optional post/entity ID for contextual votes.
     * @param string|null $context_type Optional context type ('post', 'product', etc.).
     * @return bool True if vote is allowed.
     * @throws Shuriken_Rate_Limit_Exception If any limit is exceeded.
     */
    public function can_vote(int $user_id, ?string $user_ip, int $rating_id, ?int $context_id = null, ?string $context_type = null): bool {
        /**
         * Fires before rate limit check is performed.
         *
         * @since 1.10.0
         * @param int         $user_id   User ID (0 for guests).
         * @param string|null $user_ip   User IP address.
         * @param int         $rating_id Rating ID being voted on.
         */
        do_action('shuriken_before_rate_limit_check', $user_id, $user_ip, $rating_id);

        // Check if rate limiting is enabled
        $limits = $this->get_limits($user_id);
        
        if (!$limits['enabled']) {
            return true;
        }

        // Check if user bypasses rate limiting
        if ($this->should_bypass($user_id, $user_ip)) {
            return true;
        }

        // Check cooldown for this specific rating
        if ($limits['cooldown'] > 0) {
            $cooldown_remaining = $this->get_cooldown_remaining($user_id, $user_ip, $rating_id, $context_id, $context_type);
            
            if ($cooldown_remaining > 0) {
                $this->fire_exceeded_action('cooldown', $user_id, $user_ip, $cooldown_remaining);
                throw Shuriken_Rate_Limit_Exception::vote_cooldown($cooldown_remaining);
            }
        }

        // Get current usage
        $usage = $this->get_usage($user_id, $user_ip);

        // Check hourly limit
        if ($limits['hourly_limit'] > 0 && $usage['hourly_votes'] >= $limits['hourly_limit']) {
            $retry_after = $this->get_time_until_hourly_reset($user_id, $user_ip);
            $this->fire_exceeded_action('hourly', $user_id, $user_ip, $retry_after);
            throw Shuriken_Rate_Limit_Exception::hourly_vote_limit($limits['hourly_limit']);
        }

        // Check daily limit
        if ($limits['daily_limit'] > 0 && $usage['daily_votes'] >= $limits['daily_limit']) {
            $retry_after = $this->get_time_until_daily_reset($user_id, $user_ip);
            $this->fire_exceeded_action('daily', $user_id, $user_ip, $retry_after);
            throw Shuriken_Rate_Limit_Exception::daily_vote_limit($limits['daily_limit']);
        }

        /**
         * Filter the final rate limit check result.
         *
         * Return false or a WP_Error to block the vote.
         *
         * @since 1.10.0
         * @param bool        $can_vote  Whether the vote is allowed. Default true.
         * @param int         $user_id   User ID (0 for guests).
         * @param string|null $user_ip   User IP address.
         * @param int         $rating_id Rating ID being voted on.
         * @param array       $limits    Current rate limit settings.
         * @param array       $usage     Current usage statistics.
         */
        $result = apply_filters('shuriken_rate_limit_check_result', true, $user_id, $user_ip, $rating_id, $limits, $usage);

        if (is_wp_error($result)) {
            throw Shuriken_Rate_Limit_Exception::custom_blocked($result->get_error_message());
        }

        if ($result === false) {
            throw Shuriken_Rate_Limit_Exception::custom_blocked();
        }

        return true;
    }

    /**
     * Get the current rate limit settings
     *
     * @param int $user_id User ID (0 for guests).
     * @return array Rate limit settings.
     */
    public function get_limits(int $user_id): array {
        $is_guest = ($user_id === 0);

        $settings = array(
            'enabled'      => get_option('shuriken_rate_limiting_enabled', '0') === '1',
            'cooldown'     => (int) get_option('shuriken_vote_cooldown', self::COOLDOWN_DEFAULT),
            'hourly_limit' => $is_guest 
                ? (int) get_option('shuriken_guest_hourly_limit', self::GUEST_HOURLY_LIMIT_DEFAULT)
                : (int) get_option('shuriken_hourly_vote_limit', self::MEMBER_HOURLY_LIMIT_DEFAULT),
            'daily_limit'  => $is_guest
                ? (int) get_option('shuriken_guest_daily_limit', self::GUEST_DAILY_LIMIT_DEFAULT)
                : (int) get_option('shuriken_daily_vote_limit', self::MEMBER_DAILY_LIMIT_DEFAULT),
        );

        /**
         * Filter the rate limit settings.
         *
         * Allows programmatic adjustment of rate limits.
         *
         * @since 1.10.0
         * @param array $settings {
         *     Rate limit settings.
         *
         *     @type bool $enabled      Whether rate limiting is enabled.
         *     @type int  $cooldown     Seconds between votes on same item.
         *     @type int  $hourly_limit Maximum votes per hour.
         *     @type int  $daily_limit  Maximum votes per day.
         * }
         * @param int  $user_id  User ID (0 for guests).
         * @param bool $is_guest Whether the user is a guest.
         */
        return apply_filters('shuriken_rate_limit_settings', $settings, $user_id, $is_guest);
    }

    /**
     * Get current usage for a user/guest
     *
     * @param int         $user_id User ID (0 for guests).
     * @param string|null $user_ip User IP address (required for guests).
     * @return array Current usage statistics.
     */
    public function get_usage(int $user_id, ?string $user_ip): array {
        $now = current_time('mysql');
        $hour_ago = gmdate('Y-m-d H:i:s', strtotime($now) - HOUR_IN_SECONDS);
        $day_ago = gmdate('Y-m-d H:i:s', strtotime($now) - DAY_IN_SECONDS);

        return array(
            'hourly_votes' => $this->db->count_votes_since($user_id, $user_ip, $hour_ago),
            'daily_votes'  => $this->db->count_votes_since($user_id, $user_ip, $day_ago),
        );
    }

    /**
     * Get remaining cooldown time for a specific rating
     *
     * @param int         $user_id      User ID (0 for guests).
     * @param string|null $user_ip      User IP address (required for guests).
     * @param int         $rating_id    Rating ID.
     * @param int|null    $context_id   Optional post/entity ID for contextual votes.
     * @param string|null $context_type Optional context type.
     * @return int Seconds remaining until user can vote again.
     */
    public function get_cooldown_remaining(int $user_id, ?string $user_ip, int $rating_id, ?int $context_id = null, ?string $context_type = null): int {
        $limits = $this->get_limits($user_id);
        
        if ($limits['cooldown'] <= 0) {
            return 0;
        }

        $last_vote_time = $this->db->get_last_vote_time($rating_id, $user_id, $user_ip, $context_id, $context_type);
        
        if (!$last_vote_time) {
            return 0;
        }

        $last_vote_timestamp = strtotime($last_vote_time);
        $cooldown_ends = $last_vote_timestamp + $limits['cooldown'];
        $now = strtotime(current_time('mysql'));

        if ($now >= $cooldown_ends) {
            return 0;
        }

        return $cooldown_ends - $now;
    }

    /**
     * Check if a user bypasses rate limiting
     *
     * @param int         $user_id User ID (0 for guests).
     * @param string|null $user_ip User IP address.
     * @return bool True if user bypasses rate limiting.
     */
    public function should_bypass(int $user_id, ?string $user_ip): bool {
        // Guests never bypass by default
        if ($user_id === 0) {
            $bypass = false;
        } else {
            // Administrators bypass by default
            $bypass = user_can($user_id, 'manage_options');
        }

        /**
         * Filter whether to bypass rate limiting for a user.
         *
         * @since 1.10.0
         * @param bool        $bypass  Whether to bypass rate limiting. Default: true for admins.
         * @param int         $user_id User ID (0 for guests).
         * @param string|null $user_ip User IP address.
         */
        return apply_filters('shuriken_bypass_rate_limit', $bypass, $user_id, $user_ip);
    }

    /**
     * Fire the rate limit exceeded action
     *
     * @param string      $type        Type of limit exceeded (cooldown, hourly, daily).
     * @param int         $user_id     User ID (0 for guests).
     * @param string|null $user_ip     User IP address.
     * @param int         $retry_after Seconds until limit resets.
     * @return void
     */
    private function fire_exceeded_action(string $type, int $user_id, ?string $user_ip, int $retry_after): void {
        /**
         * Fires when a rate limit is exceeded.
         *
         * Useful for logging, analytics, or notifications.
         *
         * @since 1.10.0
         * @param string      $type        Type of limit exceeded (cooldown, hourly, daily).
         * @param int         $user_id     User ID (0 for guests).
         * @param string|null $user_ip     User IP address.
         * @param int         $retry_after Seconds until limit resets.
         */
        do_action('shuriken_rate_limit_exceeded', $type, $user_id, $user_ip, $retry_after);
    }

    /**
     * Get seconds until hourly limit resets
     *
     * @param int         $user_id User ID (0 for guests).
     * @param string|null $user_ip User IP address.
     * @return int Seconds until reset.
     */
    private function get_time_until_hourly_reset(int $user_id, ?string $user_ip): int {
        $oldest_vote_in_window = $this->db->get_oldest_vote_in_window($user_id, $user_ip, HOUR_IN_SECONDS);
        
        if (!$oldest_vote_in_window) {
            return HOUR_IN_SECONDS;
        }

        $vote_timestamp = strtotime($oldest_vote_in_window);
        $reset_time = $vote_timestamp + HOUR_IN_SECONDS;
        $now = strtotime(current_time('mysql'));

        return max(0, $reset_time - $now);
    }

    /**
     * Get seconds until daily limit resets
     *
     * @param int         $user_id User ID (0 for guests).
     * @param string|null $user_ip User IP address.
     * @return int Seconds until reset.
     */
    private function get_time_until_daily_reset(int $user_id, ?string $user_ip): int {
        $oldest_vote_in_window = $this->db->get_oldest_vote_in_window($user_id, $user_ip, DAY_IN_SECONDS);
        
        if (!$oldest_vote_in_window) {
            return DAY_IN_SECONDS;
        }

        $vote_timestamp = strtotime($oldest_vote_in_window);
        $reset_time = $vote_timestamp + DAY_IN_SECONDS;
        $now = strtotime(current_time('mysql'));

        return max(0, $reset_time - $now);
    }
}

/**
 * Helper function to get rate limiter instance
 *
 * @return Shuriken_Rate_Limiter_Interface
 */
function shuriken_rate_limiter(): Shuriken_Rate_Limiter_Interface {
    return shuriken_container()->get('rate_limiter');
}
