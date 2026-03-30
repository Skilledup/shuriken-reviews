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
 * @since 1.8.0 Implements Shuriken_Exception_Interface via parent
 */
class Shuriken_Not_Found_Exception extends Shuriken_Exception {

    /**
     * @var string Resource type
     */
    protected string $resource_type;

    /**
     * @var int|string Resource ID
     */
    protected int|string $resource_id;

    /**
     * Constructor
     *
     * @param string         $message       Error message.
     * @param string         $resource_type Type of resource.
     * @param int|string     $resource_id   Resource ID.
     * @param Throwable|null $previous      Previous exception.
     */
    public function __construct(string $message = '', string $resource_type = 'resource', int|string $resource_id = 0, ?\Throwable $previous = null) {
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
    public function get_resource_type(): string {
        return $this->resource_type;
    }

    /**
     * Get the resource ID
     *
     * @return int|string
     */
    public function get_resource_id(): int|string {
        return $this->resource_id;
    }

    /**
     * Create exception for rating not found
     *
     * @param int $rating_id Rating ID.
     * @return self
     */
    public static function rating(int $rating_id): self {
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
    public static function vote(int $vote_id): self {
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
    public static function parent_rating(int $parent_id): self {
        return new self(
            sprintf(__('Parent rating with ID %d not found', 'shuriken-reviews'), $parent_id),
            'parent_rating',
            $parent_id
        );
    }
}

