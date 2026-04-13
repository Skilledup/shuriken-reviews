<?php
/**
 * Shuriken Reviews Database Façade
 *
 * Provides the unified database interface by delegating to focused repositories.
 * Maintains backward compatibility — all callers continue using shuriken_db().
 *
 * @package Shuriken_Reviews
 * @since 1.3.5
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_Database
 *
 * Façade that delegates to Shuriken_Rating_Repository, Shuriken_Vote_Repository,
 * and Shuriken_Schema_Manager while preserving the Shuriken_Database_Interface contract.
 *
 * @since 1.3.5
 */
class Shuriken_Database implements Shuriken_Database_Interface {

    /**
     * @var Shuriken_Database|null Singleton instance
     */
    private static ?self $instance = null;

    /**
     * @var \wpdb WordPress database instance
     */
    private readonly \wpdb $wpdb;

    /**
     * @var string Ratings table name
     */
    private readonly string $ratings_table;

    /**
     * @var string Votes table name
     */
    private readonly string $votes_table;

    /**
     * @var Shuriken_Rating_Repository Rating operations
     */
    private readonly Shuriken_Rating_Repository $ratings;

    /**
     * @var Shuriken_Vote_Repository Vote operations
     */
    private readonly Shuriken_Vote_Repository $votes;

    /**
     * @var Shuriken_Schema_Manager Schema operations
     */
    private readonly Shuriken_Schema_Manager $schema;

    /**
     * Default internal normalization scale (all votes are stored on a 0–5 scale).
     */
    public const RATING_SCALE_DEFAULT = 5;

    /**
     * Minimum allowed scale for any rating type.
     */
    public const SCALE_MIN = 2;

    /**
     * Maximum allowed scale for star ratings.
     */
    public const STARS_SCALE_MAX = 10;

    /**
     * Maximum allowed scale for numeric ratings.
     */
    public const NUMERIC_SCALE_MAX = 100;

    /**
     * Default number of ratings per page in admin list.
     */
    public const RATINGS_PER_PAGE_DEFAULT = 20;

    /**
     * Maximum IDs allowed in a single batch request.
     */
    public const BATCH_IDS_MAX = 50;

    /**
     * Maximum results for a search query.
     */
    public const SEARCH_LIMIT_MAX = 100;

    // =========================================================================
    // Singleton + Accessors
    // =========================================================================

    /**
     * Constructor
     */
    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->ratings_table = $wpdb->prefix . 'shuriken_ratings';
        $this->votes_table = $wpdb->prefix . 'shuriken_votes';

