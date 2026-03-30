<?php
/**
 * Exception Interface for Shuriken Reviews
 *
 * All plugin exceptions implement this interface, enabling unified catch
 * blocks regardless of which SPL exception class they extend.
 *
 * @package Shuriken_Reviews
 * @since 1.8.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface Shuriken_Exception_Interface
 *
 * Contract for all Shuriken Reviews exceptions.
 * Extends Throwable so it can be used in catch blocks directly.
 *
 * @since 1.8.0
 */
interface Shuriken_Exception_Interface extends Throwable {

    /**
     * Get the plugin-specific error code
     *
     * @return string
     */
    public function get_error_code(): string;

    /**
     * Convert to WP_Error for WordPress compatibility
     *
     * @return WP_Error
     */
    public function to_wp_error(): WP_Error;

    /**
     * Log the exception
     *
     * @param string $context Additional context for logging.
     * @return void
     */
    public function log(string $context = ''): void;
}
