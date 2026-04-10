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
    public function get_overall_stats(): object;

    /**
     * Get rating type counts
     *
     * @return array Array with counts for different rating types.
     */
    public function get_rating_type_counts(): object;

    /**
     * Get vote counts for a date range
     *
     * @param string $date_range Date range identifier.
     * @return int Number of votes.
     */
    public function get_vote_counts(string|int|array $date_range = 'all'): object;

    /**
     * Get vote change percentage
     *
     * @param string $date_range Date range identifier.
     * @return float Percentage change.
     */
    public function get_vote_change_percent(string|int|array $date_range): ?float;

    /**
     * Get vote change percentage for a specific rating item
     *
     * @param int              $rating_id  Rating ID.
     * @param string|int|array $date_range Date range.
     * @return float|null Percentage change or null.
     */
    public function get_rating_vote_change_percent(int $rating_id, string|int|array $date_range): ?float;

    /**
     * Get benchmark stats for all items of a given rating type
     *
     * @param string           $rating_type Rating type.
     * @param string|int|array $date_range  Date range filter.
     * @return object Object with avg_rating, avg_votes, item_count.
     */
    public function get_type_benchmark(string $rating_type, string|int|array $date_range = 'all'): object;

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

    /**
     * Get rating distribution
     *
     * @param string   $date_range Date range identifier.
     * @param int|null $rating_id  Optional rating ID to filter by.
     * @return array Array with counts for each star rating (1-5).
     */
    public function get_rating_distribution(string|int|array $date_range = 'all', ?int $rating_id = null): array;

    /**
     * Get votes over time
     *
     * @param int      $date_range Number of days.
     * @param int|null $rating_id  Optional rating ID to filter by.
     * @return array Array of date => count pairs.
     */
    public function get_votes_over_time(string|int|array $date_range = 30, ?int $rating_id = null): array;

    /**
     * Get recent votes
     *
     * @param int      $limit      Number of results.
     * @param int|null $rating_id  Optional rating ID to filter by.
     * @param string   $date_range Date range identifier.
     * @return array Array of vote objects.
     */
    public function get_recent_votes(int $limit = 10, ?int $rating_id = null, string|int|array $date_range = 'all'): array;

    /**
     * Get a single rating
     *
     * @param int $rating_id Rating ID.
     * @return object|null Rating object or null if not found.
     */
    public function get_rating(int $rating_id): ?object;

    /**
     * Get rating statistics
     *
     * @param int    $rating_id  Rating ID.
     * @param string $date_range Date range identifier.
     * @return array Array with statistics.
     */
    public function get_rating_stats(int $rating_id, string|int|array $date_range = 'all'): ?object;

    /**
     * Get paginated votes for a rating
     *
     * @param int $rating_id Rating ID.
     * @param int $page      Page number.
     * @param int $per_page  Items per page.
     * @param string|array $date_range Date range filter ('all', days, or array with start/end).
     * @param string $view   For parent ratings: 'direct', 'subs', or 'total'.
     * @return array Array with 'votes', 'total', and 'total_pages' keys.
     */
    public function get_rating_votes_paginated(int $rating_id, int $page = 1, int $per_page = 20, string|int|array $date_range = 'all', string $view = 'direct', string $sort_by = 'date', string $sort_order = 'desc'): object;

    /**
     * Get chart data for visualization
     *
     * @param int      $date_range Number of days.
     * @param int|null $rating_id  Optional rating ID to filter by.
     * @return array Array with chart data.
     */
    public function get_chart_data(string|int|array $date_range = 30, ?int $rating_id = null): array;

    /**
     * Build date condition for SQL queries
     *
     * @param string $date_range Date range identifier.
     * @param string $column     Column name for date comparison.
     * @return string SQL WHERE clause condition.
     */
    public function build_date_condition(string|int|array $date_range, string $column = 'date_created'): string;

    /**
     * Parse date range parameters
     *
     * @param array $params Request parameters.
     * @return string Date range identifier.
     */
    public function parse_date_range_params(array $params): string|array;

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

    /**
     * Check if a rating has sub-ratings
     *
     * @param int $rating_id Rating ID.
     * @return bool True if the rating has sub-ratings.
     */
    public function has_sub_ratings(int $rating_id): bool;

    /**
     * Get sub-ratings for a parent with contribution analysis
     *
     * @param int $parent_id Parent rating ID.
     * @return array Array of sub-rating objects with contribution data.
     */
    public function get_sub_ratings_contribution(int $parent_id): array;

    /**
     * Get parent rating stats breakdown with effective sub-rating averages
     *
     * @param int              $rating_id  Rating ID.
     * @param string|int|array $date_range Date range filter.
     * @param string|null      $scope      'global', 'contextual', or null (no filter).
     * @return object|null Stats breakdown or null.
     */
    public function get_parent_rating_stats_breakdown(int $rating_id, string|int|array $date_range = 'all', ?string $scope = null): ?object;

    /**
     * Get voting heatmap data — day-of-week × hour activity
     *
     * @param string|int|array $date_range Date range filter.
     * @return array Array of objects with dow, hour, count.
     */
    public function get_voting_heatmap(string|int|array $date_range = 'all'): array;

    /**
     * Get votes over time split by rating type
     *
     * @param string|int|array $date_range Date range filter.
     * @return array Array of objects with vote_date, rating_type, vote_count.
     */
    public function get_votes_over_time_by_type(string|int|array $date_range = 30): array;

    /**
     * Get per-type summary statistics
     *
     * @return array Array of type summary objects.
     */
    public function get_per_type_summary(): array;

    /**
     * Get participation rate
     *
     * @return object Object with total_items, active_items, rate (0-100).
     */
    public function get_participation_rate(): object;

    /**
     * Get momentum items — ratings rising or falling vs. previous period
     *
     * @param string|int|array $date_range Date range filter (numeric days only).
     * @param int              $limit      Max items per direction.
     * @return object Object with rising[] and falling[] arrays.
     */
    public function get_momentum_items(string|int|array $date_range = 30, int $limit = 3): object;

    /**
     * Get approval rate trend for a like/dislike rating
     *
     * @param int              $rating_id  Rating ID.
     * @param string|int|array $date_range Date range filter.
     * @return array Array of objects with vote_date and approval_rate.
     */
    public function get_approval_trend(int $rating_id, string|int|array $date_range = 30): array;

    /**
     * Get cumulative approval count for an approval-type rating
     *
     * @param int              $rating_id  Rating ID.
     * @param string|int|array $date_range Date range filter.
     * @return array Array of objects with vote_date, daily_count, cumulative_count.
     */
    public function get_cumulative_approvals(int $rating_id, string|int|array $date_range = 30): array;

    /**
     * Get daily votes with rolling average for dual-axis chart
     *
     * @param int              $rating_id  Rating ID.
     * @param string|int|array $date_range Date range filter.
     * @param int              $scale      Display scale for display_daily_avg.
     * @return array Array of objects with vote_date, vote_count, avg_rating, display_daily_avg.
     */
    public function get_votes_with_rolling_avg(int $rating_id, string|int|array $date_range = 30, int $scale = Shuriken_Database::RATING_SCALE_DEFAULT): array;

    /**
     * Like get_votes_with_rolling_avg but accepts multiple rating IDs.
     *
     * @param array            $rating_ids Rating IDs to include.
     * @param string|int|array $date_range Date range filter.
     * @param int              $scale      Display scale for display_daily_avg.
     * @return array Array of objects with vote_date, vote_count, daily_avg, display_daily_avg.
     */
    public function get_votes_with_rolling_avg_for_ids(array $rating_ids, string|int|array $date_range = 30, int $scale = Shuriken_Database::RATING_SCALE_DEFAULT): array;

    // =========================================================================
    // Contextual / Per-Post Analytics Methods (v1.15.0)
    // =========================================================================

    /**
     * Build SQL condition for vote scope filtering
     *
     * @param string|null $scope  'global', 'contextual', or null.
     * @param string      $prefix Column prefix.
     * @return string SQL condition.
     */
    public function build_scope_condition(?string $scope, string $prefix = ''): string;

    /**
     * Check if a rating has any contextual (per-post) votes
     *
     * @param int $rating_id Rating ID.
     * @return bool
     */
    public function has_contextual_votes(int $rating_id): bool;

    /**
     * Get scope-filtered stats for a rating
     *
     * @param int              $rating_id  Rating ID.
     * @param string|int|array $date_range Date range filter.
     * @param string|null      $scope      'global', 'contextual', or null.
     * @return object|null
     */
    public function get_rating_stats_scoped(int $rating_id, string|int|array $date_range = 'all', ?string $scope = null): ?object;

    /**
     * Get scope-filtered rating distribution
     *
     * @param string|int|array $date_range Date range filter.
     * @param int|null         $rating_id  Rating ID.
     * @param string|null      $scope      Scope filter.
     * @return array
     */
    public function get_rating_distribution_scoped(string|int|array $date_range = 'all', ?int $rating_id = null, ?string $scope = null): array;

    /**
     * Get scope-filtered votes over time
     *
     * @param string|int|array $date_range Date range filter.
     * @param int|null         $rating_id  Rating ID.
     * @param string|null      $scope      Scope filter.
     * @return array
     */
    public function get_votes_over_time_scoped(string|int|array $date_range = 30, ?int $rating_id = null, ?string $scope = null): array;

    /**
     * Get scope-filtered paginated votes
     *
     * @param int              $rating_id  Rating ID.
     * @param int              $page       Page number.
     * @param int              $per_page   Items per page.
     * @param string|int|array $date_range Date range filter.
     * @param string           $view       Parent view.
     * @param string|null      $scope      Scope filter.
     * @return object
     */
    public function get_rating_votes_paginated_scoped(int $rating_id, int $page = 1, int $per_page = 20, string|int|array $date_range = 'all', string $view = 'direct', ?string $scope = null, string $sort_by = 'date', string $sort_order = 'desc'): object;

    /**
     * Get scope-filtered dual-axis chart data
     *
     * @param int              $rating_id  Rating ID.
     * @param string|int|array $date_range Date range filter.
     * @param int              $scale      Display scale.
     * @param string|null      $scope      Scope filter.
     * @return array
     */
    public function get_votes_with_rolling_avg_scoped(int $rating_id, string|int|array $date_range = 30, int $scale = Shuriken_Database::RATING_SCALE_DEFAULT, ?string $scope = null): array;

    /**
     * Get scope-filtered approval trend
     *
     * @param int              $rating_id  Rating ID.
     * @param string|int|array $date_range Date range filter.
     * @param string|null      $scope      Scope filter.
     * @return array
     */
    public function get_approval_trend_scoped(int $rating_id, string|int|array $date_range = 30, ?string $scope = null): array;

    /**
     * Get scope-filtered cumulative approvals
     *
     * @param int              $rating_id  Rating ID.
     * @param string|int|array $date_range Date range filter.
     * @param string|null      $scope      Scope filter.
     * @return array
     */
    public function get_cumulative_approvals_scoped(int $rating_id, string|int|array $date_range = 30, ?string $scope = null): array;

    /**
     * Get contextual overview summary for a rating
     *
     * @param int              $rating_id  Rating ID.
     * @param string|int|array $date_range Date range filter.
     * @return object
     */
    public function get_rating_context_summary(int $rating_id, string|int|array $date_range = 'all'): object;

    /**
     * Get paginated list of contexts for a rating
     *
     * @param int              $rating_id  Rating ID.
     * @param int              $page       Page number.
     * @param int              $per_page   Items per page.
     * @param string|int|array $date_range Date range filter.
     * @param string           $sort_by    Sort column.
     * @param string           $sort_order Sort direction.
     * @return object
     */
    public function get_rating_contexts_paginated(int $rating_id, int $page = 1, int $per_page = 20, string|int|array $date_range = 'all', string $sort_by = 'votes', string $sort_order = 'desc'): object;

    /**
     * Get top contexts by vote count
     *
     * @param int              $rating_id  Rating ID.
     * @param int              $limit      Max results.
     * @param string|int|array $date_range Date range filter.
     * @return array
     */
    public function get_top_contexts_by_votes(int $rating_id, int $limit = 10, string|int|array $date_range = 'all'): array;

    /**
     * Get distribution of average ratings across contexts
     *
     * @param int              $rating_id  Rating ID.
     * @param string|int|array $date_range Date range filter.
     * @return array
     */
    public function get_context_avg_distribution(int $rating_id, string|int|array $date_range = 'all'): array;

    /**
     * Get trending contexts with rising vote momentum
     *
     * @param int              $rating_id  Rating ID.
     * @param string|int|array $date_range Date range filter.
     * @param int              $limit      Max results.
     * @return array
     */
    public function get_trending_contexts(int $rating_id, string|int|array $date_range = 30, int $limit = 5, string $sort_by = 'velocity', string $sort_order = 'desc'): array;

    /**
     * Get detailed stats for a rating scoped to a single context
     *
     * @param int              $rating_id    Rating ID.
     * @param int              $context_id   Post ID.
     * @param string           $context_type Context type.
     * @param string|int|array $date_range   Date range filter.
     * @return object|null
     */
    public function get_context_rating_stats(int $rating_id, int $context_id, string $context_type, string|int|array $date_range = 'all'): ?object;

    /**
     * Get paginated votes for a rating scoped to a single context
     *
     * @param int              $rating_id    Rating ID.
     * @param int              $context_id   Post ID.
     * @param string           $context_type Context type.
     * @param int              $page         Page number.
     * @param int              $per_page     Items per page.
     * @param string|int|array $date_range   Date range filter.
     * @return object
     */
    public function get_context_votes_paginated(int $rating_id, int $context_id, string $context_type, int $page = 1, int $per_page = 20, string|int|array $date_range = 'all', string $sort_by = 'date', string $sort_order = 'desc'): object;

    /**
     * Get dual-axis chart data for a rating scoped to a single context
     *
     * @param int              $rating_id    Rating ID.
     * @param int              $context_id   Post ID.
     * @param string           $context_type Context type.
     * @param string|int|array $date_range   Date range filter.
     * @param int              $scale        Display scale.
     * @return array
     */
    public function get_context_votes_with_rolling_avg(int $rating_id, int $context_id, string $context_type, string|int|array $date_range = 30, int $scale = Shuriken_Database::RATING_SCALE_DEFAULT): array;

    /**
     * Get approval trend for a rating scoped to a single context
     *
     * @param int              $rating_id    Rating ID.
     * @param int              $context_id   Post ID.
     * @param string           $context_type Context type.
     * @param string|int|array $date_range   Date range filter.
     * @return array
     */
    public function get_context_approval_trend(int $rating_id, int $context_id, string $context_type, string|int|array $date_range = 30): array;

    /**
     * Get cumulative approvals for a rating scoped to a single context
     *
     * @param int              $rating_id    Rating ID.
     * @param int              $context_id   Post ID.
     * @param string           $context_type Context type.
     * @param string|int|array $date_range   Date range filter.
     * @return array
     */
    public function get_context_cumulative_approvals(int $rating_id, int $context_id, string $context_type, string|int|array $date_range = 30): array;
}

