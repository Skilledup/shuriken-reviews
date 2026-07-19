<?php
/**
 * Shuriken Reviews Cache Service
 *
 * TTL-based statistics cache backed by wp_cache_* (Redis/Memcached when available).
 *
 * @package Shuriken_Reviews
 * @since 1.15.6
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_Cache
 *
 * @since 1.15.6
 */
class Shuriken_Cache implements Shuriken_Cache_Interface {

    /**
     * Constructor — register targeted cache invalidation hooks.
     *
     * @param \wpdb  $wpdb          WordPress database instance.
     * @param string $ratings_table Prefixed ratings table name.
     */
    public function __construct(
        private readonly \wpdb $wpdb,
        private readonly string $ratings_table,
    ) {
        add_action('shuriken_vote_created', $this->handle_vote_created(...), 1, 9);
        add_action('shuriken_vote_updated', $this->handle_vote_updated(...), 1, 10);
        add_action('shuriken_rating_updated', $this->handle_rating_updated(...), 1, 2);
        add_action('shuriken_before_delete_rating', $this->handle_rating_deleting(...), 1);
        add_action('shuriken_rating_deleted', $this->handle_rating_deleted(...), 1);
    }

    /**
     * @inheritDoc
     */
    public function key(string ...$parts): string {
        return implode(':', array_map(
            static fn(string $part): string => str_replace(array(':', ' '), '_', $part),
            $parts
        ));
    }

    /**
     * @inheritDoc
     */
    public function get(string $key): mixed {
        $found = false;
        $value = wp_cache_get($key, self::GROUP, false, $found);

        return $found ? $value : false;
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool {
        return wp_cache_set($key, $value, self::GROUP, $ttl ?? $this->get_default_ttl());
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): bool {
        return wp_cache_delete($key, self::GROUP);
    }

    /**
     * @inheritDoc
     */
    public function get_default_ttl(): int {
        /**
         * Filter the default TTL for statistics cache entries.
         *
         * @since 1.15.6
         * @param int $ttl TTL in seconds. Default 60.
         */
        return max(1, (int) apply_filters('shuriken_stats_cache_ttl', 60));
    }

    /**
     * Invalidate caches after a new vote.
     *
     * @param int         $rating_id        Rating ID.
     * @param float       $rating_value     Display-scale value.
     * @param float       $normalized_value Normalized value.
     * @param int         $user_id          User ID.
     * @param string      $user_ip          Guest IP.
     * @param object      $rating           Rating object.
     * @param int         $max_stars        Display scale.
     * @param int|null    $context_id       Context ID.
     * @param string|null $context_type     Context type.
     * @return void
     */
    public function handle_vote_created(int $rating_id, float $rating_value, float $normalized_value, int $user_id, ?string $user_ip, object $rating, int $max_stars, ?int $context_id, ?string $context_type): void {
        $this->invalidate_vote($rating_id, $rating, $context_id, $context_type);
    }

    /**
     * Invalidate caches after a vote update.
     *
     * @param int         $vote_id          Vote ID.
     * @param int         $rating_id        Rating ID.
     * @param float       $old_value        Previous normalized value.
     * @param float       $new_value        New display-scale value.
     * @param float       $normalized_value New normalized value.
     * @param int         $user_id          User ID.
     * @param object      $rating           Rating object.
     * @param int         $max_stars        Display scale.
     * @param int|null    $context_id       Context ID.
     * @param string|null $context_type     Context type.
     * @return void
     */
    public function handle_vote_updated(int $vote_id, int $rating_id, float $old_value, float $new_value, float $normalized_value, int $user_id, object $rating, int $max_stars, ?int $context_id, ?string $context_type): void {
        $this->invalidate_vote($rating_id, $rating, $context_id, $context_type);
    }

    /**
     * Invalidate persistent rating metadata after an update.
     *
     * @param int   $rating_id  Updated rating ID.
     * @param array $update_data Updated fields.
     * @return void
     */
    public function handle_rating_updated(int $rating_id, array $update_data): void {
        $this->delete_rating($rating_id);
    }

    /**
     * Invalidate the rating and cached child metadata before delete relations change.
     *
     * @param int $rating_id Rating being deleted.
     * @return void
     */
    public function handle_rating_deleting(int $rating_id): void {
        $this->invalidate_archive_queries();

        if (!$this->is_enabled()) {
            return;
        }

        $this->delete_rating($rating_id);

        $child_ids = $this->wpdb->get_col($this->wpdb->prepare(
            "SELECT id FROM {$this->ratings_table} WHERE parent_id = %d",
            $rating_id
        ));
        foreach ($child_ids as $child_id) {
            $this->delete_rating((int) $child_id);
        }

    }

    /**
     * Ensure a deleted rating's direct persistent entry is gone.
     *
     * @param int $rating_id Deleted rating ID.
     * @return void
     */
    public function handle_rating_deleted(int $rating_id): void {
        $this->delete_rating($rating_id);
    }

    /**
     * Invalidate global and contextual entries affected by a vote.
     *
     * @param int         $rating_id    Rating ID.
     * @param object      $rating       Resolved rating object.
     * @param int|null    $context_id   Context ID.
     * @param string|null $context_type Context type.
     * @return void
     */
    private function invalidate_vote(int $rating_id, object $rating, ?int $context_id, ?string $context_type): void {
        $source_id = (int) ($rating->source_id ?? $rating_id);
        $parent_id = !empty($rating->parent_id) ? (int) $rating->parent_id : 0;
        $cache_enabled = $this->is_enabled();

        if ($cache_enabled) {
            $this->delete_rating($rating_id);
            if ($source_id !== $rating_id) {
                $this->delete_rating($source_id);
            }
            if ($parent_id > 0) {
                $this->delete_rating($parent_id);
            }
        }

        if ($context_id === null || $context_type === null || $context_type === '') {
            return;
        }

        if ($cache_enabled) {
            $this->delete($this->contextual_stats_key($source_id, $context_id, $context_type));
            if ($rating_id !== $source_id) {
                $this->delete($this->contextual_stats_key($rating_id, $context_id, $context_type));
            }
            if ($parent_id > 0) {
                $this->delete($this->contextual_stats_key($parent_id, $context_id, $context_type));
            }
        }

        $this->invalidate_archive_queries();
    }

    /**
     * Delete a resolved rating entry.
     *
     * @param int $rating_id Rating ID.
     * @return void
     */
    private function delete_rating(int $rating_id): void {
        if ($rating_id > 0 && $this->is_enabled()) {
            $this->delete($this->key('rating', (string) $rating_id));
        }
    }

    /**
     * Build a scale-independent contextual statistics key.
     *
     * @param int    $rating_id    Rating ID.
     * @param int    $context_id   Context ID.
     * @param string $context_type Context type.
     * @return string
     */
    private function contextual_stats_key(int $rating_id, int $context_id, string $context_type): string {
        return $this->key('context', (string) $rating_id, (string) $context_id, $context_type);
    }

    /**
     * Change the generation used by WordPress's cached archive query results.
     *
     * @return void
     */
    private function invalidate_archive_queries(): void {
        wp_cache_set_last_changed('shuriken_archive');
    }

    /**
     * Whether the persistent statistics layer is enabled.
     *
     * @return bool
     */
    public function is_enabled(): bool {
        return (bool) apply_filters('shuriken_stats_cache_enabled', wp_using_ext_object_cache());
    }
}
