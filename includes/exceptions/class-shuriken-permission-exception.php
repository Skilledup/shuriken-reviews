<?php
/**
 * Permission Exception for Shuriken Reviews
 *
 * Thrown when user doesn't have permission for an action.
 *
 * @package Shuriken_Reviews
 * @since 1.7.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_Permission_Exception
 *
 * Exception for permission/authorization failures.
 *
 * @since 1.7.0
 */
class Shuriken_Permission_Exception extends Shuriken_Exception {

    /**
     * @var string Required capability or permission
     */
    protected $required_permission;

    /**
     * Constructor
     *
     * @param string         $message             Error message.
     * @param string         $required_permission Required permission.
     * @param Throwable|null $previous            Previous exception.
     */
    public function __construct($message = '', $required_permission = '', $previous = null) {
        $this->required_permission = $required_permission;
        $error_code = 'permission_denied';
        parent::__construct($message, $error_code, 403, $previous);
    }

    /**
     * Get the required permission
     *
     * @return string
     */
    public function get_required_permission() {
        return $this->required_permission;
    }

    /**
     * Create exception for unauthorized action
     *
     * @param string $action Action being attempted.
     * @return self
     */
    public static function unauthorized($action = '') {
        $message = __('You do not have permission to perform this action', 'shuriken-reviews');
        if (!empty($action)) {
            $message = sprintf(__('You do not have permission to %s', 'shuriken-reviews'), $action);
        }
        return new self($message, $action);
    }

    /**
     * Create exception for guests not allowed
     *
     * @return self
     */
    public static function guest_not_allowed() {
        return new self(
            __('You must be logged in to perform this action', 'shuriken-reviews'),
            'login_required'
        );
    }

    /**
     * Create exception for missing capability
     *
     * @param string $capability Required capability.
     * @return self
     */
    public static function missing_capability($capability) {
        return new self(
            sprintf(__('Required capability: %s', 'shuriken-reviews'), $capability),
            $capability
        );
    }

    /**
     * Create exception for voting not allowed
     *
     * @param string $reason Reason voting is not allowed.
     * @return self
     */
    public static function voting_not_allowed($reason = '') {
        $message = __('You are not allowed to vote', 'shuriken-reviews');
        if (!empty($reason)) {
            $message .= ': ' . $reason;
        }
        return new self($message, 'vote');
    }
}

