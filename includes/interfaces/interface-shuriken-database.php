<?php
/**
 * Shuriken Reviews Database Interface
 *
 * Defines the contract for database operations in the Shuriken Reviews plugin.
 * This interface improves testability by allowing mock implementations.
 *
 * @package Shuriken_Reviews
 * @since 1.7.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface Shuriken_Database_Interface
 *
 * Contract for database operations.
 *
 * @since 1.7.0
 */
interface Shuriken_Database_Interface {

    /**
     * Get a single rating by ID
     *
     * @param int $rating_id Rating ID.
     * @return object|null Rating object or null if not found.
     */
    public function get_rating($rating_id);

    /**
     * Get all ratings
     *
     * @param string $orderby Column to order by.
     * @param string $order   Sort order (ASC or DESC).
     * @return array Array of rating objects.
     */
    public function get_all_ratings($orderby = 'id', $order = 'DESC');

    /**
     * Get paginated ratings
     *
     * @param int    $per_page Number of ratings per page.
     * @param int    $page     Current page number.
     * @param string $search   Search term.
     * @param string $orderby  Column to order by.
     * @param string $order    Sort order (ASC or DESC).
     * @return array Array with 'ratings', 'total', and 'total_pages' keys.
     */
    public function get_ratings_paginated($per_page = 20, $page = 1, $search = '', $orderby = 'id', $order = 'DESC');

    /**
     * Create a new rating
     *
     * @param string      $name         Rating name.
     * @param int|null    $parent_id    Parent rating ID.
     * @param string      $effect_type  Effect type ('positive' or 'negative').
     * @param bool        $display_only Whether rating is display-only.
     * @param int|null    $mirror_of    ID of rating to mirror.
     * @return int|false New rating ID or false on failure.
     */
    public function create_rating($name, $parent_id = null, $effect_type = 'positive', $display_only = false, $mirror_of = null);

    /**
     * Update a rating
     *
     * @param int   $rating_id Rating ID.
     * @param array $data      Data to update.
     * @return bool True on success, false on failure.
     */
    public function update_rating($rating_id, $data);

    /**
     * Delete a rating and its votes
     *
     * @param int $rating_id Rating ID.
     * @return bool True on success, false on failure.
     */
    public function delete_rating($rating_id);

    /**
     * Get sub-ratings of a parent rating
     *
     * @param int $parent_id Parent rating ID.
     * @return array Array of rating objects.
     */
    public function get_sub_ratings($parent_id);

    /**
     * Get all parent ratings
     *
     * @param int|null $exclude_id Rating ID to exclude.
     * @return array Array of rating objects.
     */
    public function get_parent_ratings($exclude_id = null);

    /**
     * Recalculate parent rating totals
     *
     * @param int $parent_id Parent rating ID.
     * @return bool True on success, false on failure.
     */
    public function recalculate_parent_rating($parent_id);

    /**
     * Get ratings that can be mirrored
     *
     * @param int|null $exclude_id Rating ID to exclude.
     * @return array Array of rating objects.
     */
    public function get_mirrorable_ratings($exclude_id = null);

    /**
     * Get mirrors of a rating
     *
     * @param int $rating_id Rating ID.
     * @return array Array of mirror rating objects.
     */
    public function get_mirrors($rating_id);

    /**
     * Get multiple ratings by IDs in a single query
     *
     * @param array $ids Array of rating IDs.
     * @return array Array of rating objects indexed by ID.
     */
    public function get_ratings_by_ids($ids);

    /**
     * Search ratings by name
     *
     * @param string $search_term Search term to match against rating names.
     * @param int    $limit       Maximum number of results.
     * @param string $type        Filter type: 'all', 'parents', 'mirrorable'.
     * @return array Array of rating objects matching the search.
     */
    public function search_ratings($search_term, $limit = 20, $type = 'all');

    /**
     * Get user's vote for a rating
     *
     * @param int         $rating_id Rating ID.
     * @param int         $user_id   User ID.
     * @param string|null $user_ip   User IP address.
     * @return object|null Vote object or null if not found.
     */
    public function get_user_vote($rating_id, $user_id, $user_ip = null);

    /**
     * Create a new vote
     *
     * @param int         $rating_id    Rating ID.
     * @param float       $rating_value Rating value.
     * @param int         $user_id      User ID.
     * @param string|null $user_ip      User IP address.
     * @return bool True on success, false on failure.
     */
    public function create_vote($rating_id, $rating_value, $user_id = 0, $user_ip = null);

    /**
     * Update an existing vote
     *
     * @param int   $vote_id   Vote ID.
     * @param int   $rating_id Rating ID.
     * @param float $old_value Previous rating value.
     * @param float $new_value New rating value.
     * @return bool True on success, false on failure.
     */
    public function update_vote($vote_id, $rating_id, $old_value, $new_value);

    /**
     * Create database tables
     *
     * @return bool True on success, false on failure.
     */
    public function create_tables();

    /**
     * Check if database tables exist
     *
     * @return bool True if tables exist, false otherwise.
     */
    public function tables_exist();

    /**
     * Get the ratings table name
     *
     * @return string Table name.
     */
    public function get_ratings_table();

    /**
     * Get the votes table name
     *
     * @return string Table name.
     */
    public function get_votes_table();
}

