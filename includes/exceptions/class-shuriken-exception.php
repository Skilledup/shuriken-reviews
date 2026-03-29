<?php
/**
 * Base Runtime Exception for Shuriken Reviews
 *
 * Runtime-family exceptions extend this class:
 * Database, Permission, RateLimit, Integration, NotFound.
 *
 * Logic-family exceptions (Logic, Validation, Configuration) extend
 * their SPL counterparts directly and share behavior via the trait.
 *
 * All plugin exceptions implement Shuriken_Exception_Interface.
 *
 * @package Shuriken_Reviews
 * @since 1.7.0
 * @since 1.8.0 Extends RuntimeException, implements Shuriken_Exception_Interface
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_Exception
 *
 * Base runtime exception class for the plugin.
 *
 * @since 1.7.0
 * @since 1.8.0 Extends \RuntimeException instead of \Exception
 */
class Shuriken_Exception extends RuntimeException implements Shuriken_Exception_Interface {

    use Shuriken_Exception_Trait;

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
}

