<?php
/**
 * Shuriken Reviews Analytics Dashboard Interface
 *
 * @package Shuriken_Reviews
 * @since 1.15.6
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface Shuriken_Analytics_Dashboard_Interface
 *
 * Contract for site-wide dashboard analytics operations.
 *
 * @since 1.15.6
 */
interface Shuriken_Analytics_Dashboard_Interface {

    public function get_overall_stats(): object;

    public function get_contextual_post_count(): int;

    public function get_rating_type_counts(): object;

    public function get_vote_counts(string|int|array $date_range = 'all'): object;

    public function get_vote_change_percent(string|int|array $date_range): ?float;

    public function get_rating_vote_change_percent(int $rating_id, string|int|array $date_range): ?float;

    public function get_type_benchmark(string $rating_type, string|int|array $date_range = 'all'): object;

    public function get_voting_heatmap(string|int|array $date_range = 'all'): array;

    public function get_votes_over_time_by_type(string|int|array $date_range = 30): array;

    public function get_per_type_summary(): array;

    public function get_participation_rate(): object;

    public function get_momentum_items(string|int|array $date_range = 30, int $limit = 3): object;
}
