<?php
/**
 * Shuriken Reviews Voter Analytics Class
 *
 * Handles all voter-specific analytics: activity history, stats, distributions, and exports.
 * Extracted from the monolithic Shuriken_Analytics class for single-responsibility.
 *
 * @package Shuriken_Reviews
 * @since 1.14.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_Voter_Analytics
 *
 * Voter activity analytics — paginated votes, stats, distributions, and export.
 *
 * @since 1.14.0
 */
class Shuriken_Voter_Analytics implements Shuriken_Voter_Analytics_Interface {

    use Shuriken_Analytics_Helpers;

    /**
     * @var \wpdb WordPress database instance
     */
    private \wpdb $wpdb;

    /**
     * @var string Ratings table name
     */
    private string $ratings_table;

    /**
     * @var string Votes table name
     */
    private string $votes_table;

    /**
     * @var Shuriken_Database_Interface Database instance
     */
    private Shuriken_Database_Interface $db;

    /**
     * Constructor
     *
     * @param Shuriken_Database_Interface|null $db Optional database instance (for dependency injection).
     */
    public function __construct(?Shuriken_Database_Interface $db = null) {
        $this->db = $db ?: shuriken_db();
        $this->wpdb = $this->db->get_wpdb();
        $this->ratings_table = $this->db->get_ratings_table();
        $this->votes_table = $this->db->get_votes_table();
    }

