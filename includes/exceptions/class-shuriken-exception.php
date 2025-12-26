<?php
/**
 * Base Exception for Shuriken Reviews
 *
 * All custom exceptions in the plugin extend this base exception.
 *
 * @package Shuriken_Reviews
 * @since 1.7.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_Exception
 *
 * Base exception class for the plugin.
 *
 * @since 1.7.0
 */
class Shuriken_Exception extends Exception {
    
    /**
     * @var string Error code for logging/debugging
     */
    protected $error_code;

    /**
     * Constructor
     *
     * @param string         $message    Error message.
     * @param string         $error_code Error code.
     * @param int            $code       Error code number.
     * @param Throwable|null $previous   Previous exception.
     */
    public function __construct($message = '', $error_code = 'shuriken_error', $code = 0, $previous = null) {
        $this->error_code = $error_code;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the error code
     *
     * @return string
     */
    public function get_error_code() {
        return $this->error_code;
    }

    /**
     * Convert to WP_Error for WordPress compatibility
     *
     * @return WP_Error
     */
    public function to_wp_error() {
        return new WP_Error($this->error_code, $this->getMessage(), array('exception' => get_class($this)));
    }

    /**
     * Log the exception
     *
     * @param string $context Additional context for logging.
     * @return void
     */
    public function log($context = '') {
        $message = sprintf(
            '[Shuriken Reviews] %s: %s (Code: %s)',
            get_class($this),
            $this->getMessage(),
            $this->error_code
        );

        if (!empty($context)) {
            $message .= ' | Context: ' . $context;
        }

        error_log($message);
    }
}

