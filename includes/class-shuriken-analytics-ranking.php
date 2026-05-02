<?php
/**
 * Shuriken Reviews Analytics Ranking
 *
 * Consolidated ranking queries extracted from Shuriken_Analytics.
 * Merges get_top_rated(), get_most_voted(), and get_low_performers()
 * into a single parametric query with convenience wrappers.
 *
 * @package Shuriken_Reviews
 * @since 1.15.5
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_Analytics_Ranking
 *
 * @since 1.15.5
 */
class Shuriken_Analytics_Ranking {

    use Shuriken_Analytics_Helpers;

    public function __construct(
        private readonly \wpdb $wpdb,
        private readonly string $ratings_table,
        private readonly string $votes_table,
    ) {}

    /**
     * Get top rated items (standalone and parent ratings only)
     *
     * like_dislike ratings are included: their 0–1 average is scaled to 0–5 so
     * the threshold comparison is on a uniform scale. approval ratings are always
     * excluded because every approval vote is 1, making the average a meaningless
     * 100 % regardless of user sentiment.
     *
     * @param int              $limit       Maximum number of items
     * @param int              $min_votes   Minimum votes required
     * @param float            $min_average Minimum average rating (on the 0–5 scale)
     * @param string|int|array $date_range  Date range filter
     * @return array Array of rating objects
     */
    public function get_top_rated(int $limit = 10, int $min_votes = 1, float $min_average = 3.0, string|int|array $date_range = 'all'): array {
        return $this->get_ranked($limit, 'average', 'DESC', $min_votes, $min_average, '>=', $date_range);
    }

    /**
     * Get most voted (popular) items (standalone and parent ratings only)
     *
     * @param int              $limit      Maximum number of items
     * @param string|int|array $date_range Date range filter
     * @return array Array of rating objects
     */
    public function get_most_voted(int $limit = 10, string|int|array $date_range = 'all'): array {
        return $this->get_ranked($limit, 'total_votes', 'DESC', 1, null, '', $date_range);
    }

    /**
     * Get low performing items (standalone and parent ratings only)
     *
     * like_dislike ratings are included: their 0–1 average is scaled to 0–5 so
     * the threshold comparison is on a uniform scale. approval ratings are always
     * excluded because every approval vote is 1, making the average a meaningless
     * 100 % regardless of user sentiment.
     *
     * @param int              $limit       Maximum number of items
     * @param int              $min_votes   Minimum votes required
     * @param float            $max_average Maximum average rating (on the 0–5 scale)
     * @param string|int|array $date_range  Date range filter
     * @return array Array of rating objects
     */
    public function get_low_performers(int $limit = 10, int $min_votes = 1, float $max_average = 3.0, string|int|array $date_range = 'all'): array {
        return $this->get_ranked($limit, 'average', 'ASC', $min_votes, $max_average, '<', $date_range);
    }

    /**
     * Consolidated ranking query — parametric engine behind the three public methods
     *
     * @param int              $limit          Maximum results
     * @param string           $sort_column    'average' or 'total_votes'
     * @param string           $sort_direction 'ASC' or 'DESC'
     * @param int              $min_votes      Minimum votes required
     * @param float|null       $avg_threshold  Average threshold (null = no threshold)
     * @param string           $avg_operator   Comparison operator ('>=' or '<')
     * @param string|int|array $date_range     Date range filter
     * @return array Array of rating objects
     */
    private function get_ranked(int $limit, string $sort_column, string $sort_direction, int $min_votes, ?float $avg_threshold, string $avg_operator, string|int|array $date_range): array {
        $date_condition = $this->build_date_condition($date_range, 'v.date_created');
        $secondary_sort = $sort_column === 'average' ? ', total_votes DESC' : '';

        if (empty($date_condition)) {
            return $this->get_ranked_cached($limit, $sort_column, $sort_direction, $secondary_sort, $min_votes, $avg_threshold, $avg_operator);
        }

        return $this->get_ranked_filtered($limit, $sort_column, $sort_direction, $secondary_sort, $min_votes, $avg_threshold, $avg_operator, $date_condition);
    }

