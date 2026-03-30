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
    public function get_rating(int $rating_id): ?object;

    /**
     * Get all ratings
     *
     * @param string $orderby Column to order by.
     * @param string $order   Sort order (ASC or DESC).
     * @return array Array of rating objects.
     */
    public function get_all_ratings(string $orderby = 'id', string $order = 'DESC'): array;

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
    public function get_ratings_paginated(int $per_page = 20, int $page = 1, string $search = '', string $orderby = 'id', string $order = 'DESC'): object;

    /**
     * Create a new rating
     *
     * @param string      $name         Rating name.
     * @param int|null    $parent_id    Parent rating ID.
     * @param string      $effect_type  Effect type ('positive' or 'negative').
     * @param bool        $display_only Whether rating is display-only.
     * @param int|null    $mirror_of    ID of rating to mirror.
     * @param string      $rating_type  Rating type ('stars', 'like_dislike', 'numeric', 'approval').
     * @param int         $scale        Display scale (2-10 for stars, 2-100 for numeric, 1 for binary types).
     * @return int|false New rating ID or false on failure.
     */
    public function create_rating(string $name, ?int $parent_id = null, string $effect_type = 'positive', bool $display_only = false, ?int $mirror_of = null, string $rating_type = 'stars', int $scale = 5): int;

    /**
     * Update a rating
     *
     * @param int   $rating_id Rating ID.
     * @param array $data      Data to update.
     * @return bool True on success, false on failure.
     */
    public function update_rating(int $rating_id, array $data): bool;

    /**
     * Delete a rating and its votes
     *
     * @param int $rating_id Rating ID.
     * @return bool True on success, false on failure.
     */
    public function delete_rating(int $rating_id): bool;

    /**
     * Get sub-ratings of a parent rating
     *
     * @param int $parent_id Parent rating ID.
     * @return array Array of rating objects.
     */
    public function get_sub_ratings(int $parent_id): array;

    /**
     * Get all parent ratings
     *
     * @param int|null $exclude_id Rating ID to exclude.
     * @return array Array of rating objects.
     */
    public function get_parent_ratings(?int $exclude_id = null): array;

    /**
     * Recalculate parent rating totals
     *
     * @param int $parent_id Parent rating ID.
     * @return bool True on success, false on failure.
     */
    public function recalculate_parent_rating(int $parent_id): bool;

    /**
     * Get ratings that can be mirrored
     *
     * @param int|null $exclude_id Rating ID to exclude.
     * @return array Array of rating objects.
     */
    public function get_mirrorable_ratings(?int $exclude_id = null): array;

    /**
     * Get mirrors of a rating
     *
     * @param int $rating_id Rating ID.
     * @return array Array of mirror rating objects.
     */
    public function get_mirrors(int $rating_id): array;

    /**
     * Get multiple ratings by IDs in a single query
     *
     * @param array $ids Array of rating IDs.
     * @return array Array of rating objects indexed by ID.
     */
    public function get_ratings_by_ids(array $ids): array;

    /**
     * Search ratings by name
     *
     * @param string $search_term Search term to match against rating names.
     * @param int    $limit       Maximum number of results.
     * @param string $type        Filter type: 'all', 'parents', 'mirrorable'.
     * @return array Array of rating objects matching the search.
     */
    public function search_ratings(string $search_term, int $limit = 20, string $type = 'all'): array;

    /**
     * Get user's vote for a rating
     *
     * @param int         $rating_id    Rating ID.
     * @param int         $user_id      User ID.
     * @param string|null $user_ip      User IP address.
     * @param int|null    $context_id   Optional post/entity ID for contextual votes.
     * @param string|null $context_type Optional context type.
     * @return object|null Vote object or null if not found.
     */
    public function get_user_vote(int $rating_id, int $user_id, ?string $user_ip = null, ?int $context_id = null, ?string $context_type = null): ?object;

    /**
     * Create a new vote
     *
     * @param int         $rating_id    Rating ID.
     * @param float       $rating_value Rating value.
     * @param int         $user_id      User ID.
     * @param string|null $user_ip      User IP address.
     * @param int|null    $context_id   Optional post/entity ID for contextual votes.
     * @param string|null $context_type Optional context type.
     * @return bool True on success, false on failure.
     */
    public function create_vote(int $rating_id, float|int $rating_value, int $user_id = 0, ?string $user_ip = null, ?int $context_id = null, ?string $context_type = null): bool;

    /**
     * Update an existing vote
     *
     * @param int   $vote_id   Vote ID.
     * @param int   $rating_id Rating ID.
     * @param float $old_value Previous rating value.
     * @param float $new_value New rating value.
     * @return bool True on success, false on failure.
     */
    public function update_vote(int $vote_id, int $rating_id, float|int $old_value, float|int $new_value): bool;

    /**
     * Create database tables
     *
     * @return bool True on success, false on failure.
     */
    public function create_tables(): bool;

    /**
     * Check if database tables exist
     *
     * @return bool True if tables exist, false otherwise.
     */
    public function tables_exist(): bool;

    /**
     * Get the ratings table name
     *
     * @return string Table name.
     */
    public function get_ratings_table(): string;

    /**
     * Get the votes table name
     *
     * @return string Table name.
     */
    public function get_votes_table(): string;

    /**
     * Get the timestamp of the last vote for a rating by a user/guest
     *
     * @param int         $rating_id    Rating ID.
     * @param int         $user_id      User ID (0 for guests).
     * @param string|null $user_ip      User IP address (for guests).
     * @param int|null    $context_id   Optional post/entity ID for contextual votes.
     * @param string|null $context_type Optional context type.
     * @return string|null Datetime string or null if no vote found.
     */
    public function get_last_vote_time(int $rating_id, int $user_id, ?string $user_ip = null, ?int $context_id = null, ?string $context_type = null): ?string;

    /**
     * Get contextual stats for a rating scoped to a specific post/entity
     *
     * @param int    $rating_id    Rating ID.
     * @param int    $context_id   Post/entity ID.
     * @param string $context_type Context type.
     * @return object Object with total_votes, total_rating, average.
     */
    public function get_contextual_stats(int $rating_id, int $context_id, string $context_type): object;

    /**
     * Get contextual stats for multiple ratings in a single query
     *
     * @param array  $rating_ids   Array of rating IDs.
     * @param int    $context_id   Post/entity ID.
     * @param string $context_type Context type.
     * @return array Associative array keyed by rating_id.
     */
    public function get_contextual_stats_batch(array $rating_ids, int $context_id, string $context_type): array;

    /**
     * Count votes by a user/guest since a given datetime
     *
     * @param int         $user_id User ID (0 for guests).
     * @param string|null $user_ip User IP address (for guests).
     * @param string      $since   Datetime string (Y-m-d H:i:s format).
     * @return int Number of votes since the given time.
     */
    public function count_votes_since(int $user_id, ?string $user_ip, string $since): int;

    /**
     * Get the oldest vote datetime within a time window
     *
     * Used to calculate when rate limits will reset.
     *
     * @param int         $user_id        User ID (0 for guests).
     * @param string|null $user_ip        User IP address (for guests).
     * @param int         $window_seconds Time window in seconds.
     * @return string|null Datetime string or null if no votes in window.
     */
    public function get_oldest_vote_in_window(int $user_id, ?string $user_ip, int $window_seconds): ?string;
}

