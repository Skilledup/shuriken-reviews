<?php
/**
 * Shuriken Reviews Analytics Class
 *
 * Provides reusable methods for fetching rating statistics and analytics data.
 * Can be used in admin pages, shortcodes, REST API, or widgets.
 * Uses Shuriken_Database for core database operations.
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
class Shuriken_Analytics implements Shuriken_Analytics_Interface {

    use Shuriken_Analytics_Helpers;

    private readonly Shuriken_Analytics_Formatter $formatter;
    private readonly Shuriken_Analytics_Ranking $ranking;
    private readonly Shuriken_Analytics_Context $context;
    private readonly Shuriken_Analytics_Dashboard $dashboard;
    private readonly Shuriken_Analytics_Rating_Stats $rating_stats;

    /**
     * Constructor
     *
     * @param Shuriken_Rating_Repository $db Rating repository.
     */
    public function __construct(
        private readonly Shuriken_Rating_Repository $db,
    ) {
        $wpdb = $this->db->get_wpdb();
        $ratings_table = $this->db->get_ratings_table();
        $votes_table = $this->db->get_votes_table();

        $this->formatter = new Shuriken_Analytics_Formatter();
        $this->ranking = new Shuriken_Analytics_Ranking($wpdb, $ratings_table, $votes_table);
        $this->context = new Shuriken_Analytics_Context($wpdb, $ratings_table, $votes_table, $this->db);
        $this->dashboard = new Shuriken_Analytics_Dashboard($wpdb, $ratings_table, $votes_table);
        $this->rating_stats = new Shuriken_Analytics_Rating_Stats(
            $wpdb,
            $ratings_table,
            $votes_table,
            $this->db,
            $this->dashboard
        );
    }

    public function parse_date_range_params(array $params): string|array {
        return $this->rating_stats->parse_date_range_params($params);
    }

    public function get_date_range_label(string|int|array $date_range): string {
        return $this->formatter->get_date_range_label($date_range);
    }

    public function format_average_display(float $average, string $rating_type = 'stars', int $scale = Shuriken_Database::RATING_SCALE_DEFAULT, int $total_votes = 0, int $total_rating = 0): string {
        return $this->formatter->format_average_display($average, $rating_type, $scale, $total_votes, $total_rating);
    }

    public function format_vote_display(float|int $rating_value, string $rating_type = 'stars', int $scale = Shuriken_Database::RATING_SCALE_DEFAULT): string {
        return $this->formatter->format_vote_display($rating_value, $rating_type, $scale);
    }

    public function get_overall_stats(): object {
        return apply_filters('shuriken_overall_stats', $this->dashboard->get_overall_stats());
    }

    public function get_contextual_post_count(): int {
        return $this->dashboard->get_contextual_post_count();
    }

    public function get_rating_type_counts(): object {
        return $this->dashboard->get_rating_type_counts();
    }

    public function get_vote_counts(string|int|array $date_range = 'all'): object {
        return $this->dashboard->get_vote_counts($date_range);
    }

    public function get_vote_change_percent(string|int|array $date_range): ?float {
        return $this->dashboard->get_vote_change_percent($date_range);
    }

    public function get_rating_vote_change_percent(int $rating_id, string|int|array $date_range): ?float {
        return $this->dashboard->get_rating_vote_change_percent($rating_id, $date_range);
    }

    public function get_type_benchmark(string $rating_type, string|int|array $date_range = 'all'): object {
        return $this->dashboard->get_type_benchmark($rating_type, $date_range);
    }

    public function get_top_rated(int $limit = 10, int $min_votes = 1, float $min_average = 3.0, string|int|array $date_range = 'all'): array {
        $result = $this->ranking->get_top_rated($limit, $min_votes, $min_average, $date_range);
        return apply_filters('shuriken_top_rated', $result, $limit, $min_votes, $min_average, $date_range);
    }

    public function get_most_voted(int $limit = 10, string|int|array $date_range = 'all'): array {
        $result = $this->ranking->get_most_voted($limit, $date_range);
        return apply_filters('shuriken_most_voted', $result, $limit, $date_range);
    }

    public function get_low_performers(int $limit = 10, int $min_votes = 1, float $max_average = 3.0, string|int|array $date_range = 'all'): array {
        $result = $this->ranking->get_low_performers($limit, $min_votes, $max_average, $date_range);
        return apply_filters('shuriken_low_performers', $result, $limit, $min_votes, $max_average, $date_range);
    }

    public function get_sub_ratings_contribution(int $parent_id): array {
        return $this->rating_stats->get_sub_ratings_contribution($parent_id);
    }

    public function get_rating_distribution(string|int|array $date_range = 'all', ?int $rating_id = null, ?string $scope = null): array {
        return $this->rating_stats->get_rating_distribution($date_range, $rating_id, $scope);
    }

    public function get_votes_over_time(string|int|array $date_range = 30, ?int $rating_id = null, ?string $scope = null): array {
        return $this->rating_stats->get_votes_over_time($date_range, $rating_id, $scope);
    }

    public function get_recent_votes(int $limit = 10, ?int $rating_id = null, string|int|array $date_range = 'all'): array {
        return $this->rating_stats->get_recent_votes($limit, $rating_id, $date_range);
    }

    public function get_rating(int $rating_id): ?object {
        return $this->rating_stats->get_rating($rating_id);
    }

    public function get_rating_stats(int $rating_id, string|int|array $date_range = 'all', ?string $scope = null): ?object {
        return $this->rating_stats->get_rating_stats($rating_id, $date_range, $scope);
    }

    public function get_parent_rating_stats_breakdown(int $rating_id, string|int|array $date_range = 'all', ?string $scope = null): ?object {
        return $this->rating_stats->get_parent_rating_stats_breakdown($rating_id, $date_range, $scope);
    }

    public function get_rating_votes_paginated(int $rating_id, int $page = 1, int $per_page = 20, string|int|array $date_range = 'all', string $view = 'direct', string $sort_by = 'date', string $sort_order = 'desc', ?string $scope = null): object {
        return $this->rating_stats->get_rating_votes_paginated($rating_id, $page, $per_page, $date_range, $view, $sort_by, $sort_order, $scope);
    }

    public function format_time_ago(string $mysql_date): string {
        return $this->formatter->format_time_ago($mysql_date);
    }

    public function format_date(string $mysql_date, bool $include_time = true): string {
        return $this->formatter->format_date($mysql_date, $include_time);
    }

    public function get_chart_data(string|int|array $date_range = 30, ?int $rating_id = null): array {
        return $this->rating_stats->get_chart_data($date_range, $rating_id);
    }

    public function has_sub_ratings(int $rating_id): bool {
        return $this->rating_stats->has_sub_ratings($rating_id);
    }

    public function get_voting_heatmap(string|int|array $date_range = 'all'): array {
        return $this->dashboard->get_voting_heatmap($date_range);
    }

    public function get_votes_over_time_by_type(string|int|array $date_range = 30): array {
        return $this->dashboard->get_votes_over_time_by_type($date_range);
    }

    public function get_per_type_summary(): array {
        return $this->dashboard->get_per_type_summary();
    }

    public function get_participation_rate(): object {
        return $this->dashboard->get_participation_rate();
    }

    public function get_momentum_items(string|int|array $date_range = 30, int $limit = 3): object {
        return $this->dashboard->get_momentum_items($date_range, $limit);
    }

    public function get_approval_trend(int $rating_id, string|int|array $date_range = 30, ?string $scope = null): array {
        return $this->rating_stats->get_approval_trend($rating_id, $date_range, $scope);
    }

    public function get_cumulative_approvals(int $rating_id, string|int|array $date_range = 30, ?string $scope = null): array {
        return $this->rating_stats->get_cumulative_approvals($rating_id, $date_range, $scope);
    }

    public function get_votes_with_rolling_avg(int $rating_id, string|int|array $date_range = 30, int $scale = Shuriken_Database::RATING_SCALE_DEFAULT, ?string $scope = null): array {
        return $this->rating_stats->get_votes_with_rolling_avg($rating_id, $date_range, $scale, $scope);
    }

    public function get_votes_with_rolling_avg_for_ids(array $rating_ids, string|int|array $date_range = 30, int $scale = Shuriken_Database::RATING_SCALE_DEFAULT): array {
        return $this->rating_stats->get_votes_with_rolling_avg_for_ids($rating_ids, $date_range, $scale);
    }

    public function build_scope_condition(?string $scope, string $prefix = ''): string {
        return $this->rating_stats->build_scope_condition($scope, $prefix);
    }

    public function has_contextual_votes(int $rating_id): bool {
        return $this->context->has_contextual_votes($rating_id);
    }

    public function get_rating_stats_scoped(int $rating_id, string|int|array $date_range = 'all', ?string $scope = null): ?object {
        return $this->rating_stats->get_rating_stats($rating_id, $date_range, $scope);
    }

    public function get_rating_distribution_scoped(string|int|array $date_range = 'all', ?int $rating_id = null, ?string $scope = null): array {
        return $this->rating_stats->get_rating_distribution($date_range, $rating_id, $scope);
    }

    public function get_votes_over_time_scoped(string|int|array $date_range = 30, ?int $rating_id = null, ?string $scope = null): array {
        return $this->rating_stats->get_votes_over_time($date_range, $rating_id, $scope);
    }

    public function get_rating_votes_paginated_scoped(int $rating_id, int $page = 1, int $per_page = 20, string|int|array $date_range = 'all', string $view = 'direct', ?string $scope = null, string $sort_by = 'date', string $sort_order = 'desc'): object {
        return $this->rating_stats->get_rating_votes_paginated($rating_id, $page, $per_page, $date_range, $view, $sort_by, $sort_order, $scope);
    }

    public function get_votes_with_rolling_avg_scoped(int $rating_id, string|int|array $date_range = 30, int $scale = Shuriken_Database::RATING_SCALE_DEFAULT, ?string $scope = null): array {
        return $this->rating_stats->get_votes_with_rolling_avg($rating_id, $date_range, $scale, $scope);
    }

    public function get_approval_trend_scoped(int $rating_id, string|int|array $date_range = 30, ?string $scope = null): array {
        return $this->rating_stats->get_approval_trend($rating_id, $date_range, $scope);
    }

    public function get_cumulative_approvals_scoped(int $rating_id, string|int|array $date_range = 30, ?string $scope = null): array {
        return $this->rating_stats->get_cumulative_approvals($rating_id, $date_range, $scope);
    }

    public function get_rating_context_summary(int $rating_id, string|int|array $date_range = 'all'): object {
        return $this->context->get_rating_context_summary($rating_id, $date_range);
    }

    public function get_rating_contexts_paginated(int $rating_id, int $page = 1, int $per_page = 20, string|int|array $date_range = 'all', string $sort_by = 'votes', string $sort_order = 'desc'): object {
        return $this->context->get_rating_contexts_paginated($rating_id, $page, $per_page, $date_range, $sort_by, $sort_order);
    }

    public function get_top_contexts_by_votes(int $rating_id, int $limit = 10, string|int|array $date_range = 'all'): array {
        return $this->context->get_top_contexts_by_votes($rating_id, $limit, $date_range);
    }

    public function get_context_avg_distribution(int $rating_id, string|int|array $date_range = 'all'): array {
        return $this->context->get_context_avg_distribution($rating_id, $date_range);
    }

    public function get_trending_contexts(int $rating_id, string|int|array $date_range = 30, int $limit = 5, string $sort_by = 'velocity', string $sort_order = 'desc'): array {
        return $this->context->get_trending_contexts($rating_id, $date_range, $limit, $sort_by, $sort_order);
    }

    public function get_context_rating_stats(int $rating_id, int $context_id, string $context_type, string|int|array $date_range = 'all'): ?object {
        return $this->context->get_context_rating_stats($rating_id, $context_id, $context_type, $date_range);
    }

    public function get_context_votes_paginated(int $rating_id, int $context_id, string $context_type, int $page = 1, int $per_page = 20, string|int|array $date_range = 'all', string $sort_by = 'date', string $sort_order = 'desc'): object {
        return $this->context->get_context_votes_paginated($rating_id, $context_id, $context_type, $page, $per_page, $date_range, $sort_by, $sort_order);
    }

    public function get_context_votes_with_rolling_avg(int $rating_id, int $context_id, string $context_type, string|int|array $date_range = 30, int $scale = Shuriken_Database::RATING_SCALE_DEFAULT): array {
        return $this->context->get_context_votes_with_rolling_avg($rating_id, $context_id, $context_type, $date_range, $scale);
    }

    public function get_context_approval_trend(int $rating_id, int $context_id, string $context_type, string|int|array $date_range = 30): array {
        return $this->context->get_context_approval_trend($rating_id, $context_id, $context_type, $date_range);
    }

    public function get_context_cumulative_approvals(int $rating_id, int $context_id, string $context_type, string|int|array $date_range = 30): array {
        return $this->context->get_context_cumulative_approvals($rating_id, $context_id, $context_type, $date_range);
    }
}
