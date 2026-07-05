<?php
/**
 * Shuriken Reviews Analytics Rating Stats Interface
 *
 * @package Shuriken_Reviews
 * @since 1.15.6
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface Shuriken_Analytics_Rating_Stats_Interface
 *
 * Contract for per-rating statistics and breakdown queries.
 *
 * @since 1.15.6
 */
interface Shuriken_Analytics_Rating_Stats_Interface {

    public function parse_date_range_params(array $params): string|array;

    public function get_sub_ratings_contribution(int $parent_id): array;

    public function get_rating_distribution(string|int|array $date_range = 'all', ?int $rating_id = null, ?string $scope = null): array;

    public function get_votes_over_time(string|int|array $date_range = 30, ?int $rating_id = null, ?string $scope = null): array;

    public function get_recent_votes(int $limit = 10, ?int $rating_id = null, string|int|array $date_range = 'all'): array;

    public function get_rating(int $rating_id): ?object;

    public function get_rating_stats(int $rating_id, string|int|array $date_range = 'all', ?string $scope = null): ?object;

    public function get_parent_rating_stats_breakdown(int $rating_id, string|int|array $date_range = 'all', ?string $scope = null): ?object;

    public function get_rating_votes_paginated(int $rating_id, int $page = 1, int $per_page = 20, string|int|array $date_range = 'all', string $view = 'direct', string $sort_by = 'date', string $sort_order = 'desc', ?string $scope = null): object;

    public function get_chart_data(string|int|array $date_range = 30, ?int $rating_id = null): array;

    public function has_sub_ratings(int $rating_id): bool;

    public function get_approval_trend(int $rating_id, string|int|array $date_range = 30, ?string $scope = null): array;

    public function get_cumulative_approvals(int $rating_id, string|int|array $date_range = 30, ?string $scope = null): array;

    public function get_votes_with_rolling_avg(int $rating_id, string|int|array $date_range = 30, int $scale = Shuriken_Database::RATING_SCALE_DEFAULT, ?string $scope = null): array;

    public function get_votes_with_rolling_avg_for_ids(array $rating_ids, string|int|array $date_range = 30, int $scale = Shuriken_Database::RATING_SCALE_DEFAULT): array;

    public function build_scope_condition(?string $scope, string $prefix = ''): string;
}
