<?php
/**
 * Rate Limit Exception for Shuriken Reviews
 *
 * TODO: This exception is reserved for future rate limiting features.
 * Currently, there is no rate limiting implementation in the plugin.
 * It will be used when the following features are added:
 * - Vote cooldown/throttling
 * - Daily/hourly vote limits per user
 * - API rate limiting
 *
 * Thrown when a user exceeds rate limits for voting or other actions.
 *
 * @package Shuriken_Reviews
 * @since 1.7.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_Rate_Limit_Exception
 *
 * Exception for rate limiting and throttling.
 *
 * @since 1.7.0
 */
class Shuriken_Rate_Limit_Exception extends Shuriken_Exception {

    /**
     * @var int Seconds until the limit resets
     */
    protected $retry_after;

    /**
     * @var int The rate limit that was exceeded
     */
    protected $limit;

    /**
     * @var string The action that was rate limited
     */
    protected $action;

    /**
     * Constructor
     *
     * @param string         $message     Error message.
     * @param string         $action      The action being rate limited.
     * @param int            $retry_after Seconds until limit resets.
     * @param int            $limit       The rate limit.
     * @param Throwable|null $previous    Previous exception.
     */
    public function __construct($message = '', $action = '', $retry_after = 0, $limit = 0, $previous = null) {
        $this->action = $action;
        $this->retry_after = $retry_after;
        $this->limit = $limit;
        $error_code = 'rate_limit_' . sanitize_key($action);
        parent::__construct($message, $error_code, 429, $previous);
    }

    /**
     * Get seconds until the limit resets
     *
     * @return int
     */
    public function get_retry_after() {
        return $this->retry_after;
    }

    /**
     * Get the rate limit
     *
     * @return int
     */
    public function get_limit() {
        return $this->limit;
    }

    /**
     * Get the action that was rate limited
     *
     * @return string
     */
    public function get_action() {
        return $this->action;
    }

    /**
     * Create exception for vote rate limit exceeded
     *
     * TODO: Implement vote throttling/cooldown feature in database and AJAX handler
     *
     * @param int $retry_after Seconds until user can vote again.
     * @param int $limit       Maximum votes allowed in the time period.
     * @return self
     */
    public static function voting_too_fast($retry_after = 60, $limit = 0) {
        $message = __('You are voting too quickly. Please wait before submitting another vote.', 'shuriken-reviews');
        if ($retry_after > 0) {
            $message = sprintf(
                __('You are voting too quickly. Please wait %d seconds before submitting another vote.', 'shuriken-reviews'),
                $retry_after
            );
        }
        return new self($message, 'voting', $retry_after, $limit);
    }

    /**
     * Create exception for daily vote limit exceeded
     *
     * TODO: Implement daily vote limit tracking per user
     *
     * @param int $limit Maximum votes per day.
     * @return self
     */
    public static function daily_vote_limit($limit) {
        return new self(
            sprintf(__('You have reached the daily limit of %d votes. Please try again tomorrow.', 'shuriken-reviews'), $limit),
            'daily_votes',
            86400, // 24 hours
            $limit
        );
    }

    /**
     * Create exception for hourly vote limit exceeded
     *
     * TODO: Implement hourly vote limit tracking per user
     *
     * @param int $limit Maximum votes per hour.
     * @return self
     */
    public static function hourly_vote_limit($limit) {
        return new self(
            sprintf(__('You have reached the hourly limit of %d votes. Please try again later.', 'shuriken-reviews'), $limit),
            'hourly_votes',
            3600, // 1 hour
            $limit
        );
    }

    /**
     * Create exception for per-item vote cooldown
     *
     * TODO: Implement vote cooldown tracking per user/rating combination
     *
     * @param int $retry_after Seconds until user can vote on this item again.
     * @return self
     */
    public static function vote_cooldown($retry_after) {
        return new self(
            sprintf(__('You can change your vote on this item in %d seconds.', 'shuriken-reviews'), $retry_after),
            'vote_cooldown',
            $retry_after,
            1
        );
    }

    /**
     * Create exception for API rate limit exceeded
     *
     * @param int $retry_after Seconds until limit resets.
     * @param int $limit       Requests per time period.
     * @return self
     */
    public static function api_limit_exceeded($retry_after = 60, $limit = 100) {
        return new self(
            sprintf(__('API rate limit exceeded. Please wait %d seconds.', 'shuriken-reviews'), $retry_after),
            'api',
            $retry_after,
            $limit
        );
    }

    /**
     * Create exception for too many failed attempts
     *
     * @param string $action      The action with failed attempts.
     * @param int    $retry_after Seconds until user can try again.
     * @return self
     */
    public static function too_many_failures($action, $retry_after = 300) {
        return new self(
            sprintf(__('Too many failed attempts. Please wait %d seconds before trying again.', 'shuriken-reviews'), $retry_after),
            $action . '_failures',
            $retry_after,
            0
        );
    }
}
