<?php
/**
 * Shuriken Reviews Analytics Class
 *
 * Provides reusable methods for fetching rating statistics and analytics data.
 * Can be used in admin pages, shortcodes, REST API, or widgets.
 *
 * @package Shuriken_Reviews
 * @since 1.3.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_Analytics
 *
 * Handles all analytics and statistics data retrieval for ratings.
 *
 * @since 1.3.0
 */
class Shuriken_Analytics {

    /**
     * @var wpdb WordPress database instance
     */
    private $wpdb;

    /**
     * @var string Ratings table name
     */
    private $ratings_table;

    /**
     * @var string Votes table name
     */
    private $votes_table;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->ratings_table = $wpdb->prefix . 'shuriken_ratings';
        $this->votes_table = $wpdb->prefix . 'shuriken_votes';
    }

    /**
     * Get singleton instance
     *
     * @return Shuriken_Analytics
     */
    public static function get_instance() {
        static $instance = null;
        if (null === $instance) {
            $instance = new self();
        }
        return $instance;
    }

    /**
     * Build date condition SQL for filtering by date range
     *
     * @param string|int $date_range Number of days or 'all'
     * @param string $column Column name for date comparison
     * @return string SQL condition string
     */
    public function build_date_condition($date_range, $column = 'date_created') {
        if ($date_range === 'all' || empty($date_range)) {
            return '';
        }
        return $this->wpdb->prepare(
            " AND {$column} >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            intval($date_range)
        );
    }

    /**
     * Get overall statistics
     *
     * @return object Object containing total_ratings, total_votes, overall_average, unique_voters
     */
    public function get_overall_stats() {
        $stats = new stdClass();
        
        $stats->total_ratings = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->ratings_table}"
        );
        
        $stats->total_votes = (int) $this->wpdb->get_var(
            "SELECT COALESCE(SUM(total_votes), 0) FROM {$this->ratings_table}"
        );
        
        $stats->overall_average = (float) $this->wpdb->get_var(
            "SELECT AVG(total_rating / NULLIF(total_votes, 0)) 
             FROM {$this->ratings_table} 
             WHERE total_votes > 0"
        );
        
        $stats->unique_voters = (int) $this->wpdb->get_var(
            "SELECT COUNT(DISTINCT CASE WHEN user_id > 0 THEN user_id ELSE user_ip END) 
             FROM {$this->votes_table}"
        );
        
        return $stats;
    }

    /**
     * Get vote counts for a specific period
     *
     * @param string|int $date_range Number of days or 'all'
     * @return object Object containing period_votes, member_votes, guest_votes
     */
    public function get_vote_counts($date_range = 'all') {
        $date_condition = $this->build_date_condition($date_range);
        
        $counts = new stdClass();
        
        $counts->period_votes = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->votes_table} WHERE 1=1 {$date_condition}"
        );
        
        $counts->member_votes = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->votes_table} WHERE user_id > 0 {$date_condition}"
        );
        
        $counts->guest_votes = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->votes_table} WHERE user_id = 0 {$date_condition}"
        );
        
        return $counts;
    }

    /**
     * Calculate vote change percentage compared to previous period
     *
     * @param int $date_range Number of days for current period
     * @return float|null Percentage change or null if not applicable
     */
    public function get_vote_change_percent($date_range) {
        if ($date_range === 'all' || empty($date_range)) {
            return null;
        }
        
        $days = intval($date_range);
        
        $current_votes = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->votes_table} 
             WHERE date_created >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
        
        $previous_votes = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->votes_table} 
             WHERE date_created >= DATE_SUB(NOW(), INTERVAL %d DAY) 
               AND date_created < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days * 2,
            $days
        ));
        
        if ($previous_votes > 0) {
            return round((($current_votes - $previous_votes) / $previous_votes) * 100, 1);
        }
        
        return $current_votes > 0 ? 100 : 0;
    }

    /**
     * Get top rated items
     *
     * @param int $limit Maximum number of items to return
     * @param int $min_votes Minimum votes required
     * @param float $min_average Minimum average rating (default 3.0 for "top" items)
     * @return array Array of rating objects
     */
    public function get_top_rated($limit = 10, $min_votes = 1, $min_average = 3.0) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT id, name, total_votes, total_rating, 
                    ROUND(total_rating / NULLIF(total_votes, 0), 1) as average 
             FROM {$this->ratings_table} 
             WHERE total_votes >= %d 
               AND (total_rating / NULLIF(total_votes, 0)) >= %f
             ORDER BY average DESC, total_votes DESC 
             LIMIT %d",
            $min_votes,
            $min_average,
            $limit
        ));
    }

    /**
     * Get most voted (popular) items
     *
     * @param int $limit Maximum number of items to return
     * @return array Array of rating objects
     */
    public function get_most_voted($limit = 10) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT id, name, total_votes, total_rating,
                    ROUND(total_rating / NULLIF(total_votes, 0), 1) as average
             FROM {$this->ratings_table} 
             ORDER BY total_votes DESC 
             LIMIT %d",
            $limit
        ));
    }

    /**
     * Get low performing items (average < threshold)
     *
     * @param int $limit Maximum number of items to return
     * @param int $min_votes Minimum votes required
     * @param float $max_average Maximum average rating (default 3.0)
     * @return array Array of rating objects
     */
    public function get_low_performers($limit = 10, $min_votes = 1, $max_average = 3.0) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT id, name, total_votes, total_rating, 
                    ROUND(total_rating / NULLIF(total_votes, 0), 1) as average 
             FROM {$this->ratings_table} 
             WHERE total_votes >= %d 
               AND (total_rating / NULLIF(total_votes, 0)) < %f
             ORDER BY average ASC, total_votes DESC 
             LIMIT %d",
            $min_votes,
            $max_average,
            $limit
        ));
    }

    /**
     * Get rating distribution (count of each star value)
     *
     * @param string|int $date_range Number of days or 'all'
     * @param int|null $rating_id Optional specific rating ID
     * @return array Associative array with keys 1-5 and vote counts
     */
    public function get_rating_distribution($date_range = 'all', $rating_id = null) {
        $date_condition = $this->build_date_condition($date_range);
        $rating_condition = $rating_id ? $this->wpdb->prepare(" AND rating_id = %d", $rating_id) : '';
        
        $results = $this->wpdb->get_results(
            "SELECT rating_value, COUNT(*) as count 
             FROM {$this->votes_table} 
             WHERE 1=1 {$date_condition} {$rating_condition}
             GROUP BY rating_value 
             ORDER BY rating_value"
        );
        
        // Ensure all ratings 1-5 are represented
        $distribution = array_fill(1, 5, 0);
        foreach ($results as $row) {
            $distribution[intval($row->rating_value)] = intval($row->count);
        }
        
        return $distribution;
    }

    /**
     * Get votes over time
     *
     * @param string|int $date_range Number of days or 'all'
     * @param int|null $rating_id Optional specific rating ID
     * @return array Array of objects with vote_date and vote_count
     */
    public function get_votes_over_time($date_range = 30, $rating_id = null) {
        $date_condition = $this->build_date_condition($date_range);
        $rating_condition = $rating_id ? $this->wpdb->prepare(" AND rating_id = %d", $rating_id) : '';
        
        return $this->wpdb->get_results(
            "SELECT DATE(date_created) as vote_date, COUNT(*) as vote_count 
             FROM {$this->votes_table} 
             WHERE 1=1 {$date_condition} {$rating_condition}
             GROUP BY DATE(date_created) 
             ORDER BY vote_date"
        );
    }

    /**
     * Get recent votes/activity
     *
     * @param int $limit Maximum number of votes to return
     * @param int|null $rating_id Optional specific rating ID
     * @return array Array of vote objects with rating info
     */
    public function get_recent_votes($limit = 10, $rating_id = null) {
        $rating_condition = $rating_id ? $this->wpdb->prepare("AND v.rating_id = %d", $rating_id) : '';
        
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT v.id, v.rating_id, v.rating_value, v.date_created, v.user_id, v.user_ip,
                    r.name as rating_name, u.display_name, u.user_email
             FROM {$this->votes_table} v
             JOIN {$this->ratings_table} r ON v.rating_id = r.id
             LEFT JOIN {$this->wpdb->users} u ON v.user_id = u.ID
             WHERE 1=1 {$rating_condition}
             ORDER BY v.date_created DESC
             LIMIT %d",
            $limit
        ));
    }

    /**
     * Get single rating item with stats
     *
     * @param int $rating_id Rating ID
     * @return object|null Rating object with calculated stats or null
     */
    public function get_rating($rating_id) {
        $rating = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->ratings_table} WHERE id = %d",
            $rating_id
        ));
        
        if ($rating) {
            $rating->average = $rating->total_votes > 0 
                ? round($rating->total_rating / $rating->total_votes, 1) 
                : 0;
        }
        
        return $rating;
    }

    /**
     * Get detailed stats for a single rating item
     *
     * @param int $rating_id Rating ID
     * @return object|null Object with comprehensive stats or null
     */
    public function get_rating_stats($rating_id) {
        $rating = $this->get_rating($rating_id);
        if (!$rating) {
            return null;
        }
        
        $stats = new stdClass();
        $stats->rating = $rating;
        $stats->average = $rating->average;
        
        // Vote counts by user type
        $stats->member_votes = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->votes_table} WHERE rating_id = %d AND user_id > 0",
            $rating_id
        ));
        
        $stats->guest_votes = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->votes_table} WHERE rating_id = %d AND user_id = 0",
            $rating_id
        ));
        
        // Unique voters
        $stats->unique_voters = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(DISTINCT CASE WHEN user_id > 0 THEN user_id ELSE user_ip END) 
             FROM {$this->votes_table} WHERE rating_id = %d",
            $rating_id
        ));
        
        // First and last vote
        $stats->first_vote = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT MIN(date_created) FROM {$this->votes_table} WHERE rating_id = %d",
            $rating_id
        ));
        
        $stats->last_vote = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT MAX(date_created) FROM {$this->votes_table} WHERE rating_id = %d",
            $rating_id
        ));
        
        // Distribution and timeline
        $stats->distribution = $this->get_rating_distribution('all', $rating_id);
        $stats->votes_over_time = $this->get_votes_over_time(30, $rating_id);
        
        return $stats;
    }

    /**
     * Get paginated votes for a rating
     *
     * @param int $rating_id Rating ID
     * @param int $page Current page (1-indexed)
     * @param int $per_page Items per page
     * @return object Object with votes array, total_count, total_pages, current_page
     */
    public function get_rating_votes_paginated($rating_id, $page = 1, $per_page = 20) {
        $offset = ($page - 1) * $per_page;
        
        $result = new stdClass();
        
        $result->total_count = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->votes_table} WHERE rating_id = %d",
            $rating_id
        ));
        
        $result->total_pages = ceil($result->total_count / $per_page);
        $result->current_page = $page;
        $result->per_page = $per_page;
        
        $result->votes = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT v.*, u.display_name, u.user_email
             FROM {$this->votes_table} v
             LEFT JOIN {$this->wpdb->users} u ON v.user_id = u.ID
             WHERE v.rating_id = %d
             ORDER BY v.date_created DESC
             LIMIT %d OFFSET %d",
            $rating_id,
            $per_page,
            $offset
        ));
        
        return $result;
    }

    /**
     * Format time ago string with proper timezone handling
     *
     * @param string $mysql_date MySQL datetime string
     * @return string Formatted "X time ago" string
     */
    public function format_time_ago($mysql_date) {
        if (empty($mysql_date)) {
            return '-';
        }
        return human_time_diff(mysql2date('U', $mysql_date), current_time('timestamp')) 
               . ' ' . __('ago', 'shuriken-reviews');
    }

    /**
     * Format date with WordPress settings
     *
     * @param string $mysql_date MySQL datetime string
     * @param bool $include_time Whether to include time
     * @return string Formatted date string
     */
    public function format_date($mysql_date, $include_time = true) {
        if (empty($mysql_date)) {
            return '-';
        }
        $format = get_option('date_format');
        if ($include_time) {
            $format .= ' ' . get_option('time_format');
        }
        return mysql2date($format, $mysql_date);
    }

    /**
     * Get data formatted for charts (Chart.js compatible)
     *
     * @param string|int $date_range Number of days or 'all'
     * @param int|null $rating_id Optional specific rating ID
     * @return array Array with distribution, votes_over_time, user_types
     */
    public function get_chart_data($date_range = 30, $rating_id = null) {
        $vote_counts = $rating_id ? null : $this->get_vote_counts($date_range);
        
        return array(
            'distribution' => array_values($this->get_rating_distribution($date_range, $rating_id)),
            'votes_over_time' => $this->get_votes_over_time($date_range, $rating_id),
            'user_types' => $rating_id ? null : array(
                'members' => $vote_counts->member_votes,
                'guests' => $vote_counts->guest_votes,
            ),
        );
    }
}

/**
 * Helper function to get analytics instance
 *
 * @return Shuriken_Analytics
 */
function shuriken_analytics() {
    return Shuriken_Analytics::get_instance();
}
