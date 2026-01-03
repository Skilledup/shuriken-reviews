<?php
/**
 * Integration Exception for Shuriken Reviews
 *
 * TODO: Some exception factory methods are reserved for future features:
 * - webhook_failed() - for webhook delivery (not yet implemented)
 * - cache_failed() - for cache operations (not yet implemented)
 * - email_failed() - for email notifications (not yet implemented)
 * - plugin_dependency_missing() - for plugin checks (partially used)
 * - plugin_version_incompatible() - for version checks (partially used)
 *
 * Currently implemented and used for:
 * - HTTP request failures
 * - API connection/auth failures
 * - Service timeout handling
 *
 * Thrown when external service integrations or third-party dependencies fail.
 *
 * @package Shuriken_Reviews
 * @since 1.7.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_Integration_Exception
 *
 * Exception for external service and integration failures.
 *
 * @since 1.7.0
 */
class Shuriken_Integration_Exception extends Shuriken_Exception {

    /**
     * @var string The service that failed
     */
    protected $service;

    /**
     * @var array Additional context about the failure
     */
    protected $context;

    /**
     * Constructor
     *
     * @param string         $message  Error message.
     * @param string         $service  The service that failed.
     * @param array          $context  Additional context.
     * @param Throwable|null $previous Previous exception.
     */
    public function __construct($message = '', $service = '', $context = array(), $previous = null) {
        $this->service = $service;
        $this->context = $context;
        $error_code = 'integration_' . sanitize_key($service) . '_failed';
        parent::__construct($message, $error_code, 0, $previous);
    }

    /**
     * Get the service name
     *
     * @return string
     */
    public function get_service() {
        return $this->service;
    }

    /**
     * Get additional context
     *
     * @return array
     */
    public function get_context() {
        return $this->context;
    }

    /**
     * Create exception for HTTP request failure
     *
     * @param string $url        The URL that failed.
     * @param int    $status     HTTP status code.
     * @param string $error      Error message from the request.
     * @return self
     */
    public static function http_request_failed($url, $status = 0, $error = '') {
        $message = __('External HTTP request failed', 'shuriken-reviews');
        if (!empty($error)) {
            $message .= ': ' . $error;
        }
        return new self($message, 'http', array(
            'url'    => $url,
            'status' => $status,
            'error'  => $error,
        ));
    }

    /**
     * Create exception for API connection failure
     *
     * @param string $api_name Name of the API.
     * @param string $error    Error message.
     * @return self
     */
    public static function api_connection_failed($api_name, $error = '') {
        $message = sprintf(__('Failed to connect to %s API', 'shuriken-reviews'), $api_name);
        if (!empty($error)) {
            $message .= ': ' . $error;
        }
        return new self($message, $api_name, array('error' => $error));
    }

    /**
     * Create exception for API authentication failure
     *
     * @param string $api_name Name of the API.
     * @return self
     */
    public static function api_auth_failed($api_name) {
        return new self(
            sprintf(__('Authentication failed for %s API. Please check your credentials.', 'shuriken-reviews'), $api_name),
            $api_name,
            array('type' => 'authentication')
        );
    }

    /**
     * Create exception for webhook delivery failure
     *
     * TODO: Implement webhook delivery feature (not yet implemented)
     *
     * @param string $webhook_url The webhook URL.
     * @param string $error       Error message.
     * @return self
     */
    public static function webhook_failed($webhook_url, $error = '') {
        $message = __('Failed to deliver webhook', 'shuriken-reviews');
        if (!empty($error)) {
            $message .= ': ' . $error;
        }
        return new self($message, 'webhook', array(
            'url'   => $webhook_url,
            'error' => $error,
        ));
    }

    /**
     * Create exception for cache service failure
     *
     * TODO: Implement caching layer for ratings/votes (not yet implemented)
     *
     * @param string $operation The cache operation that failed.
     * @param string $error     Error message.
     * @return self
     */
    public static function cache_failed($operation, $error = '') {
        $message = sprintf(__('Cache %s operation failed', 'shuriken-reviews'), $operation);
        if (!empty($error)) {
            $message .= ': ' . $error;
        }
        return new self($message, 'cache', array(
            'operation' => $operation,
            'error'     => $error,
        ));
    }

    /**
     * Create exception for email service failure
     *
     * TODO: Implement email notification system (not yet implemented)
     *
     * @param string $error Error message.
     * @return self
     */
    public static function email_failed($error = '') {
        $message = __('Failed to send email notification', 'shuriken-reviews');
        if (!empty($error)) {
            $message .= ': ' . $error;
        }
        return new self($message, 'email', array('error' => $error));
    }

    /**
     * Create exception for plugin dependency not available
     *
     * TODO: Implement plugin dependency checking (reserved for extensions)
     *
     * @param string $plugin_name Name of the required plugin.
     * @return self
     */
    public static function plugin_dependency_missing($plugin_name) {
        return new self(
            sprintf(__('Required plugin "%s" is not installed or activated', 'shuriken-reviews'), $plugin_name),
            'plugin',
            array('plugin' => $plugin_name)
        );
    }

    /**
     * Create exception for incompatible plugin version
     *
     * TODO: Implement plugin version compatibility checking (reserved for extensions)
     *
     * @param string $plugin_name      Name of the plugin.
     * @param string $current_version  Current version.
     * @param string $required_version Required version.
     * @return self
     */
    public static function plugin_version_incompatible($plugin_name, $current_version, $required_version) {
        return new self(
            sprintf(
                __('Plugin "%s" version %s is incompatible. Version %s or higher is required.', 'shuriken-reviews'),
                $plugin_name,
                $current_version,
                $required_version
            ),
            'plugin',
            array(
                'plugin'           => $plugin_name,
                'current_version'  => $current_version,
                'required_version' => $required_version,
            )
        );
    }

    /**
     * Create exception for third-party service timeout
     *
     * @param string $service_name Name of the service.
     * @param int    $timeout      Timeout in seconds.
     * @return self
     */
    public static function service_timeout($service_name, $timeout = 30) {
        return new self(
            sprintf(__('%s service request timed out after %d seconds', 'shuriken-reviews'), $service_name, $timeout),
            $service_name,
            array('timeout' => $timeout)
        );
    }
}
