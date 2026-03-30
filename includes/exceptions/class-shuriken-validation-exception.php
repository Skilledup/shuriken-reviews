<?php
/**
 * Validation Exception for Shuriken Reviews
 *
 * Thrown when input validation fails.
 * Extends PHP's InvalidArgumentException for SPL interoperability.
 *
 * @package Shuriken_Reviews
 * @since 1.7.0
 * @since 1.8.0 Extends \InvalidArgumentException instead of Shuriken_Exception
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
 * @since 1.8.0 Extends \InvalidArgumentException, implements Shuriken_Exception_Interface
 */
class Shuriken_Validation_Exception extends InvalidArgumentException implements Shuriken_Exception_Interface {

    use Shuriken_Exception_Trait;

    /**
     * @var string Field that failed validation
     */
    protected string $field;

    /**
     * @var mixed Invalid value
     */
    protected mixed $invalid_value;

    /**
     * Constructor
     *
     * @param string         $message       Error message.
     * @param string         $field         Field that failed validation.
     * @param mixed          $invalid_value The invalid value.
     * @param Throwable|null $previous      Previous exception.
     */
    public function __construct(string $message = '', string $field = '', mixed $invalid_value = null, ?\Throwable $previous = null) {
        $this->field = $field;
        $this->invalid_value = $invalid_value;
        $this->error_code = 'validation_' . $field . '_invalid';
        parent::__construct($message, 0, $previous);
    }

    /**
     * Get the field that failed validation
     *
     * @return string
     */
    public function get_field(): string {
        return $this->field;
    }

    /**
     * Get the invalid value
     *
     * @return mixed
     */
    public function get_invalid_value(): mixed {
        return $this->invalid_value;
    }

    /**
     * Create exception for required field
     *
     * @param string $field Field name.
     * @return self
     */
    public static function required_field(string $field): self {
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
    public static function invalid_value(string $field, mixed $value, string $expected = ''): self {
        $message = sprintf(__('Invalid value for %s', 'shuriken-reviews'), $field);
        if (!empty($expected)) {
            $message .= sprintf(__('. Expected: %s', 'shuriken-reviews'), $expected);
        }
        return new self($message, $field, $value);
    }

    /**
     * Create exception for out of range value
     *
     * @param string    $field Field name.
     * @param mixed     $value Invalid value.
     * @param int|float $min   Minimum allowed value.
     * @param int|float $max   Maximum allowed value.
     * @return self
     */
    public static function out_of_range(string $field, mixed $value, int|float $min, int|float $max): self {
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
     * @param float|int $value    Rating value.
     * @param int       $max_stars Maximum stars.
     * @return self
     */
    public static function invalid_rating_value(float|int $value, int $max_stars): self {
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

