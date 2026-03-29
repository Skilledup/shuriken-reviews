<?php
/**
 * Exception Trait for Shuriken Reviews
 *
 * Provides shared exception behavior (error_code, logging, WP_Error conversion)
 * for all plugin exceptions regardless of which SPL class they extend.
 *
 * @package Shuriken_Reviews
 * @since 1.8.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Trait Shuriken_Exception_Trait
 *
 * Shared implementation for Shuriken_Exception_Interface.
 * Classes using this trait must also extend an Exception class.
 *
 * @since 1.8.0
 */
trait Shuriken_Exception_Trait {

    /**
     * @var string Error code for logging/debugging
     */
    protected $error_code;

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
