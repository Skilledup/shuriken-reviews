<?php
/**
 * Shuriken Reviews Analytics Context
 *
 * Per-post / per-context analytics extracted from Shuriken_Analytics.
 * Handles all contextual vote queries: single-context stats, paginated contexts,
 * trending contexts, context distributions, and context-scoped chart data.
 *
 * @package Shuriken_Reviews
 * @since 1.15.5
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_Analytics_Context
 *
 * @since 1.15.5
 */
class Shuriken_Analytics_Context {

    use Shuriken_Analytics_Helpers;

    public function __construct(
        private readonly \wpdb $wpdb,
        private readonly string $ratings_table,
        private readonly string $votes_table,
        private readonly Shuriken_Rating_Repository $db,
    ) {}

    /**
     * Check if a rating has any contextual (per-post) votes
     *
     * @param int $rating_id Rating ID
     * @return bool True if at least one contextual vote exists
     */
    public function has_contextual_votes(int $rating_id): bool {
        return (bool) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT 1 FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id IS NOT NULL
             LIMIT 1",
            $rating_id
        ));
    }

    /**
     * Get contextual overview summary for a rating (used in the Per-Post view)
     *
     * @param int              $rating_id  Rating ID
     * @param string|int|array $date_range Date range filter
     * @return object Summary with total_contexts, total_votes, avg_across_contexts, best_context
     */
    public function get_rating_context_summary(int $rating_id, string|int|array $date_range = 'all'): object {
        $date_condition = $this->build_date_condition($date_range);

        $summary = new stdClass();

        // Total distinct contexts
        $summary->total_contexts = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(DISTINCT CONCAT(context_id, ':', context_type))
             FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id IS NOT NULL {$date_condition}",
            $rating_id
        ));

        // Total contextual votes
        $totals = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT COUNT(*) as total_votes, COALESCE(SUM(rating_value), 0) as total_rating
             FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id IS NOT NULL {$date_condition}",
            $rating_id
        ));
        $summary->total_votes = (int) $totals->total_votes;
        $summary->total_rating = (int) $totals->total_rating;
        $summary->average = $summary->total_votes > 0 ? round($summary->total_rating / $summary->total_votes, 1) : 0;

        $rating = $this->db->get_rating($rating_id);
        $scale = $rating ? (int) ($rating->scale ?: Shuriken_Database::RATING_SCALE_DEFAULT) : Shuriken_Database::RATING_SCALE_DEFAULT;
        $summary->display_average = Shuriken_Database::denormalize_average((float) $summary->average, $scale);

        // Unique voters across all contexts
        $summary->unique_voters = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(DISTINCT CASE WHEN user_id > 0 THEN user_id ELSE user_ip END)
             FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id IS NOT NULL {$date_condition}",
            $rating_id
        ));

        // Best performing context (highest average with at least 1 vote)
        $best = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT context_id, context_type,
                    COUNT(*) as votes,
                    ROUND(AVG(rating_value), 2) as avg_rating
             FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id IS NOT NULL {$date_condition}
             GROUP BY context_id, context_type
             ORDER BY avg_rating DESC, votes DESC
             LIMIT 1",
            $rating_id
        ));

        if ($best) {
            $summary->best_context_id = (int) $best->context_id;
            $summary->best_context_type = $best->context_type;
            $rating_type_str = $rating ? ($rating->rating_type ?: 'stars') : 'stars';
            $type_enum = Shuriken_Rating_Type::tryFrom($rating_type_str) ?? Shuriken_Rating_Type::Stars;
            if ($type_enum->isBinary()) {
                $summary->best_context_avg = round((float) $best->avg_rating * 100) . '%';
            } else {
                $summary->best_context_avg = Shuriken_Database::denormalize_average((float) $best->avg_rating, $scale);
            }
            $summary->best_context_votes = (int) $best->votes;
            $summary->best_context_title = $this->get_context_title($best->context_id, $best->context_type);
        } else {
            $summary->best_context_id = null;
        }

        return $summary;
    }

    /**
     * Get paginated list of contexts (posts/pages/products) for a rating
     *
     * @param int              $rating_id  Rating ID
     * @param int              $page       Page number
     * @param int              $per_page   Items per page
     * @param string|int|array $date_range Date range filter
     * @param string           $sort_by    Column to sort by: 'votes', 'average', 'last_vote'
     * @param string           $sort_order Sort direction: 'asc' or 'desc'
     * @return object Paginated result with contexts, total_count, total_pages
     */
    public function get_rating_contexts_paginated(int $rating_id, int $page = 1, int $per_page = 20, string|int|array $date_range = 'all', string $sort_by = 'votes', string $sort_order = 'desc'): object {
        $offset = ($page - 1) * $per_page;
        $date_condition = $this->build_date_condition($date_range);
        $result = new stdClass();

        // Validate sort column
        $allowed_sort = array('votes' => 'ctx_votes', 'average' => 'ctx_avg', 'last_vote' => 'last_vote_date');
        $order_col = $allowed_sort[$sort_by] ?? 'ctx_votes';
        $order_dir = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';

        $result->total_count = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(DISTINCT CONCAT(context_id, ':', context_type))
             FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id IS NOT NULL {$date_condition}",
            $rating_id
        ));

        $result->total_pages = $result->total_count > 0 ? ceil($result->total_count / $per_page) : 0;
        $result->current_page = $page;
        $result->per_page = $per_page;

        $result->contexts = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT context_id, context_type,
                    COUNT(*) as ctx_votes,
                    COALESCE(SUM(rating_value), 0) as ctx_total,
                    ROUND(AVG(rating_value), 2) as ctx_avg,
                    MAX(date_created) as last_vote_date,
                    MIN(date_created) as first_vote_date,
                    COUNT(DISTINCT CASE WHEN user_id > 0 THEN user_id ELSE user_ip END) as unique_voters
             FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id IS NOT NULL {$date_condition}
             GROUP BY context_id, context_type
             ORDER BY {$order_col} {$order_dir}
             LIMIT %d OFFSET %d",
            $rating_id,
            $per_page,
            $offset
        ));

        // Enrich with post titles and display averages
        $rating = $this->db->get_rating($rating_id);
        $scale = $rating ? (int) ($rating->scale ?: Shuriken_Database::RATING_SCALE_DEFAULT) : Shuriken_Database::RATING_SCALE_DEFAULT;

        foreach ($result->contexts as &$ctx) {
            $ctx->context_id = (int) $ctx->context_id;
            $ctx->ctx_votes = (int) $ctx->ctx_votes;
            $ctx->ctx_total = (int) $ctx->ctx_total;
            $ctx->unique_voters = (int) $ctx->unique_voters;
            $ctx->ctx_display_avg = Shuriken_Database::denormalize_average((float) $ctx->ctx_avg, $scale);
            $ctx->title = $this->get_context_title($ctx->context_id, $ctx->context_type);
            $ctx->edit_url = get_edit_post_link($ctx->context_id, 'raw');
            $ctx->view_url = get_permalink($ctx->context_id);
        }
        unset($ctx);

        return $result;
    }

    /**
     * Get top contexts by vote count for a rating (for chart data)
     *
     * @param int              $rating_id  Rating ID
     * @param int              $limit      Max results
     * @param string|int|array $date_range Date range filter
     * @return array Array of context objects
     */
    public function get_top_contexts_by_votes(int $rating_id, int $limit = 10, string|int|array $date_range = 'all'): array {
        $date_condition = $this->build_date_condition($date_range);

        $rating = $this->db->get_rating($rating_id);
        $scale = $rating ? (int) ($rating->scale ?: Shuriken_Database::RATING_SCALE_DEFAULT) : Shuriken_Database::RATING_SCALE_DEFAULT;

        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT context_id, context_type,
                    COUNT(*) as ctx_votes,
                    ROUND(AVG(rating_value), 2) as ctx_avg
             FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id IS NOT NULL {$date_condition}
             GROUP BY context_id, context_type
             ORDER BY ctx_votes DESC
             LIMIT %d",
            $rating_id,
            $limit
        ));

        foreach ($rows as &$row) {
            $row->context_id = (int) $row->context_id;
            $row->ctx_votes = (int) $row->ctx_votes;
            $row->ctx_display_avg = Shuriken_Database::denormalize_average((float) $row->ctx_avg, $scale);
            $row->title = $this->get_context_title($row->context_id, $row->context_type);
        }
        unset($row);

        return $rows;
    }

    /**
     * Get distribution of average ratings across contexts (histogram buckets)
     *
     * @param int              $rating_id  Rating ID
     * @param string|int|array $date_range Date range filter
     * @return array Array of bucket => count pairs
     */
    public function get_context_avg_distribution(int $rating_id, string|int|array $date_range = 'all'): array {
        $date_condition = $this->build_date_condition($date_range);

        $rating = $this->db->get_rating($rating_id);
        $rating_type = $rating ? ($rating->rating_type ?: 'stars') : 'stars';
        $scale = $rating ? (int) ($rating->scale ?: Shuriken_Database::RATING_SCALE_DEFAULT) : Shuriken_Database::RATING_SCALE_DEFAULT;

        // Get per-context averages
        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT context_id, context_type, AVG(rating_value) as ctx_avg
             FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id IS NOT NULL {$date_condition}
             GROUP BY context_id, context_type",
            $rating_id
        ));

        if ($this->is_binary_type($rating_type)) {
            // For binary types, bucket by approval rate in 10% steps
            $buckets = array();
            for ($i = 0; $i <= 100; $i += 10) {
                $buckets[$i . '%'] = 0;
            }
            foreach ($rows as $row) {
                $pct = round((float) $row->ctx_avg * 100);
                $bucket = min(100, floor($pct / 10) * 10);
                $buckets[$bucket . '%']++;
            }
            return $buckets;
        }

        // Stars/numeric: bucket by denormalized values in 1-step increments
        $buckets = array();
        for ($i = 1; $i <= $scale; $i++) {
            $buckets[(string) $i] = 0;
        }

        foreach ($rows as $row) {
            $denorm = Shuriken_Database::denormalize_average((float) $row->ctx_avg, $scale);
            $bucket = max(1, min($scale, round($denorm)));
            $buckets[(string) $bucket]++;
        }

        return $buckets;
    }

    /**
     * Get trending contexts — contexts with rising vote momentum
     *
     * @param int              $rating_id  Rating ID
     * @param string|int|array $date_range Date range filter (numeric days only)
     * @param int              $limit      Max results
     * @param string           $sort_by    Sort column: 'recent_votes', 'velocity', 'ctx_avg'
     * @param string           $sort_order Sort direction: 'asc' or 'desc'
     * @return array Array of trending context objects
     */
    public function get_trending_contexts(int $rating_id, string|int|array $date_range = 30, int $limit = 5, string $sort_by = 'velocity', string $sort_order = 'desc'): array {
        $days = intval($date_range);
        if ($days <= 0) {
            return array();
        }

        $half = intval($days / 2);

        $rating = $this->db->get_rating($rating_id);
        $scale = $rating ? (int) ($rating->scale ?: Shuriken_Database::RATING_SCALE_DEFAULT) : Shuriken_Database::RATING_SCALE_DEFAULT;

        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT context_id, context_type,
                    COUNT(CASE WHEN date_created >= DATE_SUB(NOW(), INTERVAL %d DAY) THEN 1 END) as recent_votes,
                    COUNT(CASE WHEN date_created >= DATE_SUB(NOW(), INTERVAL %d DAY)
                               AND date_created < DATE_SUB(NOW(), INTERVAL %d DAY) THEN 1 END) as prev_votes,
                    ROUND(AVG(rating_value), 2) as ctx_avg
             FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id IS NOT NULL
               AND date_created >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY context_id, context_type
             HAVING recent_votes > 0
             ORDER BY (recent_votes - prev_votes) DESC
             LIMIT %d",
            $half, $days, $half, $rating_id, $days, $limit
        ));

        foreach ($rows as &$row) {
            $row->context_id = (int) $row->context_id;
            $row->recent_votes = (int) $row->recent_votes;
            $row->prev_votes = (int) $row->prev_votes;
            $row->velocity = $row->prev_votes > 0
                ? round((($row->recent_votes - $row->prev_votes) / $row->prev_votes) * 100)
                : ($row->recent_votes > 0 ? 100 : 0);
            $row->ctx_display_avg = Shuriken_Database::denormalize_average((float) $row->ctx_avg, $scale);
            $row->title = $this->get_context_title($row->context_id, $row->context_type);
        }
        unset($row);

        // Sort the results by the requested column (PHP-level, since velocity is computed post-query)
        $sort_props = array('recent_votes' => 'recent_votes', 'velocity' => 'velocity', 'ctx_avg' => 'ctx_avg');
        $sort_prop = $sort_props[$sort_by] ?? 'velocity';
        usort($rows, function ($a, $b) use ($sort_prop, $sort_order) {
            $cmp = $a->$sort_prop <=> $b->$sort_prop;
            return strtolower($sort_order) === 'asc' ? $cmp : -$cmp;
        });

        return $rows;
    }

    /**
     * Get detailed stats for a rating scoped to a single context (post/page/product)
     *
     * @param int              $rating_id    Rating ID
     * @param int              $context_id   Post ID
     * @param string           $context_type Context type
     * @param string|int|array $date_range   Date range filter
     * @return object|null Stats object or null if no data
     */
    public function get_context_rating_stats(int $rating_id, int $context_id, string $context_type, string|int|array $date_range = 'all'): ?object {
        $rating = $this->db->get_rating($rating_id);
        if (!$rating) {
            return null;
        }

        $date_condition = $this->build_date_condition($date_range);
        $scale = (int) ($rating->scale ?: Shuriken_Database::RATING_SCALE_DEFAULT);

        $stats = new stdClass();
        $stats->rating = $rating;

        $totals = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT COUNT(*) as total_votes, COALESCE(SUM(rating_value), 0) as total_rating
             FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id = %d AND context_type = %s {$date_condition}",
            $rating_id, $context_id, $context_type
        ));

        $stats->total_votes = (int) $totals->total_votes;
        $stats->total_rating = (int) $totals->total_rating;
        $stats->average = $stats->total_votes > 0 ? round($stats->total_rating / $stats->total_votes, 1) : 0;
        $stats->display_average = Shuriken_Database::denormalize_average((float) $stats->average, $scale);

        $stats->member_votes = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id = %d AND context_type = %s AND user_id > 0 {$date_condition}",
            $rating_id, $context_id, $context_type
        ));

        $stats->guest_votes = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id = %d AND context_type = %s AND user_id = 0 {$date_condition}",
            $rating_id, $context_id, $context_type
        ));

        $stats->unique_voters = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(DISTINCT CASE WHEN user_id > 0 THEN user_id ELSE user_ip END)
             FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id = %d AND context_type = %s {$date_condition}",
            $rating_id, $context_id, $context_type
        ));

        $stats->first_vote = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT MIN(date_created) FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id = %d AND context_type = %s {$date_condition}",
            $rating_id, $context_id, $context_type
        ));

        $stats->last_vote = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT MAX(date_created) FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id = %d AND context_type = %s {$date_condition}",
            $rating_id, $context_id, $context_type
        ));

        // Distribution for this context
        $rating_type = $rating->rating_type ?: 'stars';
        $distribution = $this->build_empty_distribution($rating_type, $scale);
        $dist_rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT ROUND(rating_value) as value, COUNT(*) as count
             FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id = %d AND context_type = %s {$date_condition}
             GROUP BY value ORDER BY value",
            $rating_id, $context_id, $context_type
        ));
        foreach ($dist_rows as $row) {
            $key = intval($row->value);
            if (array_key_exists($key, $distribution)) {
                $distribution[$key] = intval($row->count);
            }
        }
        $stats->distribution = $distribution;

        // Votes over time for this context
        $stats->votes_over_time = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DATE(date_created) as vote_date, COUNT(*) as vote_count
             FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id = %d AND context_type = %s {$date_condition}
             GROUP BY DATE(date_created) ORDER BY vote_date",
            $rating_id, $context_id, $context_type
        ));

        return $stats;
    }

    /**
     * Get paginated votes for a rating scoped to a single context
     *
     * @param int              $rating_id    Rating ID
     * @param int              $context_id   Post ID
     * @param string           $context_type Context type
     * @param int              $page         Page number
     * @param int              $per_page     Items per page
     * @param string|int|array $date_range   Date range filter
     * @param string           $sort_by      Sort column: 'date' or 'rating'
     * @param string           $sort_order   Sort direction: 'asc' or 'desc'
     * @return object Paginated result
     */
    public function get_context_votes_paginated(int $rating_id, int $context_id, string $context_type, int $page = 1, int $per_page = 20, string|int|array $date_range = 'all', string $sort_by = 'date', string $sort_order = 'desc'): object {
        $offset = ($page - 1) * $per_page;
        $date_condition = $this->build_date_condition($date_range, 'v.date_created');
        $result = new stdClass();

        // Safe sort column map
        $sort_col_map = array('rating' => 'v.rating_value', 'date' => 'v.date_created');
        $sort_col = $sort_col_map[$sort_by] ?? 'v.date_created';
        $sort_dir = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';

        $result->total_count = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->votes_table} v
             WHERE v.rating_id = %d AND v.context_id = %d AND v.context_type = %s {$date_condition}",
            $rating_id, $context_id, $context_type
        ));

        $result->total_pages = $result->total_count > 0 ? ceil($result->total_count / $per_page) : 0;
        $result->current_page = $page;
        $result->per_page = $per_page;

        $result->votes = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT v.*, u.display_name, u.user_email, r.name as rating_name, r.rating_type, r.scale
             FROM {$this->votes_table} v
             LEFT JOIN {$this->wpdb->users} u ON v.user_id = u.ID
             LEFT JOIN {$this->ratings_table} r ON v.rating_id = r.id
             WHERE v.rating_id = %d AND v.context_id = %d AND v.context_type = %s {$date_condition}
             ORDER BY {$sort_col} {$sort_dir}
             LIMIT %d OFFSET %d",
            $rating_id, $context_id, $context_type,
            $per_page, $offset
        ));

        return $result;
    }

    /**
     * Get dual-axis chart data for a rating scoped to a single context
     *
     * @param int              $rating_id    Rating ID
     * @param int              $context_id   Post ID
     * @param string           $context_type Context type
     * @param string|int|array $date_range   Date range filter
     * @param int              $scale        Display scale
     * @return array Chart data
     */
    public function get_context_votes_with_rolling_avg(int $rating_id, int $context_id, string $context_type, string|int|array $date_range = 30, int $scale = Shuriken_Database::RATING_SCALE_DEFAULT): array {
        $date_condition = $this->build_date_condition($date_range);

        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DATE(date_created) as vote_date,
                    COUNT(*) as vote_count,
                    ROUND(AVG(rating_value), 2) as daily_avg
             FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id = %d AND context_type = %s {$date_condition}
             GROUP BY DATE(date_created)
             ORDER BY vote_date",
            $rating_id, $context_id, $context_type
        ));

        foreach ($rows as &$row) {
            $row->display_daily_avg = Shuriken_Database::denormalize_average((float) $row->daily_avg, $scale);
        }
        unset($row);

        return $rows;
    }

    /**
     * Get approval trend for a rating scoped to a single context
     *
     * @param int              $rating_id    Rating ID
     * @param int              $context_id   Post ID
     * @param string           $context_type Context type
     * @param string|int|array $date_range   Date range filter
     * @return array Approval trend data
     */
    public function get_context_approval_trend(int $rating_id, int $context_id, string $context_type, string|int|array $date_range = 30): array {
        $date_condition = $this->build_date_condition($date_range);

        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DATE(date_created) as vote_date,
                    COUNT(*) as total,
                    SUM(rating_value) as likes,
                    ROUND((SUM(rating_value) / COUNT(*)) * 100, 1) as approval_rate
             FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id = %d AND context_type = %s {$date_condition}
             GROUP BY DATE(date_created)
             ORDER BY vote_date",
            $rating_id, $context_id, $context_type
        ));
    }

    /**
     * Get cumulative approvals for a rating scoped to a single context
     *
     * @param int              $rating_id    Rating ID
     * @param int              $context_id   Post ID
     * @param string           $context_type Context type
     * @param string|int|array $date_range   Date range filter
     * @return array Cumulative data
     */
    public function get_context_cumulative_approvals(int $rating_id, int $context_id, string $context_type, string|int|array $date_range = 30): array {
        $date_condition = $this->build_date_condition($date_range);

        $daily = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DATE(date_created) as vote_date, COUNT(*) as daily_count
             FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id = %d AND context_type = %s {$date_condition}
             GROUP BY DATE(date_created)
             ORDER BY vote_date",
            $rating_id, $context_id, $context_type
        ));

        $cumulative = 0;
        foreach ($daily as &$row) {
            $cumulative += (int) $row->daily_count;
            $row->cumulative_count = $cumulative;
        }
        unset($row);

        return $daily;
    }

    /**
     * Get the human-readable title for a context (post/page/product)
     *
     * @param int    $context_id   Post ID
     * @param string $context_type Context type
     * @return string Post title or fallback string
     */
    private function get_context_title(int $context_id, string $context_type): string {
        $title = get_the_title($context_id);
        if (empty($title)) {
            return sprintf(__('#%d (%s)', 'shuriken-reviews'), $context_id, $context_type);
        }
        return $title;
    }
}
