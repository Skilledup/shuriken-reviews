<?php
/**
 * Shuriken Reviews Analytics Ranking Interface
 *
 * Defines the contract for leaderboard / ranking operations. Split out of the
 * monolithic Shuriken_Analytics_Interface so add-on decorators can implement
 * only the ranking concern they need.
 *
 * @package Shuriken_Reviews
 * @since 1.15.6
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface Shuriken_Analytics_Ranking_Interface
 *
 * Contract for analytics ranking operations.
 *
 * @since 1.15.6
 */
interface Shuriken_Analytics_Ranking_Interface {

    /**
     * Get top rated items
     *
     * @param int    $limit       Number of results.
     * @param int    $min_votes   Minimum votes required.
     * @param float  $min_average Minimum average rating.
     * @param string $date_range  Date range identifier.
     * @return array Array of rating objects.
     */
    public function get_top_rated(int $limit = 10, int $min_votes = 1, float $min_average = 3.0, string|int|array $date_range = 'all'): array;

    /**
     * Get most voted items
     *
     * @param int    $limit      Number of results.
     * @param string $date_range Date range identifier.
     * @return array Array of rating objects.
     */
    public function get_most_voted(int $limit = 10, string|int|array $date_range = 'all'): array;

    /**
     * Get low performing items
     *
     * @param int    $limit       Number of results.
     * @param int    $min_votes   Minimum votes required.
     * @param float  $max_average Maximum average rating.
     * @param string $date_range  Date range identifier.
     * @return array Array of rating objects.
     */
    public function get_low_performers(int $limit = 10, int $min_votes = 1, float $max_average = 3.0, string|int|array $date_range = 'all'): array;
}