    /**
     * Ranking from cached totals on the ratings table (no date filter)
     *
     * like_dislike averages (0–1) are scaled to 0–5 in both the SELECT and the
     * threshold condition so all rating types share the same numeric range.
     * approval is excluded when a threshold is active because its average is
     * always 1.0 (every vote is a "1") and carries no quality signal.
     */
    private function get_ranked_cached(int $limit, string $sort_column, string $sort_direction, string $secondary_sort, int $min_votes, ?float $avg_threshold, string $avg_operator): array {
        $conditions = ['mirror_of IS NULL', 'parent_id IS NULL'];
        $params     = [];

        if ($min_votes > 0) {
            $conditions[] = 'total_votes >= %d';
            $params[] = $min_votes;
        }

        if ($avg_threshold !== null) {
            // approval is excluded: its average is always ~1.0 (every vote = 1), giving
            // a meaningless perfect score that would dominate any top-rated list.
            // like_dislike stores 0/1 values; build_like_dislike_scale_sql() maps
            // the 0–1 fraction onto the same 0–5 range used by stars and numeric types.
            $avg_expr     = self::build_like_dislike_scale_sql('total_rating / NULLIF(total_votes, 0)', 'rating_type');
            $conditions[] = "rating_type NOT IN ('approval')";
            $conditions[] = "({$avg_expr}) {$avg_operator} %f";
            $params[] = $avg_threshold;
        }

        $where    = implode(' AND ', $conditions);
        $params[] = $limit;

        $avg_select = self::build_like_dislike_scale_sql('total_rating / NULLIF(total_votes, 0)', 'rating_type');

        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT id, name, rating_type, scale, total_votes, total_rating, parent_id, effect_type, display_only, mirror_of,
                    ROUND({$avg_select}, 4) as average
             FROM {$this->ratings_table}
             WHERE {$where}
             ORDER BY {$sort_column} {$sort_direction}{$secondary_sort}
             LIMIT %d",
            ...$params
        ));
    }

    /**
     * Ranking from votes table with date filter and effect-type inversion
     *
     * like_dislike averages (0–1) are scaled to 0–5 in both the SELECT and the
     * threshold HAVING clause so all rating types share the same numeric range.
     * approval is excluded when a threshold is active (see get_ranked_cached).
     */
    private function get_ranked_filtered(int $limit, string $sort_column, string $sort_direction, string $secondary_sort, int $min_votes, ?float $avg_threshold, string $avg_operator, string $date_condition): array {
        $inversion  = self::get_inversion_sql();
        $avg_select = self::build_like_dislike_scale_sql("AVG({$inversion})", 'r.rating_type');

        $having_parts = [];
        $params       = [];

        // Binary exclusion and threshold applied in WHERE so they scope to the rating row,
        // not the aggregate (avoids filtering parent rows that have binary sub-ratings).
        $where_extra = '';
        if ($avg_threshold !== null) {
            // approval is excluded: its average is always ~1.0, carrying no quality signal.
            // build_like_dislike_scale_sql() scales like_dislike values × RATING_SCALE_DEFAULT
            // so the HAVING threshold comparison operates on a uniform 0–5 range.
            $where_extra  = " AND r.rating_type NOT IN ('approval')";
            $having_parts[] = 'total_votes >= %d';
            $params[]       = $min_votes;
            $having_parts[] = "({$avg_select}) {$avg_operator} %f";
            $params[]       = $avg_threshold;
        } else {
            $having_parts[] = 'total_votes > 0';
        }

        $having   = implode(' AND ', $having_parts);
        $params[] = $limit;

        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT r.id, r.name, r.rating_type, r.scale, r.parent_id, r.effect_type, r.display_only, r.mirror_of,
                    COUNT(v.id) as total_votes,
                    COALESCE(SUM({$inversion}), 0) as total_rating,
                    ROUND({$avg_select}, 4) as average
             FROM {$this->ratings_table} r
             LEFT JOIN {$this->ratings_table} sub ON sub.parent_id = r.id
             LEFT JOIN {$this->votes_table} v ON (v.rating_id = r.id OR v.rating_id = sub.id) {$date_condition}
             WHERE r.mirror_of IS NULL
               AND r.parent_id IS NULL{$where_extra}
             GROUP BY r.id
             HAVING {$having}
             ORDER BY {$sort_column} {$sort_direction}{$secondary_sort}
             LIMIT %d",
            ...$params
        ));
    }

    /**
     * Build a SQL expression that scales a like_dislike raw value to the 0–RATING_SCALE_DEFAULT
     * range, leaving all other types unchanged.
     *
     * like_dislike stores 0/1 values whose average is a 0–1 fraction. Multiplying
     * by RATING_SCALE_DEFAULT maps it onto the same numeric range used by stars and
     * numeric types, allowing uniform threshold comparisons.
     *
     * @param string $inner_expr      SQL expression to scale (e.g. a raw column reference or AVG(...))
     * @param string $rating_type_col SQL column reference for rating_type (e.g. 'rating_type' or 'r.rating_type')
     * @return string SQL CASE expression
     */
    private static function build_like_dislike_scale_sql(string $inner_expr, string $rating_type_col): string {
        $scale = Shuriken_Database::RATING_SCALE_DEFAULT;
        return "({$inner_expr}) * CASE WHEN {$rating_type_col} = 'like_dislike' THEN {$scale} ELSE 1 END";
    }

    /**
     * Build the effect-type inversion SQL CASE expression
     *
     * Handles binary (like_dislike, approval) and scaled (stars, numeric) types.
     * For binary: inverts 0↔1 using the rating's scale column.
     * For scaled: inverts within the normalized 1–5 range.
     *
     * The condition `{$vote_alias}.rating_id = {$rating_alias}.id` ensures that only
     * votes belonging to the sub-rating itself are inverted; direct votes on the parent
     * rating (which appear in the same JOIN result set) fall through to the ELSE branch.
     *
     * @param string $rating_alias Table alias for the ratings table (default 'sub')
     * @param string $vote_alias   Table alias for the votes table (default 'v')
     * @return string SQL CASE expression
     */
    public static function get_inversion_sql(string $rating_alias = 'sub', string $vote_alias = 'v'): string {
        $internal_scale = Shuriken_Database::RATING_SCALE_DEFAULT;
        return "CASE
            WHEN {$vote_alias}.rating_id = {$rating_alias}.id AND {$rating_alias}.effect_type = 'negative' AND {$rating_alias}.rating_type IN ('like_dislike', 'approval')
                THEN CAST({$rating_alias}.scale AS SIGNED) - {$vote_alias}.rating_value
            WHEN {$vote_alias}.rating_id = {$rating_alias}.id AND {$rating_alias}.effect_type = 'negative'
                THEN ({$internal_scale} + 1) - {$vote_alias}.rating_value
            ELSE {$vote_alias}.rating_value
        END";
    }
}
