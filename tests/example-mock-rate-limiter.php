<?php
/**
 * Mock Rate Limiter for Testing
 *
 * Provides a controllable rate limiter implementation for unit tests.
 * Allows setting up specific scenarios without actual database queries.
 *
 * @package Shuriken_Reviews
 * @since 1.10.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Mock_Rate_Limiter
 *
 * Mock implementation of Shuriken_Rate_Limiter_Interface for testing.
 *
 * Usage example:
 * ```php
 * $mock_limiter = new Mock_Rate_Limiter();
 * 
 * // Configure to allow all votes
 * $mock_limiter->set_can_vote(true);
 * 
 * // Or configure to block with specific exception
 * $mock_limiter->set_exception(
 *     Shuriken_Rate_Limit_Exception::daily_vote_limit(100)
 * );
 * 
 * // Inject into container
 * shuriken_container()->set('rate_limiter', $mock_limiter);
 * ```
 *
 * @since 1.10.0
 */
class Mock_Rate_Limiter implements Shuriken_Rate_Limiter_Interface {

    /**
     * @var bool Whether votes are allowed
     */
    private $can_vote_result = true;

    /**
     * @var Shuriken_Rate_Limit_Exception|null Exception to throw
     */
    private $exception_to_throw = null;

    /**
     * @var array Rate limit settings
     */
    private $limits = array(
        'enabled'      => false,
        'cooldown'     => 60,
        'hourly_limit' => 30,
        'daily_limit'  => 100,
    );

    /**
     * @var array Usage statistics
     */
    private $usage = array(
        'hourly_votes' => 0,
        'daily_votes'  => 0,
    );

    /**
     * @var int Cooldown remaining
     */
    private $cooldown_remaining = 0;

    /**
     * @var bool Whether user bypasses limits
     */
    private $should_bypass_result = false;

    /**
     * @var array Log of method calls for verification
     */
    private $call_log = array();

    /**
     * Check if a user can submit a vote
     *
     * @param int         $user_id   User ID.
     * @param string|null $user_ip   User IP.
     * @param int         $rating_id Rating ID.
     * @return bool
     * @throws Shuriken_Rate_Limit_Exception If configured to throw.
     */
    public function can_vote($user_id, $user_ip, $rating_id) {
        $this->log_call('can_vote', compact('user_id', 'user_ip', 'rating_id'));

        if ($this->exception_to_throw !== null) {
            throw $this->exception_to_throw;
        }

        return $this->can_vote_result;
    }

    /**
     * Get rate limit settings
     *
     * @param int $user_id User ID.
     * @return array
     */
    public function get_limits($user_id) {
        $this->log_call('get_limits', compact('user_id'));
        return $this->limits;
    }

    /**
     * Get current usage
     *
     * @param int         $user_id User ID.
     * @param string|null $user_ip User IP.
     * @return array
     */
    public function get_usage($user_id, $user_ip) {
        $this->log_call('get_usage', compact('user_id', 'user_ip'));
        return $this->usage;
    }

    /**
     * Get cooldown remaining
     *
     * @param int         $user_id   User ID.
     * @param string|null $user_ip   User IP.
     * @param int         $rating_id Rating ID.
     * @return int
     */
    public function get_cooldown_remaining($user_id, $user_ip, $rating_id) {
        $this->log_call('get_cooldown_remaining', compact('user_id', 'user_ip', 'rating_id'));
        return $this->cooldown_remaining;
    }

    /**
     * Check if user bypasses limits
     *
     * @param int         $user_id User ID.
     * @param string|null $user_ip User IP.
     * @return bool
     */
    public function should_bypass($user_id, $user_ip) {
        $this->log_call('should_bypass', compact('user_id', 'user_ip'));
        return $this->should_bypass_result;
    }

    // =========================================================================
    // Test Setup Methods
    // =========================================================================

    /**
     * Set whether can_vote() returns true or false
     *
     * @param bool $result Result to return.
     * @return self
     */
    public function set_can_vote($result) {
        $this->can_vote_result = (bool) $result;
        $this->exception_to_throw = null;
        return $this;
    }

    /**
     * Set an exception to throw from can_vote()
     *
     * @param Shuriken_Rate_Limit_Exception $exception Exception to throw.
     * @return self
     */
    public function set_exception(Shuriken_Rate_Limit_Exception $exception) {
        $this->exception_to_throw = $exception;
        return $this;
    }

    /**
     * Set rate limit settings
     *
     * @param array $limits Limit settings.
     * @return self
     */
    public function set_limits($limits) {
        $this->limits = array_merge($this->limits, $limits);
        return $this;
    }

    /**
     * Set usage statistics
     *
     * @param array $usage Usage stats.
     * @return self
     */
    public function set_usage($usage) {
        $this->usage = array_merge($this->usage, $usage);
        return $this;
    }

    /**
     * Set cooldown remaining
     *
     * @param int $seconds Seconds remaining.
     * @return self
     */
    public function set_cooldown_remaining($seconds) {
        $this->cooldown_remaining = (int) $seconds;
        return $this;
    }

    /**
     * Set whether user bypasses limits
     *
     * @param bool $bypass Whether to bypass.
     * @return self
     */
    public function set_should_bypass($bypass) {
        $this->should_bypass_result = (bool) $bypass;
        return $this;
    }

    // =========================================================================
    // Test Verification Methods
    // =========================================================================

    /**
     * Get the call log
     *
     * @return array
     */
    public function get_call_log() {
        return $this->call_log;
    }

    /**
     * Check if a method was called
     *
     * @param string $method Method name.
     * @return bool
     */
    public function was_called($method) {
        foreach ($this->call_log as $entry) {
            if ($entry['method'] === $method) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get call count for a method
     *
     * @param string $method Method name.
     * @return int
     */
    public function get_call_count($method) {
        $count = 0;
        foreach ($this->call_log as $entry) {
            if ($entry['method'] === $method) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Reset the mock state
     *
     * @return self
     */
    public function reset() {
        $this->can_vote_result = true;
        $this->exception_to_throw = null;
        $this->limits = array(
            'enabled'      => false,
            'cooldown'     => 60,
            'hourly_limit' => 30,
            'daily_limit'  => 100,
        );
        $this->usage = array(
            'hourly_votes' => 0,
            'daily_votes'  => 0,
        );
        $this->cooldown_remaining = 0;
        $this->should_bypass_result = false;
        $this->call_log = array();
        return $this;
    }

    /**
     * Log a method call
     *
     * @param string $method Method name.
     * @param array  $args   Arguments passed.
     * @return void
     */
    private function log_call($method, $args) {
        $this->call_log[] = array(
            'method' => $method,
            'args'   => $args,
            'time'   => microtime(true),
        );
    }
}
