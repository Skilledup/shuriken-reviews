<?php
/**
 * Shuriken Reviews Analytics Formatter Interface
 *
 * Defines the contract for display-formatting operations. Split out of the
 * monolithic Shuriken_Analytics_Interface so add-on decorators can implement
 * only the formatting concern they need.
 *
 * @package Shuriken_Reviews
 * @since 1.15.6
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface Shuriken_Analytics_Formatter_Interface
 *
 * Contract for analytics display-formatting operations.
 *
 * @since 1.15.6
 */
interface Shuriken_Analytics_Formatter_Interface {

    /**
     * Get date range label
     *
     * @param string $date_range Date range identifier.
     * @return string Human-readable label.
     */
    public function get_date_range_label(string|int|array $date_range): string;

    /**
     * Format time ago string
     *
     * @param string $mysql_date MySQL datetime string.
     * @return string Human-readable time ago string.
     */
    public function format_time_ago(string $mysql_date): string;

    /**
     * Format date string
     *
     * @param string $mysql_date   MySQL datetime string.
     * @param bool   $include_time Whether to include time.
     * @return string Formatted date string.
     */
    public function format_date(string $mysql_date, bool $include_time = true): string;

    /**
     * Format an average rating value for display, adapting to rating type
     *
     * @param float  $average     The average value.
     * @param string $rating_type Rating type.
     * @param int    $scale       Rating scale.
     * @param int    $total_votes Total votes.
     * @param int    $total_rating Total rating sum.
     * @return string Formatted display string.
     */
    public function format_average_display(float $average, string $rating_type = 'stars', int $scale = Shuriken_Database::RATING_SCALE_DEFAULT, int $total_votes = 0, int $total_rating = 0): string;

    /**
     * Render a vote value for display in tables, adapting to rating type
     *
     * @param int    $rating_value The vote value.
     * @param string $rating_type  Rating type.
     * @param int    $scale        Rating scale.
     * @return string HTML display string.
     */
    public function format_vote_display(int $rating_value, string $rating_type = 'stars', int $scale = Shuriken_Database::RATING_SCALE_DEFAULT): string;
}
