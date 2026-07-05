<?php
/**
 * Shuriken Reviews Analytics Rating Stats
 *
 * Per-rating statistics and breakdown queries extracted from Shuriken_Analytics.
 *
 * @package Shuriken_Reviews
 * @since 1.15.6
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shuriken_Analytics_Rating_Stats implements Shuriken_Analytics_Rating_Stats_Interface {

    use Shuriken_Analytics_Helpers;

    public function __construct(
        private readonly \wpdb $wpdb,
        private readonly string $ratings_table,
        private readonly string $votes_table,
        private readonly Shuriken_Rating_Repository $db,
        private readonly Shuriken_Analytics_Dashboard $dashboard,
    ) {}

    /**
     * Get the inversion constant for a rating's scale
     *
     * Stars/numeric: value range [1, scale], inversion = (scale + 1) - value
     * Binary (like_dislike, approval): value range [0, 1], inversion = 1 - value
     *
     * @param string $rating_type Rating type
     * @param int $scale Rating scale
     * @return int The constant C where inverted_value = C - original_value
     */
    private function get_inversion_constant(string $rating_type, int $scale): int {
        return $this->is_binary_type($rating_type) ? (int) $scale : (Shuriken_Database::RATING_SCALE_DEFAULT + 1);
    }
    /**
     * Parse date range from request parameters
     * 
     * Converts GET/POST parameters into a date range value that can be passed to build_date_condition.
     * Handles both preset ranges (7, 30, 90, 365, all) and custom date ranges.
     *
     * @param array $params Request parameters (typically $_GET)
     * @return string|array Date range value ('all', number of days, or array with start/end)
     */
    public function parse_date_range_params(array $params): string|array {
        $range_type = isset($params['range_type']) ? sanitize_text_field($params['range_type']) : 'preset';
        
        if ($range_type === 'custom') {
            $start_date = isset($params['start_date']) ? sanitize_text_field($params['start_date']) : '';
            $end_date = isset($params['end_date']) ? sanitize_text_field($params['end_date']) : '';
            
            // Validate dates
            $valid_start = preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date);
            $valid_end = preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date);
            
            if ($valid_start || $valid_end) {
                return array(
                    'start' => $valid_start ? $start_date : '',
                    'end' => $valid_end ? $end_date : ''
                );
            }
        }
        
        // Preset range
        $date_range = isset($params['date_range']) ? sanitize_text_field($params['date_range']) : '30';
        $valid_ranges = array('7', '30', '90', '365', 'all');
        
        if (!in_array($date_range, $valid_ranges, true)) {
            $date_range = '30';
        }
        
        return $date_range;
    }
    public function get_sub_ratings_contribution(int $parent_id): array {
        $parent = $this->db->get_rating($parent_id);
        if (!$parent || $parent->total_votes == 0) {
            return array();
        }
        
        $subs = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT id, name, total_votes, total_rating, effect_type, rating_type, scale,
                    ROUND(total_rating / NULLIF(total_votes, 0), 4) as average,
                    ROUND((total_votes / %d) * 100, 1) as vote_contribution_percent
             FROM {$this->ratings_table}
             WHERE parent_id = %d
             ORDER BY total_votes DESC",
            $parent->total_votes,
            $parent_id
        ));
        
        // Calculate effective contribution to parent score
        foreach ($subs as &$sub) {
            if ($sub->total_votes > 0) {
                $sub_average = $sub->total_rating / $sub->total_votes;
                if ($sub->effect_type === 'negative') {
                    $inv = $this->get_inversion_constant($sub->rating_type, $sub->scale);
                    $sub->effective_average = $inv - $sub_average;
                } else {
                    $sub->effective_average = $sub_average;
                }
                $sub->effective_average = round($sub->effective_average, 1);
            } else {
                $sub->effective_average = 0;
            }
        }
        
        return $subs;
    }

    /**
     * Get rating distribution (count of each star value)
     * 
     * For sub-ratings with negative effect_type, the rating values are inverted
     * to show the effective contribution (1★→5★, 2★→4★, 3★→3★, 4★→2★, 5★→1★).
     *
     * @param string|int $date_range Number of days or 'all'
     * @param int|null $rating_id Optional specific rating ID
     * @return array Associative array with vote value keys and counts
     */
    public function get_rating_distribution(string|int|array $date_range = 'all', ?int $rating_id = null, ?string $scope = null): array {
        $date_condition = $this->build_date_condition($date_range, 'v.date_created');
        $rating_condition = $rating_id ? $this->wpdb->prepare(" AND v.rating_id = %d", $rating_id) : '';
        $scope_condition = $this->build_scope_condition($scope, 'v.');

        // Determine the target rating's type and scale for proper bucketing
        $rating_type = 'stars';
        $scale = Shuriken_Database::RATING_SCALE_DEFAULT;
        if ($rating_id) {
            $rating = $this->db->get_rating($rating_id);
            if ($rating) {
                $rating_type = $rating->rating_type ?: 'stars';
                $scale = (int) ($rating->scale ?: Shuriken_Database::RATING_SCALE_DEFAULT);
            }
        }

        // When aggregating globally (no rating_id), exclude binary types from the distribution
        // since their 0/1 values are incompatible with star-scale buckets
        $type_condition = '';
        if (!$rating_id) {
            $type_condition = " AND r.rating_type NOT IN ('like_dislike', 'approval')";
        }

        // Join with ratings table to get effect_type and invert negative effect votes
        // Votes are normalized to 1-5 internally, so inversion uses RATING_SCALE_DEFAULT
        $internal_scale = Shuriken_Database::RATING_SCALE_DEFAULT;
        $results = $this->wpdb->get_results(
            "SELECT 
                CASE 
                    WHEN r.effect_type = 'negative' AND r.rating_type IN ('like_dislike', 'approval') 
                        THEN CAST(r.scale AS SIGNED) - v.rating_value
                    WHEN r.effect_type = 'negative' 
                        THEN ({$internal_scale} + 1) - ROUND(v.rating_value) 
                    ELSE ROUND(v.rating_value) 
                END as effective_value, 
                COUNT(*) as count 
             FROM {$this->votes_table} v
             JOIN {$this->ratings_table} r ON v.rating_id = r.id
             WHERE 1=1 {$date_condition} {$rating_condition} {$type_condition} {$scope_condition}
             GROUP BY effective_value 
             ORDER BY effective_value"
        );
        
        // Build distribution with type-appropriate buckets
        $distribution = $this->build_empty_distribution($rating_type, $scale);
        foreach ($results as $row) {
            $key = intval($row->effective_value);
            if (array_key_exists($key, $distribution)) {
                $distribution[$key] = intval($row->count);
            }
        }
        
        return $distribution;
    }

    /**
     * Get votes over time
     *
     * @param string|int|array $date_range Number of days, 'all', or array with start/end
     * @param int|null $rating_id Optional specific rating ID
     * @return array Array of objects with vote_date and vote_count
     */
    public function get_votes_over_time(string|int|array $date_range = 30, ?int $rating_id = null, ?string $scope = null): array {
        $date_condition = $this->build_date_condition($date_range);
        $rating_condition = $rating_id ? $this->wpdb->prepare(" AND rating_id = %d", $rating_id) : '';
        $scope_condition = $this->build_scope_condition($scope);
        
        return $this->wpdb->get_results(
            "SELECT DATE(date_created) as vote_date, COUNT(*) as vote_count 
             FROM {$this->votes_table} 
             WHERE 1=1 {$date_condition} {$rating_condition} {$scope_condition}
             GROUP BY DATE(date_created) 
             ORDER BY vote_date"
        );
    }

    /**
     * Get recent votes/activity
     *
     * @param int $limit Maximum number of votes to return
     * @param int|null $rating_id Optional specific rating ID
     * @param string|int|array $date_range Date range filter
     * @return array Array of vote objects with rating info
     */
    public function get_recent_votes(int $limit = 10, ?int $rating_id = null, string|int|array $date_range = 'all'): array {
        $rating_condition = $rating_id ? $this->wpdb->prepare("AND v.rating_id = %d", $rating_id) : '';
        $date_condition = $this->build_date_condition($date_range, 'v.date_created');
        
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT v.id, v.rating_id, v.rating_value, v.date_created, v.user_id, v.user_ip,
                    v.context_id, v.context_type,
                    r.name as rating_name, r.rating_type, r.scale, u.display_name, u.user_email
             FROM {$this->votes_table} v
             JOIN {$this->ratings_table} r ON v.rating_id = r.id
             LEFT JOIN {$this->wpdb->users} u ON v.user_id = u.ID
             WHERE 1=1 {$rating_condition} {$date_condition}
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
    public function get_rating(int $rating_id): ?object {
        return $this->db->get_rating($rating_id);
    }

    /**
     * Get detailed stats for a single rating item
     *
     * @param int $rating_id Rating ID
     * @param string|int|array $date_range Date range filter
     * @return object|null Object with comprehensive stats or null
     */
    public function get_rating_stats(int $rating_id, string|int|array $date_range = 'all', ?string $scope = null): ?object {
        $rating = $this->get_rating($rating_id);
        if (!$rating) {
            return null;
        }
        
        $date_condition = $this->build_date_condition($date_range);
        $scope_condition = $this->build_scope_condition($scope);
        
        $stats = new stdClass();
        $stats->rating = $rating;
        
        // If filtering by date or scope, recalculate average from filtered votes
        if (!empty($date_condition) || $scope !== null) {
            $filtered_totals = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT COUNT(*) as total_votes, COALESCE(SUM(rating_value), 0) as total_rating
                 FROM {$this->votes_table} WHERE rating_id = %d {$date_condition} {$scope_condition}",
                $rating_id
            ));
            $stats->total_votes = (int) $filtered_totals->total_votes;
            $stats->total_rating = (int) $filtered_totals->total_rating;
            $stats->average = $stats->total_votes > 0 ? round($stats->total_rating / $stats->total_votes, 1) : 0;
        } else {
            $stats->total_votes = $rating->total_votes;
            $stats->total_rating = $rating->total_rating;
            $stats->average = $rating->average;
        }
        
        $scale = isset($rating->scale) ? (int) $rating->scale : Shuriken_Database::RATING_SCALE_DEFAULT;
        $stats->display_average = Shuriken_Database::denormalize_average((float) $stats->average, $scale);
        
        // Vote counts by user type
        $stats->member_votes = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->votes_table} WHERE rating_id = %d AND user_id > 0 {$date_condition} {$scope_condition}",
            $rating_id
        ));
        
        $stats->guest_votes = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->votes_table} WHERE rating_id = %d AND user_id = 0 {$date_condition} {$scope_condition}",
            $rating_id
        ));
        
        // Unique voters
        $stats->unique_voters = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(DISTINCT CASE WHEN user_id > 0 THEN user_id ELSE user_ip END) 
             FROM {$this->votes_table} WHERE rating_id = %d {$date_condition} {$scope_condition}",
            $rating_id
        ));
        
        // First and last vote (within date range)
        $stats->first_vote = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT MIN(date_created) FROM {$this->votes_table} WHERE rating_id = %d {$date_condition} {$scope_condition}",
            $rating_id
        ));
        
        $stats->last_vote = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT MAX(date_created) FROM {$this->votes_table} WHERE rating_id = %d {$date_condition} {$scope_condition}",
            $rating_id
        ));
        
        // Distribution and timeline (use same date range and scope)
        $stats->distribution = $this->get_rating_distribution($date_range, $rating_id, $scope);
        $stats->votes_over_time = $this->get_votes_over_time($date_range, $rating_id, $scope);
        
        return $stats;
    }

    /**
     * Get detailed stats breakdown for a parent rating
     * 
     * Returns stats from three sources:
     * - direct: Votes directly on the parent rating
     * - subs: Aggregated stats from sub-ratings
     * - total: Combined total
     *
     * @param int $rating_id Parent rating ID
     * @return object|null Object with direct, subs, and total stats or null
     */
    /**
     * Get detailed stats breakdown for a parent rating
     * 
     * Returns stats from three sources:
     * - direct: Votes directly on the parent rating
     * - subs: Aggregated stats from sub-ratings
     * - total: Combined total
     *
     * @param int $rating_id Parent rating ID
     * @param string|int|array $date_range Date range filter
     * @param string|null $scope Scope filter
     * @return object|null Object with direct, subs, and total stats or null
     */
    public function get_parent_rating_stats_breakdown(int $rating_id, string|int|array $date_range = 'all', ?string $scope = null): ?object {
        $rating = $this->get_rating($rating_id);
        if (!$rating) {
            return null;
        }
        
        // Check if this is actually a parent rating
        $sub_rating_ids = $this->wpdb->get_col($this->wpdb->prepare(
            "SELECT id FROM {$this->ratings_table} WHERE parent_id = %d",
            $rating_id
        ));
        
        if (empty($sub_rating_ids)) {
            return null; // Not a parent rating
        }
        
        // Build date and scope conditions
        $date_condition = $this->build_date_condition($date_range, 'date_created');
        $date_condition_v = $this->build_date_condition($date_range, 'v.date_created');
        $scope_condition = $this->build_scope_condition($scope);
        $scope_condition_v = $this->build_scope_condition($scope, 'v.');
        
        $scale = isset($rating->scale) ? (int) $rating->scale : Shuriken_Database::RATING_SCALE_DEFAULT;
        $parent_type = $rating->rating_type ?: 'stars';
        $parent_scale = (int) ($rating->scale ?: Shuriken_Database::RATING_SCALE_DEFAULT);
        
        // === DIRECT VOTES ===
        $direct = $this->get_direct_votes_breakdown($rating_id, $scale, $date_condition, $scope_condition, $date_range, $scope);
        
        // === SUB-RATINGS AGGREGATED ===
        $sub_ratings_info = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT id, effect_type, rating_type, scale FROM {$this->ratings_table} WHERE parent_id = %d",
            $rating_id
        ));
        $subs_totals = $this->calculate_sub_ratings_rating_totals($sub_ratings_info, $date_condition, $scope_condition);
        $subs = $this->get_sub_ratings_breakdown(
            $sub_rating_ids,
            $subs_totals,
            $date_condition,
            $scope_condition,
            $date_condition_v,
            $scope_condition_v,
            $date_range,
            $scope,
            $parent_type,
            $parent_scale,
            $scale
        );
        
        // === TOTAL (Combined) ===
        $total = $this->combine_votes_breakdown(
            $rating_id,
            $sub_rating_ids,
            $scale,
            $parent_type,
            $parent_scale,
            $date_condition,
            $scope_condition,
            $date_range,
            $scope,
            $direct,
            $subs,
            $subs_totals->subs_total_rating
        );
        
        $breakdown = new stdClass();
        $breakdown->direct = $direct;
        $breakdown->subs = $subs;
        $breakdown->total = $total;
        
        return $breakdown;
    }

    /**
     * Get direct votes breakdown stats
     */
    private function get_direct_votes_breakdown(int $rating_id, int $scale, string $date_condition, string $scope_condition, string|int|array $date_range, ?string $scope): stdClass {
        $direct = new stdClass();
        
        $direct_totals = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT COUNT(*) as total_votes, COALESCE(SUM(rating_value), 0) as total_rating
             FROM {$this->votes_table} WHERE rating_id = %d {$date_condition} {$scope_condition}",
            $rating_id
        ));
        
        $direct->total_votes = (int) $direct_totals->total_votes;
        $direct->total_rating = (int) $direct_totals->total_rating;
        $direct->average = $direct->total_votes > 0 
            ? round($direct->total_rating / $direct->total_votes, 1) 
            : 0;
        $direct->display_average = Shuriken_Database::denormalize_average((float) $direct->average, $scale);
        
        $direct->member_votes = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->votes_table} WHERE rating_id = %d AND user_id > 0 {$date_condition} {$scope_condition}",
            $rating_id
        ));
        
        $direct->guest_votes = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->votes_table} WHERE rating_id = %d AND user_id = 0 {$date_condition} {$scope_condition}",
            $rating_id
        ));
        
        $direct->unique_voters = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(DISTINCT CASE WHEN user_id > 0 THEN user_id ELSE user_ip END) 
             FROM {$this->votes_table} WHERE rating_id = %d {$date_condition} {$scope_condition}",
            $rating_id
        ));
        
        $direct->first_vote = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT MIN(date_created) FROM {$this->votes_table} WHERE rating_id = %d {$date_condition} {$scope_condition}",
            $rating_id
        ));
        
        $direct->last_vote = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT MAX(date_created) FROM {$this->votes_table} WHERE rating_id = %d {$date_condition} {$scope_condition}",
            $rating_id
        ));
        
        $direct->distribution = $this->get_rating_distribution($date_range, $rating_id, $scope);
        $direct->votes_over_time = $this->get_votes_over_time($date_range, $rating_id, $scope);
        
        return $direct;
    }

    /**
     * Calculate effective totals from sub-ratings with date+scope filter
     */
    private function calculate_sub_ratings_rating_totals(array $sub_ratings_info, string $date_condition, string $scope_condition): stdClass {
        $totals = new stdClass();
        $totals->subs_total_votes = 0;
        $totals->subs_total_rating = 0;
        
        foreach ($sub_ratings_info as $sub) {
            $sub_totals = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT COUNT(*) as total_votes, COALESCE(SUM(rating_value), 0) as total_rating
                 FROM {$this->votes_table} WHERE rating_id = %d {$date_condition} {$scope_condition}",
                $sub->id
            ));
            
            if ($sub_totals->total_votes > 0) {
                $totals->subs_total_votes += (int) $sub_totals->total_votes;
                if ($sub->effect_type === 'negative') {
                    $inv = $this->get_inversion_constant($sub->rating_type, $sub->scale);
                    $inverted_rating = ($sub_totals->total_votes * $inv) - $sub_totals->total_rating;
                    $totals->subs_total_rating += $inverted_rating;
                } else {
                    $totals->subs_total_rating += (int) $sub_totals->total_rating;
                }
            }
        }
        
        return $totals;
    }

    /**
     * Get sub-ratings aggregated breakdown stats
     */
    private function get_sub_ratings_breakdown(
        array $sub_rating_ids,
        stdClass $subs_totals,
        string $date_condition,
        string $scope_condition,
        string $date_condition_v,
        string $scope_condition_v,
        string|int|array $date_range,
        ?string $scope,
        string $parent_type,
        int $parent_scale,
        int $scale
    ): stdClass {
        $subs = new stdClass();
        $sub_ids_placeholder = implode(',', array_map('intval', $sub_rating_ids));
        
        $subs->total_votes = $subs_totals->subs_total_votes;
        $subs->total_rating = $subs_totals->subs_total_rating;
        $subs->average = $subs->total_votes > 0 
            ? round($subs->total_rating / $subs->total_votes, 1) 
            : 0;
        $subs->display_average = Shuriken_Database::denormalize_average((float) $subs->average, $scale);
        
        $subs->member_votes = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->votes_table} 
             WHERE rating_id IN ({$sub_ids_placeholder}) AND user_id > 0 {$date_condition} {$scope_condition}"
        );
        
        $subs->guest_votes = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->votes_table} 
             WHERE rating_id IN ({$sub_ids_placeholder}) AND user_id = 0 {$date_condition} {$scope_condition}"
        );
        
        $subs->unique_voters = (int) $this->wpdb->get_var(
            "SELECT COUNT(DISTINCT CASE WHEN user_id > 0 THEN user_id ELSE user_ip END) 
             FROM {$this->votes_table} WHERE rating_id IN ({$sub_ids_placeholder}) {$date_condition} {$scope_condition}"
        );
        
        $subs->first_vote = $this->wpdb->get_var(
            "SELECT MIN(date_created) FROM {$this->votes_table} WHERE rating_id IN ({$sub_ids_placeholder}) {$date_condition} {$scope_condition}"
        );
        
        $subs->last_vote = $this->wpdb->get_var(
            "SELECT MAX(date_created) FROM {$this->votes_table} WHERE rating_id IN ({$sub_ids_placeholder}) {$date_condition} {$scope_condition}"
        );
        
        // Distribution from sub-ratings
        $internal_scale = Shuriken_Database::RATING_SCALE_DEFAULT;
        $subs_distribution_results = $this->wpdb->get_results(
            "SELECT 
                CASE 
                    WHEN r.effect_type = 'negative' AND r.rating_type IN ('like_dislike', 'approval') 
                        THEN CAST(r.scale AS SIGNED) - v.rating_value
                    WHEN r.effect_type = 'negative' 
                        THEN ({$internal_scale} + 1) - v.rating_value 
                    ELSE v.rating_value 
                END as effective_value, 
                COUNT(*) as count 
             FROM {$this->votes_table} v
             JOIN {$this->ratings_table} r ON v.rating_id = r.id
             WHERE v.rating_id IN ({$sub_ids_placeholder}) {$date_condition_v} {$scope_condition_v}
             GROUP BY effective_value 
             ORDER BY effective_value"
        );

        $subs->distribution = $this->build_empty_distribution($parent_type, $parent_scale);
        foreach ($subs_distribution_results as $row) {
            $key = intval($row->effective_value);
            if (array_key_exists($key, $subs->distribution)) {
                $subs->distribution[$key] = intval($row->count);
            }
        }
        
        $subs->votes_over_time = $this->get_votes_over_time_for_ids($sub_rating_ids, $date_range, $scope);
        
        return $subs;
    }

    /**
     * Combine direct and sub-ratings breakdown stats
     */
    private function combine_votes_breakdown(
        int $rating_id,
        array $sub_rating_ids,
        int $scale,
        string $parent_type,
        int $parent_scale,
        string $date_condition,
        string $scope_condition,
        string|int|array $date_range,
        ?string $scope,
        stdClass $direct,
        stdClass $subs,
        int $subs_total_rating
    ): stdClass {
        $total = new stdClass();
        
        $total->total_votes = $direct->total_votes + $subs->total_votes;
        $total->total_rating = $direct->total_rating + $subs_total_rating;
        $total->average = $total->total_votes > 0 
            ? round($total->total_rating / $total->total_votes, 1) 
            : 0;
        $total->display_average = Shuriken_Database::denormalize_average((float) $total->average, $scale);
        
        $total->member_votes = $direct->member_votes + $subs->member_votes;
        $total->guest_votes = $direct->guest_votes + $subs->guest_votes;
        
        // Unique voters across all
        $all_rating_ids = array_merge(array($rating_id), $sub_rating_ids);
        $all_ids_placeholder = implode(',', array_map('intval', $all_rating_ids));
        
        $total->unique_voters = (int) $this->wpdb->get_var(
            "SELECT COUNT(DISTINCT CASE WHEN user_id > 0 THEN user_id ELSE user_ip END) 
             FROM {$this->votes_table} WHERE rating_id IN ({$all_ids_placeholder}) {$date_condition} {$scope_condition}"
        );
        
        // First and last vote across all
        $total->first_vote = min(
            $direct->first_vote ?: PHP_INT_MAX,
            $subs->first_vote ?: PHP_INT_MAX
        );
        $total->first_vote = $total->first_vote === PHP_INT_MAX ? null : $total->first_vote;
        
        $total->last_vote = max(
            $direct->last_vote ?: '',
            $subs->last_vote ?: ''
        ) ?: null;
        
        // Combined distribution
        $total->distribution = $this->build_empty_distribution($parent_type, $parent_scale);
        foreach ($total->distribution as $key => &$val) {
            $direct_val = isset($direct->distribution[$key]) ? $direct->distribution[$key] : 0;
            $subs_val = isset($subs->distribution[$key]) ? $subs->distribution[$key] : 0;
            $val = $direct_val + $subs_val;
        }
        unset($val);
        
        // Combined votes over time
        $total->votes_over_time = $this->get_votes_over_time_for_ids($all_rating_ids, $date_range, $scope);
        
        return $total;
    }
    
    /**
     * Get votes over time for multiple rating IDs
     *
     * @param array $rating_ids Array of rating IDs
     * @param mixed $date_range Date range (days, 'all', or array with 'start'/'end')
     * @return array Array of vote date/count objects
     */
    private function get_votes_over_time_for_ids(array $rating_ids, string|int|array $date_range = 30, ?string $scope = null): array {
        if (empty($rating_ids)) {
            return array();
        }
        
        $ids_placeholder = implode(',', array_map('intval', $rating_ids));
        $date_condition = $this->build_date_condition($date_range, 'date_created');
        $scope_condition = $this->build_scope_condition($scope);
        
        return $this->wpdb->get_results(
            "SELECT DATE(date_created) as vote_date, COUNT(*) as vote_count 
             FROM {$this->votes_table} 
             WHERE rating_id IN ({$ids_placeholder}) {$date_condition} {$scope_condition}
             GROUP BY DATE(date_created) 
             ORDER BY vote_date"
        );
    }

    /**
     * Get paginated votes for a rating
     *
     * @param int $rating_id Rating ID
     * @param int $page Current page (1-indexed)
     * @param int $per_page Items per page
     * @param string|array $date_range Date range filter ('all', days, or array with start/end)
     * @param string $view For parent ratings: 'direct', 'subs', or 'total'
     * @return object Object with votes array, total_count, total_pages, current_page
     */
    public function get_rating_votes_paginated(int $rating_id, int $page = 1, int $per_page = 20, string|int|array $date_range = 'all', string $view = 'direct', string $sort_by = 'date', string $sort_order = 'desc', ?string $scope = null): object {
        $offset = ($page - 1) * $per_page;
        
        $result = new stdClass();
        
        // Build date and scope conditions
        $date_condition = $this->build_date_condition($date_range, 'v.date_created');
        $scope_condition = $this->build_scope_condition($scope, 'v.');
        
        // Safe sort column map
        $sort_col_map = array('rating' => 'v.rating_value', 'date' => 'v.date_created');
        $sort_col = $sort_col_map[$sort_by] ?? 'v.date_created';
        $sort_dir = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';
        
        // Determine which rating IDs to query based on view
        $rating_ids = array($rating_id);
        if (in_array($view, array('subs', 'total'))) {
            // Get sub-rating IDs
            $sub_rating_ids = $this->wpdb->get_col($this->wpdb->prepare(
                "SELECT id FROM {$this->ratings_table} WHERE parent_id = %d",
                $rating_id
            ));
            
            if (!empty($sub_rating_ids)) {
                if ($view === 'subs') {
                    // Only subs, not direct
                    $rating_ids = $sub_rating_ids;
                } else {
                    // total: both direct and subs
                    $rating_ids = array_merge(array($rating_id), $sub_rating_ids);
                }
            }
        }
        
        $rating_ids_placeholder = implode(',', array_map('intval', $rating_ids));
        
        $result->total_count = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->votes_table} v WHERE v.rating_id IN ({$rating_ids_placeholder}) {$date_condition} {$scope_condition}"
        );
        
        $result->total_pages = ceil($result->total_count / $per_page);
        $result->current_page = $page;
        $result->per_page = $per_page;
        
        $result->votes = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT v.*, u.display_name, u.user_email, r.name as rating_name, r.rating_type, r.scale
             FROM {$this->votes_table} v
             LEFT JOIN {$this->wpdb->users} u ON v.user_id = u.ID
             LEFT JOIN {$this->ratings_table} r ON v.rating_id = r.id
             WHERE v.rating_id IN ({$rating_ids_placeholder}) {$date_condition} {$scope_condition}
             ORDER BY {$sort_col} {$sort_dir}
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
        
        // Mark which votes are from sub-ratings vs direct (for parent rating views)
        $result->is_multi_rating = count($rating_ids) > 1;
        $result->parent_rating_id = $rating_id;
        
        return $result;
    }

    /**
     * Get data formatted for charts (Chart.js compatible)
     *
     * @param string|int $date_range Number of days or 'all'
     * @param int|null $rating_id Optional specific rating ID
     * @return array Array with distribution, votes_over_time, user_types
     */
    public function get_chart_data(string|int|array $date_range = 30, ?int $rating_id = null): array {
        $vote_counts = $rating_id ? null : $this->dashboard->get_vote_counts($date_range);
        
        return array(
            'distribution' => array_values($this->get_rating_distribution($date_range, $rating_id)),
            'votes_over_time' => $this->get_votes_over_time($date_range, $rating_id),
            'user_types' => $rating_id ? null : array(
                'members' => $vote_counts->member_votes,
                'guests' => $vote_counts->guest_votes,
            ),
        );
    }

    /**
     * Check if a rating has sub-ratings
     *
     * @param int $rating_id Rating ID to check
     * @return bool True if rating has sub-ratings
     */
    public function has_sub_ratings(int $rating_id): bool {
        $count = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->ratings_table} WHERE parent_id = %d",
            $rating_id
        ));
        return $count > 0;
    }
    public function get_approval_trend(int $rating_id, string|int|array $date_range = 30, ?string $scope = null): array {
        $date_condition = $this->build_date_condition($date_range);
        $scope_condition = $this->build_scope_condition($scope);

        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DATE(date_created) as vote_date,
                    COUNT(*) as total,
                    SUM(rating_value) as likes,
                    ROUND((SUM(rating_value) / COUNT(*)) * 100, 1) as approval_rate
             FROM {$this->votes_table}
             WHERE rating_id = %d {$date_condition} {$scope_condition}
             GROUP BY DATE(date_created)
             ORDER BY vote_date",
            $rating_id
        ));
    }

    /**
     * Get cumulative approval count over time for an approval-type rating
     *
     * @param int $rating_id Rating ID
     * @param string|int|array $date_range Date range filter
     * @return array Array of objects with vote_date, daily_count, cumulative_count
     */
    public function get_cumulative_approvals(int $rating_id, string|int|array $date_range = 30, ?string $scope = null): array {
        $date_condition = $this->build_date_condition($date_range);
        $scope_condition = $this->build_scope_condition($scope);

        $daily = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DATE(date_created) as vote_date, COUNT(*) as daily_count
             FROM {$this->votes_table}
             WHERE rating_id = %d {$date_condition} {$scope_condition}
             GROUP BY DATE(date_created)
             ORDER BY vote_date",
            $rating_id
        ));

        // Build cumulative
        $cumulative = 0;
        foreach ($daily as &$row) {
            $cumulative += (int) $row->daily_count;
            $row->cumulative_count = $cumulative;
        }
        unset($row);

        return $daily;
    }

    /**
     * Get votes over time with rolling average for stars/numeric items
     *
     * Returns daily vote counts alongside a rolling 7-day average score.
     *
     * @param int $rating_id Rating ID
     * @param string|int|array $date_range Date range filter
     * @param int $scale Display scale for denormalization
     * @return array Array of objects with vote_date, vote_count, daily_avg, display_daily_avg
     */
    public function get_votes_with_rolling_avg(int $rating_id, string|int|array $date_range = 30, int $scale = Shuriken_Database::RATING_SCALE_DEFAULT, ?string $scope = null): array {
        $date_condition = $this->build_date_condition($date_range);
        $scope_condition = $this->build_scope_condition($scope);

        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DATE(date_created) as vote_date,
                    COUNT(*) as vote_count,
                    ROUND(AVG(rating_value), 2) as daily_avg
             FROM {$this->votes_table}
             WHERE rating_id = %d {$date_condition} {$scope_condition}
             GROUP BY DATE(date_created)
             ORDER BY vote_date",
            $rating_id
        ));

        foreach ($rows as &$row) {
            $row->display_daily_avg = Shuriken_Database::denormalize_average((float) $row->daily_avg, $scale);
        }
        unset($row);

        return $rows;
    }

    /**
     * Like get_votes_with_rolling_avg but accepts multiple rating IDs (for parent ratings).
     *
     * @param array $rating_ids Array of rating IDs to include
     * @param string|int|array $date_range Date range filter
     * @param int $scale Display scale for denormalization
     * @return array Array of objects with vote_date, vote_count, daily_avg, display_daily_avg
     */
    public function get_votes_with_rolling_avg_for_ids(array $rating_ids, string|int|array $date_range = 30, int $scale = Shuriken_Database::RATING_SCALE_DEFAULT): array {
        if (empty($rating_ids)) {
            return array();
        }

        $ids_placeholder = implode(',', array_map('intval', $rating_ids));
        $date_condition = $this->build_date_condition($date_range);

        $rows = $this->wpdb->get_results(
            "SELECT DATE(date_created) as vote_date,
                    COUNT(*) as vote_count,
                    ROUND(AVG(rating_value), 2) as daily_avg
             FROM {$this->votes_table}
             WHERE rating_id IN ({$ids_placeholder}) {$date_condition}
             GROUP BY DATE(date_created)
             ORDER BY vote_date"
        );

        foreach ($rows as &$row) {
            $row->display_daily_avg = Shuriken_Database::denormalize_average((float) $row->daily_avg, $scale);
        }
        unset($row);

        return $rows;
    }

    // =========================================================================
    // Contextual / Per-Post Analytics Methods
    // =========================================================================

    /**
     * Build SQL condition for vote scope filtering (global vs contextual)
     *
     * @param string|null $scope 'global' (context_id IS NULL), 'contextual' (context_id IS NOT NULL), or null (no filter)
     * @param string      $prefix Column prefix (e.g., 'v.' for aliased queries)
     * @return string SQL condition string (includes leading AND)
     */
    public function build_scope_condition(?string $scope, string $prefix = ''): string {
        return match ($scope) {
            'global'     => " AND {$prefix}context_id IS NULL",
            'contextual' => " AND {$prefix}context_id IS NOT NULL",
            default      => '',
        };
    }
}
