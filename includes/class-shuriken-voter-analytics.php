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
    public function get_voter_votes_paginated(int $user_id, ?string $user_ip = null, int $page = 1, int $per_page = 20, string|int|array $date_range = 'all', string $orderby = 'date', string $order = 'DESC'): object {
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
        
        // Build ORDER BY clause (whitelist to prevent SQL injection)
        $order_dir = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        $order_clause = match ($orderby) {
            'rating'  => "v.rating_value {$order_dir}, v.date_created DESC",
            'item'    => "r.name {$order_dir}, v.date_created DESC",
            default   => "v.date_created {$order_dir}",
        };
        
        // Paginated votes with rating name
        $result->votes = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT v.*, r.name as rating_name, r.parent_id, r.effect_type, r.rating_type, r.scale
             FROM {$this->votes_table} v
             JOIN {$this->ratings_table} r ON v.rating_id = r.id
             WHERE {$voter_condition} {$date_condition}
             ORDER BY {$order_clause}
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
        // Excludes binary types from effective average since their 0/1 values are incompatible with star scales
        // Includes binary types in positive/negative sentiment counts
        // All v.rating_value are normalized to 1-5 (RATING_SCALE_DEFAULT) regardless of display scale
        $internal_scale = Shuriken_Database::RATING_SCALE_DEFAULT;
        $stats = $this->wpdb->get_row(
            "SELECT 
                COUNT(*) as total_votes,
                ROUND(AVG(v.rating_value), 1) as average_rating_given,
                ROUND(AVG(
                    CASE 
                        WHEN r.rating_type IN ('like_dislike', 'approval') THEN NULL
                        WHEN r.effect_type = 'negative' THEN ({$internal_scale} + 1) - v.rating_value
                        ELSE v.rating_value 
                    END
                ), 1) as average_effective_rating,
                SUM(CASE WHEN r.rating_type NOT IN ('like_dislike', 'approval') THEN 1 ELSE 0 END) as non_binary_votes,
                SUM(CASE WHEN r.rating_type IN ('like_dislike', 'approval') THEN 1 ELSE 0 END) as binary_votes,
                SUM(CASE WHEN r.rating_type IN ('like_dislike', 'approval') AND v.rating_value > 0 THEN 1 ELSE 0 END) as binary_positive,
                MIN(v.date_created) as first_vote,
                MAX(v.date_created) as last_vote,
                COUNT(DISTINCT v.rating_id) as unique_items_rated,
                SUM(CASE 
                    WHEN r.rating_type IN ('like_dislike', 'approval') AND v.rating_value > 0 THEN 1
                    WHEN r.rating_type NOT IN ('like_dislike', 'approval') AND v.rating_value >= CEIL({$internal_scale} * 0.8) THEN 1
                    ELSE 0 END) as positive_votes,
                SUM(CASE 
                    WHEN r.rating_type = 'like_dislike' AND v.rating_value = 0 THEN 1
                    WHEN r.rating_type NOT IN ('like_dislike', 'approval') AND v.rating_value <= CEIL({$internal_scale} * 0.4) THEN 1
                    ELSE 0 END) as negative_votes,
                SUM(CASE 
                    WHEN r.rating_type NOT IN ('like_dislike', 'approval') AND v.rating_value > CEIL({$internal_scale} * 0.4) AND v.rating_value < CEIL({$internal_scale} * 0.8) THEN 1
                    ELSE 0 END) as neutral_votes
             FROM {$this->votes_table} v
             LEFT JOIN {$this->ratings_table} r ON v.rating_id = r.id
             WHERE {$voter_condition} {$date_condition_aliased}"
        );
        
        // Calculate voting tendency — scale-aware for star/numeric, ratio-based for binary
        if ($stats && $stats->total_votes > 0) {
            $non_binary = (int) $stats->non_binary_votes;
            $binary = (int) $stats->binary_votes;
            
            if ($non_binary > 0 && $stats->average_effective_rating !== null) {
                // Normalize effective average (already on 1-5 internal scale) to 0-1 range
                $normalized = floatval($stats->average_effective_rating) / Shuriken_Database::RATING_SCALE_DEFAULT;
                if ($normalized >= 0.75) {
                    $stats->voting_tendency = 'generous';
                } elseif ($normalized <= 0.40) {
                    $stats->voting_tendency = 'critical';
                } else {
                    $stats->voting_tendency = 'balanced';
                }
            } elseif ($binary > 0) {
                // Binary-only voter: use like/approval ratio
                $like_ratio = (int) $stats->binary_positive / $binary;
                if ($like_ratio >= 0.70) {
                    $stats->voting_tendency = 'generous';
                } elseif ($like_ratio <= 0.30) {
                    $stats->voting_tendency = 'critical';
                } else {
                    $stats->voting_tendency = 'balanced';
                }
            } else {
                $stats->voting_tendency = 'none';
            }
        } else {
            $stats->voting_tendency = 'none';
        }
        
        return $stats;
    }

    /**
     * Get deviation-from-average distribution for a specific voter
     *
     * For each non-binary vote the voter cast, computes (voter_value − item_average)
     * and bins the result into buckets: ≤−2, −1, 0, +1, ≥+2 (rounded to nearest integer).
     * This reveals whether the voter rates higher or lower than the community consensus.
     *
     * @param int $user_id User ID (0 for guests)
     * @param string|null $user_ip IP address for guest identification
     * @param string|int|array $date_range Date range filter
     * @return array Associative array with bucket labels as keys and counts as values
     */
    public function get_voter_rating_distribution(int $user_id, ?string $user_ip = null, string|int|array $date_range = 'all'): array {
        $date_condition = $this->build_date_condition($date_range, 'v.date_created');
        
        // Build voter condition
        if ($user_id > 0) {
            $voter_condition = $this->wpdb->prepare("v.user_id = %d", $user_id);
        } else {
            $voter_condition = $this->wpdb->prepare("v.user_id = 0 AND v.user_ip = %s", $user_ip);
        }

        // For each non-binary vote, compute the deviation from the item's average rating.
        // Both voter value and item average are on the normalized 1-5 internal scale.
        $results = $this->wpdb->get_results(
            "SELECT ROUND(v.rating_value - (r.total_rating / NULLIF(r.total_votes, 0))) as deviation
             FROM {$this->votes_table} v
             JOIN {$this->ratings_table} r ON v.rating_id = r.id
             WHERE {$voter_condition} {$date_condition}
               AND r.rating_type NOT IN ('like_dislike', 'approval')
               AND r.total_votes > 0"
        );

        // Bin into five buckets: ≤-2, -1, 0, +1, ≥+2
        $distribution = array(
            '≤-2' => 0,
            '-1'  => 0,
            '0'   => 0,
            '+1'  => 0,
            '≥+2' => 0,
        );
        foreach ($results as $row) {
            $dev = intval($row->deviation);
            if ($dev <= -2) {
                $distribution['≤-2']++;
            } elseif ($dev === -1) {
                $distribution['-1']++;
            } elseif ($dev === 0) {
                $distribution['0']++;
            } elseif ($dev === 1) {
                $distribution['+1']++;
            } else {
                $distribution['≥+2']++;
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
     * Get per-type vote breakdown for a voter
     *
     * Returns vote count and type-appropriate metric for each rating type the voter has used.
     * For stars/numeric: average rating. For like_dislike/approval: approval rate.
     *
     * @param int $user_id User ID (0 for guests)
     * @param string|null $user_ip IP address for guest identification
     * @param string|int|array $date_range Date range filter
     * @return array Array of objects with rating_type, vote_count, avg_value, approval_rate
     */
    public function get_voter_type_breakdown(int $user_id, ?string $user_ip = null, string|int|array $date_range = 'all'): array {
        $date_condition = $this->build_date_condition($date_range, 'v.date_created');
        
        if ($user_id > 0) {
            $voter_condition = $this->wpdb->prepare("v.user_id = %d", $user_id);
        } else {
            $voter_condition = $this->wpdb->prepare("v.user_id = 0 AND v.user_ip = %s", $user_ip);
        }
        
        $results = $this->wpdb->get_results(
            "SELECT 
                COALESCE(r.rating_type, 'stars') as rating_type,
                COUNT(*) as vote_count,
                ROUND(AVG(v.rating_value), 1) as avg_value,
                AVG(r.scale) as avg_scale,
                SUM(CASE WHEN v.rating_value > 0 THEN 1 ELSE 0 END) as positive_count
             FROM {$this->votes_table} v
             JOIN {$this->ratings_table} r ON v.rating_id = r.id
             WHERE {$voter_condition} {$date_condition}
             GROUP BY COALESCE(r.rating_type, 'stars')
             ORDER BY vote_count DESC"
        );
        
        foreach ($results as &$row) {
            $row->vote_count = (int) $row->vote_count;
            $row->avg_value = (float) $row->avg_value;
            $row->avg_scale = (float) $row->avg_scale;
            $row->positive_count = (int) $row->positive_count;
            $row->is_binary = (Shuriken_Rating_Type::tryFrom($row->rating_type) ?? Shuriken_Rating_Type::Stars)->isBinary();
            if ($row->is_binary) {
                $row->approval_rate = $row->vote_count > 0
                    ? round(($row->positive_count / $row->vote_count) * 100)
                    : 0;
            }
        }
        unset($row);
        
        return $results;
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