    /**
     * Get paginated votes for a specific voter (user or guest by IP)
     *
     * @param int $user_id User ID (0 for guests)
     * @param string|null $user_ip IP address for guest identification
     * @param int $page Current page (1-indexed)
     * @param int $per_page Items per page
     * @param string|array $date_range Date range filter
     * @return object Object with votes array, total_count, total_pages, current_page
     */
    public function get_voter_votes_paginated(int $user_id, ?string $user_ip = null, int $page = 1, int $per_page = 20, string|int|array $date_range = 'all'): object {
        $offset = ($page - 1) * $per_page;
        $date_condition = $this->build_date_condition($date_range, 'v.date_created');
        
        $result = new stdClass();
        
        // Build voter condition
        if ($user_id > 0) {
            $voter_condition = $this->wpdb->prepare("v.user_id = %d", $user_id);
        } else {
            $voter_condition = $this->wpdb->prepare("v.user_id = 0 AND v.user_ip = %s", $user_ip);
        }
        
        // Total count
        $result->total_count = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->votes_table} v 
             WHERE {$voter_condition} {$date_condition}"
        );
        
        $result->total_pages = ceil($result->total_count / $per_page);
        $result->current_page = $page;
        $result->per_page = $per_page;
        
        // Paginated votes with rating name
        $result->votes = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT v.*, r.name as rating_name, r.parent_id, r.effect_type, r.rating_type, r.scale
             FROM {$this->votes_table} v
             JOIN {$this->ratings_table} r ON v.rating_id = r.id
             WHERE {$voter_condition} {$date_condition}
             ORDER BY v.date_created DESC
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
        
        return $result;
    }

    /**
     * Get voting statistics for a specific voter
     *
     * @param int $user_id User ID (0 for guests)
     * @param string|null $user_ip IP address for guest identification
     * @param string|array $date_range Date range filter
     * @return object Object with voting statistics
     */
    public function get_voter_stats(int $user_id, ?string $user_ip = null, string|int|array $date_range = 'all'): object {
        $date_condition = $this->build_date_condition($date_range);
        
        // Build voter condition
        if ($user_id > 0) {
            $voter_condition = $this->wpdb->prepare("v.user_id = %d", $user_id);
        } else {
            $voter_condition = $this->wpdb->prepare("v.user_id = 0 AND v.user_ip = %s", $user_ip);
        }
        
        // Replace v.date_created in date condition with proper alias
        $date_condition_aliased = str_replace('date_created', 'v.date_created', $date_condition);
        
        // Calculate effect-aware rating values:
        // For positive effect ratings: use rating_value as-is
        // For negative effect ratings: invert using scale-aware formula
        // Excludes binary types from average since their 0/1 values are incompatible with star scales
        $stats = $this->wpdb->get_row(
            "SELECT 
                COUNT(*) as total_votes,
                ROUND(AVG(v.rating_value), 1) as average_rating_given,
                ROUND(AVG(
                    CASE 
                        WHEN r.rating_type IN ('like_dislike', 'approval') THEN NULL
                        WHEN r.effect_type = 'negative' THEN (CAST(r.scale AS SIGNED) + 1) - v.rating_value
                        ELSE v.rating_value 
                    END
                ), 1) as average_effective_rating,
                MIN(v.date_created) as first_vote,
                MAX(v.date_created) as last_vote,
                COUNT(DISTINCT v.rating_id) as unique_items_rated,
                SUM(CASE WHEN r.rating_type NOT IN ('like_dislike', 'approval') AND v.rating_value >= CEIL(r.scale * 0.8) THEN 1 ELSE 0 END) as positive_votes,
                SUM(CASE WHEN r.rating_type NOT IN ('like_dislike', 'approval') AND v.rating_value <= CEIL(r.scale * 0.4) THEN 1 ELSE 0 END) as negative_votes,
                SUM(CASE WHEN r.rating_type NOT IN ('like_dislike', 'approval') AND v.rating_value > CEIL(r.scale * 0.4) AND v.rating_value < CEIL(r.scale * 0.8) THEN 1 ELSE 0 END) as neutral_votes
             FROM {$this->votes_table} v
             LEFT JOIN {$this->ratings_table} r ON v.rating_id = r.id
             WHERE {$voter_condition} {$date_condition_aliased}"
        );
        
        // Calculate voting tendency based on EFFECTIVE rating (considers negative effect ratings)
        if ($stats && $stats->total_votes > 0) {
            $effective_avg = floatval($stats->average_effective_rating);
            if ($effective_avg >= 4.0) {
                $stats->voting_tendency = 'generous';
            } elseif ($effective_avg <= 2.5) {
                $stats->voting_tendency = 'critical';
            } else {
                $stats->voting_tendency = 'balanced';
            }
        } else {
            $stats->voting_tendency = 'none';
        }
        
        return $stats;
    }

    /**
     * Get rating distribution for a specific voter
     *
     * @param int $user_id User ID (0 for guests)
     * @param string|null $user_ip IP address for guest identification
     * @param string|array $date_range Date range filter
     * @return array Distribution array with keys 1-5
     */
    public function get_voter_rating_distribution(int $user_id, ?string $user_ip = null, string|int|array $date_range = 'all'): array {
        $date_condition = $this->build_date_condition($date_range, 'v.date_created');
        
        // Build voter condition
        if ($user_id > 0) {
            $voter_condition = $this->wpdb->prepare("v.user_id = %d", $user_id);
        } else {
            $voter_condition = $this->wpdb->prepare("v.user_id = 0 AND v.user_ip = %s", $user_ip);
        }

        // Exclude binary types from the star distribution — they use 0/1 values
        $results = $this->wpdb->get_results(
            "SELECT v.rating_value, COUNT(*) as count 
             FROM {$this->votes_table} v
             JOIN {$this->ratings_table} r ON v.rating_id = r.id
             WHERE {$voter_condition} {$date_condition}
               AND r.rating_type NOT IN ('like_dislike', 'approval')
             GROUP BY v.rating_value 
             ORDER BY v.rating_value"
        );

        // Use the max scale across the voter's rated items for bucket size
        $max_scale = (int) $this->wpdb->get_var(
            "SELECT MAX(r.scale) FROM {$this->votes_table} v
             JOIN {$this->ratings_table} r ON v.rating_id = r.id
             WHERE {$voter_condition}
               AND r.rating_type NOT IN ('like_dislike', 'approval')"
        );
        if ($max_scale < 1) {
            $max_scale = Shuriken_Database::RATING_SCALE_DEFAULT;
        }
        
        $distribution = array_fill(1, $max_scale, 0);
        foreach ($results as $row) {
            $key = intval($row->rating_value);
            if (array_key_exists($key, $distribution)) {
                $distribution[$key] = intval($row->count);
            }
        }
        
        return $distribution;
    }

    /**
     * Get voter's activity over time
     *
     * @param int $user_id User ID (0 for guests)
     * @param string|null $user_ip IP address for guest identification
     * @param string|array $date_range Date range filter
     * @return array Array of objects with vote_date and vote_count
     */
    public function get_voter_activity_over_time(int $user_id, ?string $user_ip = null, string|int|array $date_range = 30): array {
        $date_condition = $this->build_date_condition($date_range);
        
        // Build voter condition
        if ($user_id > 0) {
            $voter_condition = $this->wpdb->prepare("user_id = %d", $user_id);
        } else {
            $voter_condition = $this->wpdb->prepare("user_id = 0 AND user_ip = %s", $user_ip);
        }
        
        return $this->wpdb->get_results(
            "SELECT DATE(date_created) as vote_date, COUNT(*) as vote_count 
             FROM {$this->votes_table}
             WHERE {$voter_condition} {$date_condition}
             GROUP BY DATE(date_created) 
             ORDER BY vote_date"
        );
    }

    /**
     * Get WordPress user info
     *
     * @param int $user_id WordPress user ID
     * @return object|null User object with display_name, email, etc. or null
     */
    public function get_user_info(int $user_id): ?object {
        if ($user_id <= 0) {
            return null;
        }
        
        $user = get_userdata($user_id);
        if (!$user) {
            return null;
        }
        
        $info = new stdClass();
        $info->ID = $user->ID;
        $info->display_name = $user->display_name;
        $info->user_email = $user->user_email;
        $info->user_login = $user->user_login;
        $info->user_registered = $user->user_registered;
        $info->roles = $user->roles;
        $info->avatar_url = get_avatar_url($user->ID, array('size' => 96));
        
        return $info;
    }

    /**
     * Get all votes for a voter for export
     *
     * @param int $user_id User ID (0 for guests)
     * @param string|null $user_ip IP address for guest identification
     * @return array Array of vote objects with rating info
     */
    public function get_voter_votes_for_export(int $user_id, ?string $user_ip = null): array {
        // Build voter condition
        if ($user_id > 0) {
            $voter_condition = $this->wpdb->prepare("v.user_id = %d", $user_id);
        } else {
            $voter_condition = $this->wpdb->prepare("v.user_id = 0 AND v.user_ip = %s", $user_ip);
        }
        
        return $this->wpdb->get_results(
            "SELECT v.id, v.rating_value, v.date_created, v.date_modified,
                    r.id as rating_id, r.name as rating_name
             FROM {$this->votes_table} v
             JOIN {$this->ratings_table} r ON v.rating_id = r.id
             WHERE {$voter_condition}
             ORDER BY v.date_created DESC"
        );
    }
}
