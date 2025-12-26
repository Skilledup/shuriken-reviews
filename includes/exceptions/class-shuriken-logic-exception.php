<?php
/**
 * Logic Exception for Shuriken Reviews
 *
 * Thrown when business logic rules are violated.
 *
 * @package Shuriken_Reviews
 * @since 1.7.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_Logic_Exception
 *
 * Exception for business logic violations.
 *
 * @since 1.7.0
 */
class Shuriken_Logic_Exception extends Shuriken_Exception {

    /**
     * Constructor
     *
     * @param string         $message    Error message.
     * @param string         $error_code Error code.
     * @param Throwable|null $previous   Previous exception.
     */
    public function __construct($message = '', $error_code = 'logic_error', $previous = null) {
        parent::__construct($message, $error_code, 0, $previous);
    }

    /**
     * Create exception for display-only rating
     *
     * @return self
     */
    public static function display_only_rating() {
        return new self(
            __('This rating is display-only and cannot be voted on directly', 'shuriken-reviews'),
            'display_only_rating'
        );
    }

    /**
     * Create exception for circular reference
     *
     * @return self
     */
    public static function circular_reference() {
        return new self(
            __('Cannot create circular reference between ratings', 'shuriken-reviews'),
            'circular_reference'
        );
    }

    /**
     * Create exception for invalid parent
     *
     * @return self
     */
    public static function invalid_parent() {
        return new self(
            __('Cannot set a mirror or display-only rating as parent', 'shuriken-reviews'),
            'invalid_parent'
        );
    }

    /**
     * Create exception for invalid mirror target
     *
     * @return self
     */
    public static function invalid_mirror_target() {
        return new self(
            __('Cannot mirror a rating that is itself a mirror', 'shuriken-reviews'),
            'invalid_mirror_target'
        );
    }

    /**
     * Create exception for duplicate vote
     *
     * @return self
     */
    public static function duplicate_vote() {
        return new self(
            __('You have already voted on this rating', 'shuriken-reviews'),
            'duplicate_vote'
        );
    }

    /**
     * Create exception for vote limit reached
     *
     * @param int $limit Vote limit.
     * @return self
     */
    public static function vote_limit_reached($limit) {
        return new self(
            sprintf(__('You have reached your voting limit of %d votes', 'shuriken-reviews'), $limit),
            'vote_limit_reached'
        );
    }
}

