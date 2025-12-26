<?php
/**
 * Validation Exception for Shuriken Reviews
 *
 * Thrown when input validation fails.
 *
 * @package Shuriken_Reviews
 * @since 1.7.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_Validation_Exception
 *
 * Exception for validation failures.
 *
 * @since 1.7.0
 */
class Shuriken_Validation_Exception extends Shuriken_Exception {

    /**
     * @var string Field that failed validation
     */
    protected $field;

    /**
     * @var mixed Invalid value
     */
    protected $invalid_value;

    /**
     * Constructor
     *
     * @param string         $message       Error message.
     * @param string         $field         Field that failed validation.
     * @param mixed          $invalid_value The invalid value.
     * @param Throwable|null $previous      Previous exception.
     */
    public function __construct($message = '', $field = '', $invalid_value = null, $previous = null) {
        $this->field = $field;
        $this->invalid_value = $invalid_value;
        $error_code = 'validation_' . $field . '_invalid';
        parent::__construct($message, $error_code, 0, $previous);
    }

    /**
     * Get the field that failed validation
     *
     * @return string
     */
    public function get_field() {
        return $this->field;
    }

    /**
     * Get the invalid value
     *
     * @return mixed
     */
    public function get_invalid_value() {
        return $this->invalid_value;
    }

    /**
     * Create exception for required field
     *
     * @param string $field Field name.
     * @return self
     */
    public static function required_field($field) {
        return new self(
            sprintf(__('The %s field is required', 'shuriken-reviews'), $field),
            $field,
            null
        );
    }

    /**
     * Create exception for invalid value
     *
     * @param string $field Field name.
     * @param mixed  $value Invalid value.
     * @param string $expected Expected format/type.
     * @return self
     */
    public static function invalid_value($field, $value, $expected = '') {
        $message = sprintf(__('Invalid value for %s', 'shuriken-reviews'), $field);
        if (!empty($expected)) {
            $message .= sprintf(__('. Expected: %s', 'shuriken-reviews'), $expected);
        }
        return new self($message, $field, $value);
    }

    /**
     * Create exception for out of range value
     *
     * @param string $field Field name.
     * @param mixed  $value Invalid value.
     * @param int    $min   Minimum allowed value.
     * @param int    $max   Maximum allowed value.
     * @return self
     */
    public static function out_of_range($field, $value, $min, $max) {
        return new self(
            sprintf(
                __('%s must be between %d and %d', 'shuriken-reviews'),
                $field,
                $min,
                $max
            ),
            $field,
            $value
        );
    }

    /**
     * Create exception for invalid rating value
     *
     * @param float $value    Rating value.
     * @param int   $max_stars Maximum stars.
     * @return self
     */
    public static function invalid_rating_value($value, $max_stars) {
        return new self(
            sprintf(
                __('Rating value must be between 1 and %d', 'shuriken-reviews'),
                $max_stars
            ),
            'rating_value',
            $value
        );
    }
}

