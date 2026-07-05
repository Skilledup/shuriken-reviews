<?php
/**
 * Shuriken Reviews Analytics Dashboard
 *
 * Site-wide overview analytics extracted from Shuriken_Analytics.
 *
 * @package Shuriken_Reviews
 * @since 1.15.6
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shuriken_Analytics_Dashboard implements Shuriken_Analytics_Dashboard_Interface {

    use Shuriken_Analytics_Helpers;

    public function __construct(
        private readonly \wpdb $wpdb,
        private readonly string $ratings_table,
        private readonly string $votes_table,
    ) {}

    public function get_overall_stats(): object {
        $stats = new stdClass();
        
        // Count all ratings
        $stats->total_ratings = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->ratings_table}"
        );
        
        // Get rating type breakdown
        $stats->rating_types = $this->get_rating_type_counts();
        
        // Total votes (exclude mirrors as they don't have their own votes)
        $stats->total_votes = (int) $this->wpdb->get_var(
            "SELECT COALESCE(SUM(total_votes), 0) FROM {$this->ratings_table} WHERE mirror_of IS NULL"
        );
        
        // Overall average (only from non-mirror ratings with votes)
        $stats->overall_average = (float) $this->wpdb->get_var(
            "SELECT AVG(total_rating / NULLIF(total_votes, 0)) 
             FROM {$this->ratings_table} 
             WHERE total_votes > 0 AND mirror_of IS NULL"
        );
        
        $stats->unique_voters = (int) $this->wpdb->get_var(
            "SELECT COUNT(DISTINCT CASE WHEN user_id > 0 THEN user_id ELSE user_ip END) 
             FROM {$this->votes_table}"
        );
        
        return $stats;
    }
    public function get_contextual_post_count(): int {
        return (int) $this->wpdb->get_var(
            "SELECT COUNT(DISTINCT CONCAT(context_id, ':', context_type))
             FROM {$this->votes_table}
             WHERE context_id IS NOT NULL"
        );
    }

    /**
     * Get rating type counts breakdown
     *
     * @return object Object with standalone, parent, sub, display_only, mirror counts
     */
    public function get_rating_type_counts(): object {
        $counts = new stdClass();
        
        // Standalone: no parent_id, no mirror_of, and not a parent itself
        $counts->standalone = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->ratings_table} r
             WHERE r.parent_id IS NULL 
               AND r.mirror_of IS NULL 
               AND NOT EXISTS (SELECT 1 FROM {$this->ratings_table} sub WHERE sub.parent_id = r.id)"
        );
        
        // Parent ratings: has sub-ratings
        $counts->parent = (int) $this->wpdb->get_var(
            "SELECT COUNT(DISTINCT parent_id) FROM {$this->ratings_table} WHERE parent_id IS NOT NULL"
        );
        
        // Sub-ratings
        $counts->sub = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->ratings_table} WHERE parent_id IS NOT NULL"
        );
        
        // Sub-ratings with positive effect
        $counts->sub_positive = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->ratings_table} WHERE parent_id IS NOT NULL AND effect_type = 'positive'"
        );
        
        // Sub-ratings with negative effect
        $counts->sub_negative = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->ratings_table} WHERE parent_id IS NOT NULL AND effect_type = 'negative'"
        );
        
        // Display-only ratings
        $counts->display_only = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->ratings_table} WHERE display_only = 1"
        );
        
        // Mirror ratings
        $counts->mirror = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->ratings_table} WHERE mirror_of IS NOT NULL"
        );
        
        return $counts;
    }

    /**
     * Get vote counts for a specific period
     *
     * @param string|int $date_range Number of days or 'all'
     * @return object Object containing period_votes, member_votes, guest_votes
     */
    public function get_vote_counts(string|int|array $date_range = 'all'): object {
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
     * For preset ranges (7, 30, 90, 365 days): compares to previous same-length period
     * For custom date ranges: compares to same-length period immediately before the start date
     *
     * @param string|int|array $date_range Date range (number of days or array with start/end)
     * @return float|null Percentage change or null if not applicable
     */
    public function get_vote_change_percent(string|int|array $date_range): ?float {
        // For 'all' or empty, no comparison possible
        if ($date_range === 'all' || empty($date_range)) {
            return null;
        }
        
        // Custom date range
        if (is_array($date_range)) {
            $start = !empty($date_range['start']) ? $date_range['start'] : null;
            $end = !empty($date_range['end']) ? $date_range['end'] : date('Y-m-d');
            
            if (!$start) {
                return null; // Can't calculate without a start date
            }
            
            // Calculate period length in days
            $start_time = strtotime($start);
            $end_time = strtotime($end);
            $period_days = max(1, ($end_time - $start_time) / 86400);
            
            // Previous period: same length, ending just before current start
            $prev_end = date('Y-m-d', $start_time - 86400);
            $prev_start = date('Y-m-d', $start_time - ($period_days * 86400));
            
            $current_votes = (int) $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->votes_table} 
                 WHERE date_created >= %s AND date_created <= %s",
                $start . ' 00:00:00',
                $end . ' 23:59:59'
            ));
            
            $previous_votes = (int) $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->votes_table} 
                 WHERE date_created >= %s AND date_created <= %s",
                $prev_start . ' 00:00:00',
                $prev_end . ' 23:59:59'
            ));
            
            if ($previous_votes > 0) {
                return round((($current_votes - $previous_votes) / $previous_votes) * 100, 1);
            }
            
            return $current_votes > 0 ? 100 : 0;
        }
        
        // Preset relative days
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
     * Get vote change percentage for a specific rating item compared to previous period
     *
     * @param int $rating_id Rating ID
     * @param string|int|array $date_range Date range
     * @return float|null Percentage change or null if not applicable
     */
    public function get_rating_vote_change_percent(int $rating_id, string|int|array $date_range): ?float {
        if ($date_range === 'all' || empty($date_range)) {
            return null;
        }

        if (is_array($date_range)) {
            $start = !empty($date_range['start']) ? $date_range['start'] : null;
            $end = !empty($date_range['end']) ? $date_range['end'] : date('Y-m-d');
            if (!$start) {
                return null;
            }
            $start_time = strtotime($start);
            $end_time = strtotime($end);
            $period_days = max(1, ($end_time - $start_time) / 86400);
            $prev_end = date('Y-m-d', $start_time - 86400);
            $prev_start = date('Y-m-d', $start_time - ($period_days * 86400));

            $current_votes = (int) $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->votes_table}
                 WHERE rating_id = %d AND date_created >= %s AND date_created <= %s",
                $rating_id, $start . ' 00:00:00', $end . ' 23:59:59'
            ));
            $previous_votes = (int) $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->votes_table}
                 WHERE rating_id = %d AND date_created >= %s AND date_created <= %s",
                $rating_id, $prev_start . ' 00:00:00', $prev_end . ' 23:59:59'
            ));
        } else {
            $days = intval($date_range);
            $current_votes = (int) $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->votes_table}
                 WHERE rating_id = %d AND date_created >= DATE_SUB(NOW(), INTERVAL %d DAY)",
                $rating_id, $days
            ));
            $previous_votes = (int) $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->votes_table}
                 WHERE rating_id = %d AND date_created >= DATE_SUB(NOW(), INTERVAL %d DAY)
                   AND date_created < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $rating_id, $days * 2, $days
            ));
        }

        if ($previous_votes > 0) {
            return round((($current_votes - $previous_votes) / $previous_votes) * 100, 1);
        }
        return $current_votes > 0 ? 100.0 : null;
    }

    /**
     * Get benchmark stats for all items of a given rating type
     *
     * Returns the average metric across all items of the same type, useful for
     * comparing a single item's performance against the site-wide average.
     *
     * @param string $rating_type Rating type (stars, numeric, like_dislike, approval)
     * @param string|int|array $date_range Date range filter
     * @return object Object with avg_rating, avg_votes, item_count
     */
    public function get_type_benchmark(string $rating_type, string|int|array $date_range = 'all'): object {
        $date_condition = $this->build_date_condition($date_range, 'v.date_created');
        $is_binary = (Shuriken_Rating_Type::tryFrom($rating_type) ?? Shuriken_Rating_Type::Stars)->isBinary();

        $result = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT 
                COUNT(*) as item_count,
                AVG(sub.avg_value) as avg_rating,
                AVG(sub.vote_count) as avg_votes,
                AVG(sub.approval_rate) as avg_approval_rate
             FROM (
                SELECT r.id,
                       AVG(v.rating_value) as avg_value,
                       COUNT(v.id) as vote_count,
                       CASE WHEN %s IN ('like_dislike', 'approval')
                            THEN ROUND(SUM(CASE WHEN v.rating_value > 0 THEN 1 ELSE 0 END) / COUNT(v.id) * 100)
                            ELSE NULL END as approval_rate
                FROM {$this->ratings_table} r
                JOIN {$this->votes_table} v ON v.rating_id = r.id
                WHERE COALESCE(r.rating_type, 'stars') = %s
                  AND r.mirror_of IS NULL
                  AND r.parent_id IS NULL
                  {$date_condition}
                GROUP BY r.id
             ) sub",
            $rating_type,
            $rating_type
        ));

        if (!$result || !$result->item_count) {
            $default = new stdClass();
            $default->avg_rating = null;
            $default->avg_votes = null;
            $default->avg_approval_rate = null;
            $default->item_count = 0;
            return $default;
        }

        $result->item_count = (int) $result->item_count;
        $result->avg_rating = $result->avg_rating !== null ? round((float) $result->avg_rating, 2) : null;
        $result->avg_votes = $result->avg_votes !== null ? round((float) $result->avg_votes, 1) : null;
        $result->avg_approval_rate = $result->avg_approval_rate !== null ? round((float) $result->avg_approval_rate) : null;

        return $result;
    }
    public function get_voting_heatmap(string|int|array $date_range = 'all'): array {
        $date_condition = $this->build_date_condition($date_range);

        return $this->wpdb->get_results(
            "SELECT DAYOFWEEK(date_created) as dow, HOUR(date_created) as hour, COUNT(*) as count
             FROM {$this->votes_table}
             WHERE 1=1 {$date_condition}
             GROUP BY dow, hour
             ORDER BY dow, hour"
        );
    }

    /**
     * Get votes over time split by rating type (stars, like_dislike, numeric, approval)
     *
     * Used for the stacked area chart on the dashboard.
     *
     * @param string|int|array $date_range Date range filter
     * @return array Array of objects with vote_date, rating_type, vote_count
     */
    public function get_votes_over_time_by_type(string|int|array $date_range = 30): array {
        $date_condition = $this->build_date_condition($date_range, 'v.date_created');

        return $this->wpdb->get_results(
            "SELECT DATE(v.date_created) as vote_date,
                    COALESCE(r.rating_type, 'stars') as rating_type,
                    COUNT(*) as vote_count
             FROM {$this->votes_table} v
             JOIN {$this->ratings_table} r ON v.rating_id = r.id
             WHERE 1=1 {$date_condition}
             GROUP BY vote_date, rating_type
             ORDER BY vote_date"
        );
    }

    /**
     * Get per-type summary statistics
     *
     * Returns one row per active rating type with appropriate metrics:
     * - Stars/Numeric: weighted average, item count, total votes
     * - Like/Dislike: average approval %, item count, total votes, likes, dislikes
     * - Approval: total approvals, item count
     *
     * @return array Array of type summary objects
     */
    public function get_per_type_summary(): array {
        $results = $this->wpdb->get_results(
            "SELECT COALESCE(r.rating_type, 'stars') as rating_type,
                    r.scale,
                    COUNT(DISTINCT r.id) as item_count,
                    COALESCE(SUM(r.total_votes), 0) as total_votes,
                    COALESCE(SUM(r.total_rating), 0) as total_rating
             FROM {$this->ratings_table} r
             WHERE r.mirror_of IS NULL
               AND r.parent_id IS NULL
               AND r.total_votes > 0
             GROUP BY r.rating_type, r.scale
             ORDER BY total_votes DESC"
        );

        // Aggregate by type (different scales of same type become one row)
        $summaries = array();
        foreach ($results as $row) {
            $type = $row->rating_type;
            if (!isset($summaries[$type])) {
                $summaries[$type] = (object) array(
                    'rating_type' => $type,
                    'item_count'  => 0,
                    'total_votes' => 0,
                    'total_rating' => 0,
                    'scale'       => (int) $row->scale,
                );
            }
            $summaries[$type]->item_count  += (int) $row->item_count;
            $summaries[$type]->total_votes += (int) $row->total_votes;
            $summaries[$type]->total_rating += (float) $row->total_rating;
        }

        // Calculate display metrics per type
        foreach ($summaries as &$s) {
            if ((Shuriken_Rating_Type::tryFrom($s->rating_type) ?? Shuriken_Rating_Type::Stars)->isBinary()) {
                $s->approval_rate = $s->total_votes > 0
                    ? round(($s->total_rating / $s->total_votes) * 100)
                    : 0;
            } else {
                $s->weighted_average = $s->total_votes > 0
                    ? Shuriken_Database::denormalize_average($s->total_rating / $s->total_votes, $s->scale)
                    : 0;
            }
        }
        unset($s);

        return array_values($summaries);
    }

    /**
     * Get participation rate — how many rating items have received at least one vote
     *
     * @return object Object with total_items, active_items, rate (0-100)
     */
    public function get_participation_rate(): object {
        $result = new stdClass();

        // Count only top-level, non-mirror ratings (sub-ratings and mirrors are excluded).
        // Participation is based on ratings that have received at least one direct (global)
        // vote — per-post (contextual) votes are intentionally excluded: a rating displayed
        // on N posts could theoretically appear on any number of posts, making "total items"
        // undefined until a user actually votes on them.
        $result->total_items = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->ratings_table}
             WHERE mirror_of IS NULL AND parent_id IS NULL"
        );

        $result->active_items = (int) $this->wpdb->get_var(
            "SELECT COUNT(DISTINCT v.rating_id)
             FROM {$this->votes_table} v
             INNER JOIN {$this->ratings_table} r ON r.id = v.rating_id
             WHERE r.mirror_of IS NULL AND r.parent_id IS NULL AND v.context_id IS NULL"
        );

        $result->rate = $result->total_items > 0
            ? round(($result->active_items / $result->total_items) * 100)
            : 0;

        return $result;
    }

    /**
     * Get momentum items — ratings whose average is rising or falling vs. previous period
     *
     * Compares current half-period average to previous half-period average.
     * Only considers items with votes in both halves.
     *
     * @param string|int|array $date_range Date range filter (preset days only, not custom/all)
     * @param int $limit Max items per direction (rising + falling)
     * @return object Object with rising[] and falling[] arrays
     */
    public function get_momentum_items(string|int|array $date_range = 30, int $limit = 3): object {
        $result = new stdClass();
        $result->rising = array();
        $result->falling = array();

        // Only works with numeric day presets
        $days = intval($date_range);
        if ($days <= 0) {
            return $result;
        }

        $half = intval($days / 2);

        // Get items with votes in both halves, compare averages
        // Exclude binary types (approval rate momentum is different from star averages)
        $items = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT r.id, r.name, r.rating_type, r.scale, r.total_votes,
                    AVG(CASE WHEN v.date_created >= DATE_SUB(NOW(), INTERVAL %d DAY) THEN v.rating_value END) as recent_avg,
                    AVG(CASE WHEN v.date_created >= DATE_SUB(NOW(), INTERVAL %d DAY)
                              AND v.date_created < DATE_SUB(NOW(), INTERVAL %d DAY) THEN v.rating_value END) as prev_avg,
                    COUNT(CASE WHEN v.date_created >= DATE_SUB(NOW(), INTERVAL %d DAY) THEN 1 END) as recent_count,
                    COUNT(CASE WHEN v.date_created >= DATE_SUB(NOW(), INTERVAL %d DAY)
                               AND v.date_created < DATE_SUB(NOW(), INTERVAL %d DAY) THEN 1 END) as prev_count
             FROM {$this->ratings_table} r
             JOIN {$this->votes_table} v ON v.rating_id = r.id
             WHERE r.mirror_of IS NULL
               AND r.parent_id IS NULL
               AND r.rating_type NOT IN ('like_dislike', 'approval')
               AND v.date_created >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY r.id
             HAVING recent_count >= 2 AND prev_count >= 2",
            $half, $days, $half, $half, $days, $half, $days
        ));

        foreach ($items as $item) {
            $recent = round((float) $item->recent_avg, 2);
            $prev = round((float) $item->prev_avg, 2);
            if ($prev == 0) continue;

            $delta = $recent - $prev;
            $item->delta = round($delta, 1);
            $item->recent_avg = round($recent, 1);
            $item->prev_avg = round($prev, 1);

            $s = isset($item->scale) ? (int) $item->scale : Shuriken_Database::RATING_SCALE_DEFAULT;
            $item->display_delta = Shuriken_Database::denormalize_average(abs((float) $item->delta), $s) * ($delta < 0 ? -1 : 1);
            $item->display_recent_avg = Shuriken_Database::denormalize_average((float) $item->recent_avg, $s);
            $item->display_prev_avg = Shuriken_Database::denormalize_average((float) $item->prev_avg, $s);

            if ($delta > 0) {
                $result->rising[] = $item;
            } elseif ($delta < 0) {
                $result->falling[] = $item;
            }
        }

        // Sort: rising by delta DESC, falling by delta ASC
        usort($result->rising, fn($a, $b) => $b->delta <=> $a->delta);
        usort($result->falling, fn($a, $b) => $a->delta <=> $b->delta);

        $result->rising = array_slice($result->rising, 0, $limit);
        $result->falling = array_slice($result->falling, 0, $limit);

        return $result;
    }
}
