<?php
/**
 * Configuration Exception for Shuriken Reviews
 *
 * Thrown when plugin configuration or settings are invalid.
 *
 * @package Shuriken_Reviews
 * @since 1.7.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_Configuration_Exception
 *
 * Exception for configuration and settings errors.
 *
 * @since 1.7.0
 */
class Shuriken_Configuration_Exception extends Shuriken_Exception {

    /**
     * @var string The configuration key that caused the error
     */
    protected $config_key;

    /**
     * @var mixed The invalid configuration value
     */
    protected $config_value;

    /**
     * Constructor
     *
     * @param string         $message      Error message.
     * @param string         $config_key   Configuration key that caused the error.
     * @param mixed          $config_value The invalid configuration value.
     * @param Throwable|null $previous     Previous exception.
     */
    public function __construct($message = '', $config_key = '', $config_value = null, $previous = null) {
        $this->config_key = $config_key;
        $this->config_value = $config_value;
        $error_code = 'config_' . sanitize_key($config_key) . '_invalid';
        parent::__construct($message, $error_code, 0, $previous);
    }

    /**
     * Get the configuration key
     *
     * @return string
     */
    public function get_config_key() {
        return $this->config_key;
    }

    /**
     * Get the invalid configuration value
     *
     * @return mixed
     */
    public function get_config_value() {
        return $this->config_value;
    }

    /**
     * Create exception for invalid option value
     *
     * @param string $option   Option name.
     * @param mixed  $value    Invalid value.
     * @param string $expected Expected format/type.
     * @return self
     */
    public static function invalid_option($option, $value = null, $expected = '') {
        $message = sprintf(__('Invalid value for option "%s"', 'shuriken-reviews'), $option);
        if (!empty($expected)) {
            $message .= sprintf(__('. Expected: %s', 'shuriken-reviews'), $expected);
        }
        return new self($message, $option, $value);
    }

    /**
     * Create exception for missing required option
     *
     * @param string $option Option name.
     * @return self
     */
    public static function missing_option($option) {
        return new self(
            sprintf(__('Required option "%s" is not configured', 'shuriken-reviews'), $option),
            $option,
            null
        );
    }

    /**
     * Create exception for invalid max stars configuration
     *
     * Typical values are 1-10, but any value >= 1 is supported.
     * This is a recommendation, not a hard limit.
     *
     * @param mixed $value Invalid max stars value.
     * @return self
     */
    public static function invalid_max_stars($value) {
        return new self(
            sprintf(__('Invalid max stars value: %s. Should be at least 1 (typically 1-10).', 'shuriken-reviews'), $value),
            'max_stars',
            $value
        );
    }

    /**
     * Create exception for invalid effect type
     *
     * @param mixed $value Invalid effect type value.
     * @return self
     */
    public static function invalid_effect_type($value) {
        return new self(
            sprintf(__('Invalid effect type: %s. Must be "positive" or "negative".', 'shuriken-reviews'), $value),
            'effect_type',
            $value
        );
    }

    /**
     * Create exception for invalid database table configuration
     *
     * @param string $table Table name.
     * @return self
     */
    public static function invalid_table($table) {
        return new self(
            sprintf(__('Database table "%s" does not exist or is not accessible', 'shuriken-reviews'), $table),
            'database_table',
            $table
        );
    }

    /**
     * Create exception for conflicting options
     *
     * @param string $option1 First option name.
     * @param string $option2 Second option name.
     * @return self
     */
    public static function conflicting_options($option1, $option2) {
        return new self(
            sprintf(__('Options "%s" and "%s" cannot be used together', 'shuriken-reviews'), $option1, $option2),
            'conflicting_options',
            array($option1, $option2)
        );
    }
}
