<?php
/**
 * Database Exception for Shuriken Reviews
 *
 * Thrown when database operations fail.
 *
 * @package Shuriken_Reviews
 * @since 1.7.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_Database_Exception
 *
 * Exception for database operation failures.
 *
 * @since 1.7.0
 */
class Shuriken_Database_Exception extends Shuriken_Exception {

    /**
     * Constructor
     *
     * @param string         $message  Error message.
     * @param string         $operation Database operation that failed.
     * @param Throwable|null $previous Previous exception.
     */
    public function __construct($message = '', $operation = 'unknown', $previous = null) {
        $error_code = 'database_' . $operation . '_failed';
        parent::__construct($message, $error_code, 0, $previous);
    }

    /**
     * Create exception for insert failure
     *
     * @param string $table Table name.
     * @return self
     */
    public static function insert_failed($table) {
        return new self(
            sprintf(__('Failed to insert data into %s table', 'shuriken-reviews'), $table),
            'insert'
        );
    }

    /**
     * Create exception for update failure
     *
     * @param string $table Table name.
     * @param int    $id    Record ID.
     * @return self
     */
    public static function update_failed($table, $id) {
        return new self(
            sprintf(__('Failed to update %s with ID %d', 'shuriken-reviews'), $table, $id),
            'update'
        );
    }

    /**
     * Create exception for delete failure
     *
     * @param string $table Table name.
     * @param int    $id    Record ID.
     * @return self
     */
    public static function delete_failed($table, $id) {
        return new self(
            sprintf(__('Failed to delete %s with ID %d', 'shuriken-reviews'), $table, $id),
            'delete'
        );
    }

    /**
     * Create exception for query failure
     *
     * @param string $query Query type.
     * @return self
     */
    public static function query_failed($query) {
        return new self(
            sprintf(__('Database query failed: %s', 'shuriken-reviews'), $query),
            'query'
        );
    }

    /**
     * Create exception for transaction failure
     *
     * @return self
     */
    public static function transaction_failed() {
        return new self(
            __('Database transaction failed and was rolled back', 'shuriken-reviews'),
            'transaction'
        );
    }
}

