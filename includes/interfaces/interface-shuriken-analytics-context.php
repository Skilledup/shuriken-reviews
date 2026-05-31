<?php
/**
 * Shuriken Reviews Analytics Context Interface
 *
 * Defines the contract for per-post / contextual analytics operations. Split out
 * of the monolithic Shuriken_Analytics_Interface so add-on decorators can
 * implement only the contextual concern they need.
 *
 * @package Shuriken_Reviews
 * @since 1.15.6
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface Shuriken_Analytics_Context_Interface
 *
 * Contract for contextual (per-post) analytics operations.
 *
 * @since 1.15.6
 */
interface Shuriken_Analytics_Context_Interface {

    /**
     * Check if a rating has any contextual (per-post) votes
     *
     * @param int $rating_id Rating ID.
     * @return bool
     */
    public function has_contextual_votes(int $rating_id): bool;

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
