<?php
/**
 * Exception Handler for Shuriken Reviews
 *
 * Provides utilities for handling exceptions in a WordPress-friendly way.
 *
 * @package Shuriken_Reviews
 * @since 1.7.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_Exception_Handler
 *
 * Handles exceptions and converts them to appropriate WordPress responses.
 *
 * @since 1.7.0
 */
class Shuriken_Exception_Handler {

    /**
     * Handle an exception for AJAX requests
     *
     * Converts the exception to a JSON error response.
     *
     * @param Shuriken_Exception $exception The exception to handle.
     * @param bool               $die       Whether to die after sending response.
     * @return void
     */
    public static function handle_ajax_exception($exception, $die = true) {
        // Log the exception
        $exception->log('AJAX Request');

        // Convert to user-friendly message
        $message = self::get_user_message($exception);

        // Send JSON error
        wp_send_json_error($message);

        if ($die) {
            wp_die();
        }
    }

    /**
     * Handle an exception for REST API requests
     *
     * Converts the exception to a WP_Error.
     *
     * @param Shuriken_Exception $exception The exception to handle.
     * @return WP_Error
     */
    public static function handle_rest_exception($exception) {
        // Log the exception
        $exception->log('REST API Request');

        // Convert to WP_Error
        $wp_error = $exception->to_wp_error();

        // Add HTTP status code
        $status_code = self::get_http_status_code($exception);
        $wp_error->add_data(array('status' => $status_code));

        return $wp_error;
    }

    /**
     * Handle an exception for admin pages
     *
     * Adds an admin notice and logs the exception.
     *
     * @param Shuriken_Exception $exception The exception to handle.
     * @param string             $redirect_url Optional URL to redirect to.
     * @return void
     */
    public static function handle_admin_exception($exception, $redirect_url = '') {
        // Log the exception
        $exception->log('Admin Page');

        // Add admin notice
        $message = self::get_user_message($exception);
        add_settings_error(
            'shuriken_reviews',
            $exception->get_error_code(),
            $message,
            'error'
        );

        // Redirect if URL provided
        if (!empty($redirect_url)) {
            set_transient('shuriken_admin_error', $message, 30);
            wp_safe_redirect($redirect_url);
            exit;
        }
    }

    /**
     * Get user-friendly error message
     *
     * @param Shuriken_Exception $exception The exception.
     * @return string User-friendly message.
     */
    protected static function get_user_message($exception) {
        // Return the exception message directly
        // Could be customized based on exception type
        $message = $exception->getMessage();

        // Add helpful context for certain exceptions
        if ($exception instanceof Shuriken_Validation_Exception) {
            // Validation errors are already user-friendly
            return $message;
        }

        if ($exception instanceof Shuriken_Not_Found_Exception) {
            // Not found errors are already user-friendly
            return $message;
        }

        if ($exception instanceof Shuriken_Permission_Exception) {
            // Permission errors are already user-friendly
            return $message;
        }

        if ($exception instanceof Shuriken_Database_Exception) {
            // Database errors might be too technical for end users
            if (!defined('WP_DEBUG') || !WP_DEBUG) {
                return __('A database error occurred. Please try again.', 'shuriken-reviews');
            }
            return $message;
        }

        return $message;
    }

    /**
     * Get HTTP status code for exception
     *
     * @param Shuriken_Exception $exception The exception.
     * @return int HTTP status code.
     */
    protected static function get_http_status_code($exception) {
        if ($exception instanceof Shuriken_Not_Found_Exception) {
            return 404;
        }

        if ($exception instanceof Shuriken_Permission_Exception) {
            return 403;
        }

        if ($exception instanceof Shuriken_Validation_Exception) {
            return 400;
        }

        if ($exception instanceof Shuriken_Database_Exception) {
            return 500;
        }

        return 500;
    }

    /**
     * Safely execute a callable and handle any exceptions
     *
     * @param callable $callback Callback to execute.
     * @param string   $context  Context for error logging.
     * @param mixed    $default  Default value to return on exception.
     * @return mixed Result of callback or default value on exception.
     */
    public static function safe_execute($callback, $context = '', $default = null) {
        try {
            return call_user_func($callback);
        } catch (Shuriken_Exception $e) {
            $e->log($context);
            return $default;
        } catch (Exception $e) {
            error_log('[Shuriken Reviews] Unexpected error in ' . $context . ': ' . $e->getMessage());
            return $default;
        }
    }

    /**
     * Convert legacy return values to exceptions
     *
     * Helper for gradually migrating from return false to exceptions.
     *
     * @param mixed  $result   Result from legacy function.
     * @param string $operation Operation name.
     * @param string $message  Error message.
     * @throws Shuriken_Database_Exception If result is false.
     * @return mixed The result if not false.
     */
    public static function assert_not_false($result, $operation, $message = '') {
        if ($result === false) {
            if (empty($message)) {
                $message = sprintf(__('Operation failed: %s', 'shuriken-reviews'), $operation);
            }
            throw new Shuriken_Database_Exception($message, $operation);
        }
        return $result;
    }
}

