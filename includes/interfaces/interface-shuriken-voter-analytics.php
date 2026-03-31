<?php
/**
 * Shuriken Reviews Voter Analytics Interface
 *
 * Defines the contract for voter-specific analytics operations.
 *
 * @package Shuriken_Reviews
 * @since 1.14.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface Shuriken_Voter_Analytics_Interface
 *
 * Contract for voter activity analytics operations.
 *
 * @since 1.14.0
 */
interface Shuriken_Voter_Analytics_Interface {

    /**
     * Get paginated votes for a specific voter (user or guest by IP)
     *
     * @param int         $user_id    User ID (0 for guests).
     * @param string|null $user_ip    IP address for guest identification.
     * @param int         $page       Current page (1-indexed).
     * @param int         $per_page   Items per page.
     * @param string|int|array $date_range Date range filter.
     * @return object Object with votes array, total_count, total_pages, current_page.
     */
    public function get_voter_votes_paginated(int $user_id, ?string $user_ip = null, int $page = 1, int $per_page = 20, string|int|array $date_range = 'all'): object;

    /**
     * Get voting statistics for a specific voter
     *
     * @param int         $user_id    User ID (0 for guests).
     * @param string|null $user_ip    IP address for guest identification.
     * @param string|int|array $date_range Date range filter.
     * @return object Object with voting statistics.
     */
    public function get_voter_stats(int $user_id, ?string $user_ip = null, string|int|array $date_range = 'all'): object;

    /**
     * Get rating distribution for a specific voter
     *
     * @param int         $user_id    User ID (0 for guests).
     * @param string|null $user_ip    IP address for guest identification.
     * @param string|int|array $date_range Date range filter.
     * @return array Distribution array.
     */
    public function get_voter_rating_distribution(int $user_id, ?string $user_ip = null, string|int|array $date_range = 'all'): array;

    /**
     * Get voter's activity over time
     *
     * @param int         $user_id    User ID (0 for guests).
     * @param string|null $user_ip    IP address for guest identification.
     * @param string|int|array $date_range Date range filter.
     * @return array Array of objects with vote_date and vote_count.
     */
    public function get_voter_activity_over_time(int $user_id, ?string $user_ip = null, string|int|array $date_range = 30): array;

    /**
     * Get WordPress user info
     *
     * @param int $user_id WordPress user ID.
     * @return object|null User object or null.
     */
    public function get_user_info(int $user_id): ?object;

    /**
     * Get all votes for a voter for export
     *
     * @param int         $user_id User ID (0 for guests).
     * @param string|null $user_ip IP address for guest identification.
     * @return array Array of vote objects with rating info.
     */
    public function get_voter_votes_for_export(int $user_id, ?string $user_ip = null): array;

    /**
     * Get per-type vote breakdown for a voter
     *
     * @param int         $user_id    User ID (0 for guests).
     * @param string|null $user_ip    IP address for guest identification.
     * @param string|int|array $date_range Date range filter.
     * @return array Array of objects with rating_type, vote_count, avg_value, etc.
     */
    public function get_voter_type_breakdown(int $user_id, ?string $user_ip = null, string|int|array $date_range = 'all'): array;
}
