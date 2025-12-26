<?php
/**
 * Shuriken Reviews Analytics Interface
 *
 * Defines the contract for analytics operations in the Shuriken Reviews plugin.
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
 * Interface Shuriken_Analytics_Interface
 *
 * Contract for analytics operations.
 *
 * @since 1.7.0
 */
interface Shuriken_Analytics_Interface {

    /**
     * Get overall statistics
     *
     * @return array Array with total_ratings, total_votes, average_rating, etc.
     */
    public function get_overall_stats();

    /**
     * Get rating type counts
     *
     * @return array Array with counts for different rating types.
     */
    public function get_rating_type_counts();

    /**
     * Get vote counts for a date range
     *
     * @param string $date_range Date range identifier.
     * @return int Number of votes.
     */
    public function get_vote_counts($date_range = 'all');

    /**
     * Get vote change percentage
     *
     * @param string $date_range Date range identifier.
     * @return float Percentage change.
     */
    public function get_vote_change_percent($date_range);

    /**
     * Get top rated items
     *
     * @param int    $limit       Number of results.
     * @param int    $min_votes   Minimum votes required.
     * @param float  $min_average Minimum average rating.
     * @param string $date_range  Date range identifier.
     * @return array Array of rating objects.
     */
    public function get_top_rated($limit = 10, $min_votes = 1, $min_average = 3.0, $date_range = 'all');

    /**
     * Get most voted items
     *
     * @param int    $limit      Number of results.
     * @param string $date_range Date range identifier.
     * @return array Array of rating objects.
     */
    public function get_most_voted($limit = 10, $date_range = 'all');

    /**
     * Get low performing items
     *
     * @param int    $limit       Number of results.
     * @param int    $min_votes   Minimum votes required.
     * @param float  $max_average Maximum average rating.
     * @param string $date_range  Date range identifier.
     * @return array Array of rating objects.
     */
    public function get_low_performers($limit = 10, $min_votes = 1, $max_average = 3.0, $date_range = 'all');

    /**
     * Get rating distribution
     *
     * @param string   $date_range Date range identifier.
     * @param int|null $rating_id  Optional rating ID to filter by.
     * @return array Array with counts for each star rating (1-5).
     */
    public function get_rating_distribution($date_range = 'all', $rating_id = null);

    /**
     * Get votes over time
     *
     * @param int      $date_range Number of days.
     * @param int|null $rating_id  Optional rating ID to filter by.
     * @return array Array of date => count pairs.
     */
    public function get_votes_over_time($date_range = 30, $rating_id = null);

    /**
     * Get recent votes
     *
     * @param int      $limit      Number of results.
     * @param int|null $rating_id  Optional rating ID to filter by.
     * @param string   $date_range Date range identifier.
     * @return array Array of vote objects.
     */
    public function get_recent_votes($limit = 10, $rating_id = null, $date_range = 'all');

    /**
     * Get a single rating
     *
     * @param int $rating_id Rating ID.
     * @return object|null Rating object or null if not found.
     */
    public function get_rating($rating_id);

    /**
     * Get rating statistics
     *
     * @param int    $rating_id  Rating ID.
     * @param string $date_range Date range identifier.
     * @return array Array with statistics.
     */
    public function get_rating_stats($rating_id, $date_range = 'all');

    /**
     * Get paginated votes for a rating
     *
     * @param int $rating_id Rating ID.
     * @param int $page      Page number.
     * @param int $per_page  Items per page.
     * @return array Array with 'votes', 'total', and 'total_pages' keys.
     */
    public function get_rating_votes_paginated($rating_id, $page = 1, $per_page = 20);

    /**
     * Get chart data for visualization
     *
     * @param int      $date_range Number of days.
     * @param int|null $rating_id  Optional rating ID to filter by.
     * @return array Array with chart data.
     */
    public function get_chart_data($date_range = 30, $rating_id = null);

    /**
     * Build date condition for SQL queries
     *
     * @param string $date_range Date range identifier.
     * @param string $column     Column name for date comparison.
     * @return string SQL WHERE clause condition.
     */
    public function build_date_condition($date_range, $column = 'date_created');

    /**
     * Parse date range parameters
     *
     * @param array $params Request parameters.
     * @return string Date range identifier.
     */
    public function parse_date_range_params($params);

    /**
     * Get date range label
     *
     * @param string $date_range Date range identifier.
     * @return string Human-readable label.
     */
    public function get_date_range_label($date_range);

    /**
     * Format time ago string
     *
     * @param string $mysql_date MySQL datetime string.
     * @return string Human-readable time ago string.
     */
    public function format_time_ago($mysql_date);

    /**
     * Format date string
     *
     * @param string $mysql_date   MySQL datetime string.
     * @param bool   $include_time Whether to include time.
     * @return string Formatted date string.
     */
    public function format_date($mysql_date, $include_time = true);
}