        $this->ratings = new Shuriken_Rating_Repository($this->wpdb, $this->ratings_table, $this->votes_table);
        $this->votes = new Shuriken_Vote_Repository($this->wpdb, $this->ratings_table, $this->votes_table);
        $this->schema = new Shuriken_Schema_Manager($this->wpdb, $this->ratings_table, $this->votes_table);
    }

    /**
     * Get singleton instance
     *
     * @return Shuriken_Database
     */
    public static function get_instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get the ratings table name
     *
     * @return string
     */
    public function get_ratings_table(): string {
        return $this->ratings_table;
    }

    /**
     * Get the votes table name
     *
     * @return string
     */
    public function get_votes_table(): string {
        return $this->votes_table;
    }

    /**
     * Get the wpdb instance
     *
     * @return \wpdb
     */
    public function get_wpdb(): \wpdb {
        return $this->wpdb;
    }

    // =========================================================================
    // Static Helpers
    // =========================================================================

    /**
     * Normalize a raw vote value to the internal storage scale.
     *
     * @param float  $rating_value Raw vote value from the user.
     * @param string $rating_type  Rating type (stars, like_dislike, numeric, approval).
     * @param int    $scale        The rating's display scale.
     * @return float Normalized value for storage.
     * @throws Shuriken_Validation_Exception If the value is out of range for the type.
     */
    public static function normalize_vote_value(float $rating_value, string $rating_type, int $scale): float {
        $type = Shuriken_Rating_Type::tryFrom($rating_type);
        if ($type === null) {
            throw Shuriken_Validation_Exception::invalid_value('rating_type', $rating_type, implode(', ', Shuriken_Rating_Type::values()));
        }

        if ($type === Shuriken_Rating_Type::LikeDislike) {
            $int_value = intval($rating_value);
            if ($int_value !== 0 && $int_value !== 1) {
                throw Shuriken_Validation_Exception::invalid_rating_value($rating_value, 1);
            }
            return (float) $int_value;
        }

        if ($type === Shuriken_Rating_Type::Approval) {
            $int_value = intval($rating_value);
            if ($int_value !== 1) {
                throw Shuriken_Validation_Exception::invalid_rating_value($rating_value, 1);
            }
            return 1.0;
        }

        // Stars/numeric: validate against scale, normalize to 1–RATING_SCALE_DEFAULT
        if ($rating_value < 1 || $rating_value > $scale) {
            throw Shuriken_Validation_Exception::invalid_rating_value($rating_value, $scale);
        }

        $normalized = ($rating_value / $scale) * self::RATING_SCALE_DEFAULT;
        $normalized = round($normalized, 2);
        return (float) max(1, min(self::RATING_SCALE_DEFAULT, $normalized));
    }

    /**
     * Convert a normalized average back to the display scale.
     *
     * @param float $average Normalized average (0–5 internal scale).
     * @param int   $scale   The rating's display scale.
     * @return float Scaled average for display.
     */
    public static function denormalize_average(float $average, int $scale): float {
        return round(($average / self::RATING_SCALE_DEFAULT) * $scale, 1);
    }

    // =========================================================================
    // Rating Delegation
    // =========================================================================

    /** @inheritDoc */
    public function get_rating(int $rating_id): ?object {
        return $this->ratings->get_rating($rating_id);
    }

    /** @inheritDoc */
    public function get_all_ratings(string $orderby = 'id', string $order = 'DESC'): array {
        return $this->ratings->get_all_ratings($orderby, $order);
    }

    /** @inheritDoc */
    public function get_ratings_paginated(int $per_page = 20, int $page = 1, string $search = '', string $orderby = 'id', string $order = 'DESC'): object {
        return $this->ratings->get_ratings_paginated($per_page, $page, $search, $orderby, $order);
    }

    /** @inheritDoc */
    public function create_rating(string $name, ?int $parent_id = null, string $effect_type = 'positive', bool $display_only = false, ?int $mirror_of = null, string $rating_type = 'stars', int $scale = self::RATING_SCALE_DEFAULT): int {
        return $this->ratings->create_rating($name, $parent_id, $effect_type, $display_only, $mirror_of, $rating_type, $scale);
    }

    /** @inheritDoc */
    public function update_rating(int $rating_id, array $data): bool {
        return $this->ratings->update_rating($rating_id, $data);
    }

    /** @inheritDoc */
    public function delete_rating(int $rating_id): bool {
        return $this->ratings->delete_rating($rating_id);
    }

    /** @inheritDoc */
    public function get_sub_ratings(int $parent_id): array {
        return $this->ratings->get_sub_ratings($parent_id);
    }

    /** @inheritDoc */
    public function get_parent_ratings(?int $exclude_id = null): array {
        return $this->ratings->get_parent_ratings($exclude_id);
    }

    /** @inheritDoc */
    public function recalculate_parent_rating(int $parent_id): bool {
        return $this->ratings->recalculate_parent_rating($parent_id);
    }

    /** @inheritDoc */
    public function get_mirrorable_ratings(?int $exclude_id = null): array {
        return $this->ratings->get_mirrorable_ratings($exclude_id);
    }

    /** @inheritDoc */
    public function get_mirrors(int $rating_id): array {
        return $this->ratings->get_mirrors($rating_id);
    }

    /** @inheritDoc */
    public function get_ratings_by_ids(array $ids): array {
        return $this->ratings->get_ratings_by_ids($ids);
    }

    /** @inheritDoc */
    public function search_ratings(string $search_term, int $limit = 20, string $type = 'all'): array {
        return $this->ratings->search_ratings($search_term, $limit, $type);
    }

    /** @inheritDoc */
    public function get_contextual_stats(int $rating_id, int $context_id, string $context_type, int $scale = self::RATING_SCALE_DEFAULT): object {
        return $this->ratings->get_contextual_stats($rating_id, $context_id, $context_type, $scale);
    }

    /** @inheritDoc */
    public function get_contextual_stats_batch(array $rating_ids, int $context_id, string $context_type, array $scales = array()): array {
        return $this->ratings->get_contextual_stats_batch($rating_ids, $context_id, $context_type, $scales);
    }

    /** @inheritDoc */
    public function get_context_usage_counts(): array {
        return $this->ratings->get_context_usage_counts();
    }

    /** @inheritDoc */
    public function get_global_vote_counts(): array {
        return $this->ratings->get_global_vote_counts();
    }

    /** @inheritDoc */
    public function get_ratings_for_context(int $context_id, string $context_type): array {
        return $this->ratings->get_ratings_for_context($context_id, $context_type);
    }

    /**
     * Get child ratings of a parent rating
     *
     * @param int $parent_id The parent rating ID
     * @return array Array of child rating objects
     * @since 1.9.0
     */
    public function get_child_ratings(int $parent_id): array {
        return $this->ratings->get_child_ratings($parent_id);
    }

    /**
     * Delete multiple ratings and their associated votes
     *
     * @param array $rating_ids Array of rating IDs
     * @return int Number of deleted ratings
     */
    public function delete_ratings(array $rating_ids): int {
        return $this->ratings->delete_ratings($rating_ids);
    }

    /**
     * Get all ratings with calculated averages for export
     *
     * @return array Array of rating objects
     */
    public function get_ratings_for_export(): array {
        return $this->ratings->get_ratings_for_export();
    }

    /**
     * Get all votes for a rating for export
     *
     * @param int $rating_id Rating ID
     * @return array Array of vote objects with user info
     */
    public function get_votes_for_export(int $rating_id): array {
        return $this->ratings->get_votes_for_export($rating_id);
    }

    // =========================================================================
    // Vote Delegation
    // =========================================================================

    /** @inheritDoc */
    public function get_user_vote(int $rating_id, int $user_id, ?string $user_ip = null, ?int $context_id = null, ?string $context_type = null): ?object {
        return $this->votes->get_user_vote($rating_id, $user_id, $user_ip, $context_id, $context_type);
    }

    /** @inheritDoc */
    public function create_vote(int $rating_id, float|int $rating_value, int $user_id = 0, ?string $user_ip = null, ?int $context_id = null, ?string $context_type = null): bool {
        return $this->votes->create_vote($rating_id, $rating_value, $user_id, $user_ip, $context_id, $context_type);
    }

    /** @inheritDoc */
    public function update_vote(int $vote_id, int $rating_id, float|int $old_value, float|int $new_value): bool {
        return $this->votes->update_vote($vote_id, $rating_id, $old_value, $new_value);
    }

    /** @inheritDoc */
    public function get_last_vote_time(int $rating_id, int $user_id, ?string $user_ip = null, ?int $context_id = null, ?string $context_type = null): ?string {
        return $this->votes->get_last_vote_time($rating_id, $user_id, $user_ip, $context_id, $context_type);
    }

    /** @inheritDoc */
    public function count_votes_since(int $user_id, ?string $user_ip, string $since): int {
        return $this->votes->count_votes_since($user_id, $user_ip, $since);
    }

    /** @inheritDoc */
    public function get_oldest_vote_in_window(int $user_id, ?string $user_ip, int $window_seconds): ?string {
        return $this->votes->get_oldest_vote_in_window($user_id, $user_ip, $window_seconds);
    }

    // =========================================================================
    // Schema Delegation
    // =========================================================================

    /** @inheritDoc */
    public function create_tables(): bool {
        return $this->schema->create_tables();
    }

    /** @inheritDoc */
    public function tables_exist(): bool {
        return $this->schema->tables_exist();
    }

    /**
     * Run database migrations
     *
     * @param string $current_version Current DB version
     * @return bool True on success
     */
    public function run_migrations(string $current_version): bool {
        return $this->schema->run_migrations($current_version);
    }
}
