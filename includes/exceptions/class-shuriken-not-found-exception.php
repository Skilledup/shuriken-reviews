<?php
/**
 * Not Found Exception for Shuriken Reviews
 *
 * Thrown when a requested resource doesn't exist.
 *
 * @package Shuriken_Reviews
 * @since 1.7.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_Not_Found_Exception
 *
 * Exception for missing resources.
 *
 * @since 1.7.0
 */
class Shuriken_Not_Found_Exception extends Shuriken_Exception {

    /**
     * @var string Resource type
     */
    protected $resource_type;

    /**
     * @var int|string Resource ID
     */
    protected $resource_id;

    /**
     * Constructor
     *
     * @param string         $message       Error message.
     * @param string         $resource_type Type of resource.
     * @param int|string     $resource_id   Resource ID.
     * @param Throwable|null $previous      Previous exception.
     */
    public function __construct($message = '', $resource_type = 'resource', $resource_id = 0, $previous = null) {
        $this->resource_type = $resource_type;
        $this->resource_id = $resource_id;
        $error_code = $resource_type . '_not_found';
        parent::__construct($message, $error_code, 404, $previous);
    }

    /**
     * Get the resource type
     *
     * @return string
     */
    public function get_resource_type() {
        return $this->resource_type;
    }

    /**
     * Get the resource ID
     *
     * @return int|string
     */
    public function get_resource_id() {
        return $this->resource_id;
    }

    /**
     * Create exception for rating not found
     *
     * @param int $rating_id Rating ID.
     * @return self
     */
    public static function rating($rating_id) {
        return new self(
            sprintf(__('Rating with ID %d not found', 'shuriken-reviews'), $rating_id),
            'rating',
            $rating_id
        );
    }

    /**
     * Create exception for vote not found
     *
     * @param int $vote_id Vote ID.
     * @return self
     */
    public static function vote($vote_id) {
        return new self(
            sprintf(__('Vote with ID %d not found', 'shuriken-reviews'), $vote_id),
            'vote',
            $vote_id
        );
    }

    /**
     * Create exception for parent rating not found
     *
     * @param int $parent_id Parent rating ID.
     * @return self
     */
    public static function parent_rating($parent_id) {
        return new self(
            sprintf(__('Parent rating with ID %d not found', 'shuriken-reviews'), $parent_id),
            'parent_rating',
            $parent_id
        );
    }
}

