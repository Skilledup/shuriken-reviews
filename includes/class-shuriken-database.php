<?php
/**
 * Shuriken Reviews Database Class
 *
 * Provides core database operations for ratings and votes.
 * Handles CRUD operations, table management, and common queries.
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
 * Core database handler for the Shuriken Reviews plugin.
 *
 * @since 1.3.5
 */
class Shuriken_Database implements Shuriken_Database_Interface {

    /**
     * @var \wpdb WordPress database instance
     */
    private \wpdb $wpdb;

    /**
     * @var string Ratings table name
     */
    private string $ratings_table;

    /**
     * @var string Votes table name
     */
    private string $votes_table;

    /**
     * @var Shuriken_Database|null Singleton instance
     */
    private static ?self $instance = null;

    /**
     * @var string Standard rating fields for SELECT queries
     */
    private const RATING_FIELDS = 'id, name, total_votes, total_rating, parent_id, effect_type, display_only, mirror_of, rating_type, scale, date_created';

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

    /**
     * Normalize a raw vote value to the internal storage scale.
     *
     * Validates the value against the rating type and scale, then converts
     * stars/numeric votes to the 0–5 internal scale. Binary types pass through.
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

    /**
     * Compute normalized average and attach display_average to a rating/stats object.
     *
     * Every method that computes `->average` MUST call this instead of doing inline math.
     * This ensures `display_average` is always present alongside `average`, so consumers
     * never need to denormalize manually.
     *
     * @param object   $obj   The object to attach averages to (rating, stats, etc.).
     * @param int|null $scale The display scale. If null, reads from $obj->scale (defaults to RATING_SCALE_DEFAULT).
     */
    private static function attach_averages(object $obj, ?int $scale = null): void {
        $s = $scale ?? (isset($obj->scale) ? (int) $obj->scale : self::RATING_SCALE_DEFAULT);
        $obj->average = (isset($obj->total_votes) && $obj->total_votes > 0)
            ? round((float) $obj->total_rating / (int) $obj->total_votes, 2)
            : 0;
        $obj->display_average = self::denormalize_average((float) $obj->average, $s);
    }

    /**
     * Constructor
     */
    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->ratings_table = $wpdb->prefix . 'shuriken_ratings';
        $this->votes_table = $wpdb->prefix . 'shuriken_votes';
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
    // Rating CRUD Operations
    // =========================================================================

    /**
     * Get a single rating by ID
     *
     * @param int $rating_id Rating ID
     * @param bool $with_average Whether to calculate average rating
     * @return object|null Rating object or null if not found
     */
    public function get_rating(int $rating_id): ?object {
        $fields = self::RATING_FIELDS;
        $rating = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT {$fields} FROM {$this->ratings_table} WHERE id = %d",
            $rating_id
        ));

        if ($rating) {
            // Default source_id to self
            $rating->source_id = $rating->id;
            
            // If this is a mirror, get vote data from the original rating
            // but preserve mirror_of so callers can detect it's a mirror
            if (!empty($rating->mirror_of)) {
                $original = $this->get_original_rating($rating->mirror_of);
                if ($original) {
                    // Copy vote data and settings from original
                    $rating->total_votes = $original->total_votes;
                    $rating->total_rating = $original->total_rating;
                    $rating->display_only = $original->display_only;
                    // Store original's ID for data-id attribute
                    $rating->source_id = $original->id;
                }
            }
            
            self::attach_averages($rating);
        }

        return $rating;
    }

    /**
     * Get original rating without mirror resolution (to avoid infinite loops)
     *
     * @param int $rating_id Rating ID
     * @return object|null Rating object or null if not found
     */
    private function get_original_rating(int $rating_id): ?object {
        $fields = self::RATING_FIELDS;
        $rating = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT {$fields} FROM {$this->ratings_table} WHERE id = %d",
            $rating_id
        ));

        if ($rating) {
            self::attach_averages($rating);
        }

        return $rating;
    }

    /**
     * Get all ratings
     *
     * @param string $orderby Column to order by
     * @param string $order ASC or DESC
     * @return array Array of rating objects
     */
    public function get_all_ratings(string $orderby = 'id', string $order = 'DESC'): array {
        $allowed_orderby = array('id', 'name', 'total_votes', 'total_rating', 'date_created', 'parent_id', 'mirror_of');
        $orderby = in_array($orderby, $allowed_orderby, true) ? $orderby : 'id';
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        $fields = self::RATING_FIELDS;

        $ratings = $this->wpdb->get_results(
            "SELECT {$fields} FROM {$this->ratings_table} ORDER BY {$orderby} {$order}"
        );

        foreach ($ratings as &$rating) {
            self::attach_averages($rating);
        }
        unset($rating);

        return $ratings;
    }

    /**
     * Get ratings with pagination
     *
     * @param int $per_page Items per page
     * @param int $page Current page (1-indexed)
     * @param string $search Optional search term
     * @param string $orderby Column to order by
     * @param string $order ASC or DESC
     * @return object Object with ratings, total_count, total_pages, current_page
     */
    public function get_ratings_paginated(int $per_page = 20, int $page = 1, string $search = '', string $orderby = 'id', string $order = 'DESC'): object {
        $offset = ($page - 1) * $per_page;
        $allowed_orderby = array('id', 'name', 'total_votes', 'total_rating', 'date_created', 'parent_id', 'mirror_of');
        $orderby = in_array($orderby, $allowed_orderby, true) ? $orderby : 'id';
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        $result = new stdClass();
        $result->current_page = $page;
        $result->per_page = $per_page;
        $fields = self::RATING_FIELDS;

        if (!empty($search)) {
            $search_like = '%' . $this->wpdb->esc_like($search) . '%';
            
            $result->total_count = (int) $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->ratings_table} WHERE name LIKE %s",
                $search_like
            ));

            $result->ratings = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT {$fields} FROM {$this->ratings_table} 
                 WHERE name LIKE %s 
                 ORDER BY {$orderby} {$order} 
                 LIMIT %d OFFSET %d",
                $search_like,
                $per_page,
                $offset
            ));
        } else {
            $result->total_count = (int) $this->wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->ratings_table}"
            );

            $result->ratings = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT {$fields} FROM {$this->ratings_table} 
                 ORDER BY {$orderby} {$order} 
                 LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ));
        }

        $result->total_pages = ceil($result->total_count / $per_page);

        // Attach average and display_average to each rating
        if (!empty($result->ratings)) {
            foreach ($result->ratings as &$rating) {
                self::attach_averages($rating);
            }
            unset($rating);
        }

        return $result;
    }

    /**
     * Create a new rating
     *
     * @param string $name Rating name
     * @param int|null $parent_id Parent rating ID (optional)
     * @param string $effect_type Effect type on parent: 'positive' or 'negative'
     * @param bool $display_only Whether the rating is display-only (no direct voting)
     * @param int|null $mirror_of Original rating ID to mirror (optional)
     * @return int The new rating ID
     * @throws Shuriken_Database_Exception If insert fails
     * @throws Shuriken_Validation_Exception If name is empty
     */
    public function create_rating(string $name, ?int $parent_id = null, string $effect_type = 'positive', bool $display_only = false, ?int $mirror_of = null, string $rating_type = 'stars', int $scale = self::RATING_SCALE_DEFAULT): int {
        $sanitized_name = sanitize_text_field($name);
        
        if (empty($sanitized_name)) {
            throw Shuriken_Validation_Exception::required_field('name');
        }

        $type_enum = Shuriken_Rating_Type::tryFrom($rating_type) ?? Shuriken_Rating_Type::Stars;
        $rating_type = $type_enum->value;

        // Mirror inherits type and scale from source rating
        if ($mirror_of !== null && $mirror_of > 0) {
            $source = $this->get_rating(intval($mirror_of));
            if ($source) {
                $rating_type = isset($source->rating_type) ? $source->rating_type : 'stars';
                $type_enum = Shuriken_Rating_Type::from($rating_type);
                $scale = isset($source->scale) ? intval($source->scale) : self::RATING_SCALE_DEFAULT;
            }
        }

        // Force scale per type constraints
        $scale = $type_enum->constrainScale(intval($scale));
        
        $insert_data = array(
            'name' => $sanitized_name,
            'effect_type' => in_array($effect_type, array('positive', 'negative'), true) ? $effect_type : 'positive',
            'display_only' => $display_only ? 1 : 0,
            'rating_type' => $rating_type,
            'scale' => $scale,
        );
        $format = array('%s', '%s', '%d', '%s', '%d');

        // Mirror takes precedence - if mirroring, ignore parent_id
        if ($mirror_of !== null && $mirror_of > 0) {
            $insert_data['mirror_of'] = intval($mirror_of);
            $format[] = '%d';
        } elseif ($parent_id !== null && $parent_id > 0) {
            $insert_data['parent_id'] = intval($parent_id);
            $format[] = '%d';
        }

        /**
         * Filter the rating data before insertion.
         *
         * @since 1.7.0
         * @param array $insert_data The data to insert.
         */
        $insert_data = apply_filters('shuriken_before_create_rating', $insert_data);

        $result = $this->wpdb->insert(
            $this->ratings_table,
            $insert_data,
            $format
        );

        if ($result === false) {
            throw Shuriken_Database_Exception::insert_failed('ratings');
        }

        $new_id = $this->wpdb->insert_id;

        /**
         * Fires after a rating is created.
         *
         * @since 1.7.0
         * @param int   $rating_id   The new rating ID.
         * @param array $insert_data The inserted data.
         */
        do_action('shuriken_rating_created', $new_id, $insert_data);

        return $new_id;
    }

    /**
     * Update a rating
     *
     * @param int $rating_id Rating ID
     * @param array $data Data to update (name, total_votes, total_rating, parent_id, effect_type, display_only, mirror_of)
     * @return bool True on success
     * @throws Shuriken_Database_Exception If update fails
     * @throws Shuriken_Validation_Exception If no valid data provided
     */
    public function update_rating(int $rating_id, array $data): bool {
        // Block type/scale changes if rating has votes or is a mirror
        if (isset($data['rating_type']) || isset($data['scale'])) {
            $existing = $this->get_rating($rating_id);
            if ($existing) {
                // Mirrors always inherit source type — silently ignore type/scale changes
                if (!empty($existing->mirror_of)) {
                    unset($data['rating_type'], $data['scale']);
                } elseif ($existing->total_votes > 0) {
                    $type_changed = isset($data['rating_type']) && $data['rating_type'] !== ($existing->rating_type ?? 'stars');
                    $scale_changed = isset($data['scale']) && intval($data['scale']) !== intval($existing->scale ?? self::RATING_SCALE_DEFAULT);
                    if ($type_changed || $scale_changed) {
                        throw Shuriken_Validation_Exception::invalid_value(
                            'rating_type',
                            $data['rating_type'] ?? '',
                            'type and scale cannot be changed when rating has existing votes'
                        );
                    }
                }
            }
        }

        $allowed_fields = array('name', 'total_votes', 'total_rating', 'parent_id', 'effect_type', 'display_only', 'mirror_of', 'rating_type', 'scale');
        $update_data = array();
        $format = array();

        foreach ($data as $key => $value) {
            if (in_array($key, $allowed_fields, true)) {
                if ($key === 'name') {
                    $update_data[$key] = sanitize_text_field($value);
                    $format[] = '%s';
                } elseif ($key === 'effect_type') {
                    $update_data[$key] = in_array($value, array('positive', 'negative'), true) ? $value : 'positive';
                    $format[] = '%s';
                } elseif ($key === 'rating_type') {
                    $type_enum = Shuriken_Rating_Type::tryFrom($value);
                    $update_data[$key] = $type_enum ? $type_enum->value : Shuriken_Rating_Type::Stars->value;
                    $format[] = '%s';
                } elseif ($key === 'scale') {
                    $update_data[$key] = max(1, min(self::NUMERIC_SCALE_MAX, intval($value)));
                    $format[] = '%d';
                } elseif ($key === 'parent_id' || $key === 'mirror_of') {
                    // Allow null for parent_id and mirror_of
                    $update_data[$key] = $value === null || $value === '' || $value === 0 ? null : intval($value);
                    $format[] = $update_data[$key] === null ? null : '%d';
                } elseif ($key === 'display_only') {
                    $update_data[$key] = $value ? 1 : 0;
                    $format[] = '%d';
                } else {
                    $update_data[$key] = intval($value);
                    $format[] = '%d';
                }
            }
        }

        if (empty($update_data)) {
            throw Shuriken_Validation_Exception::invalid_value('data', $data, 'at least one valid field');
        }

        /**
         * Filter the rating data before update.
         *
         * @since 1.7.0
         * @param array $update_data The data to update.
         * @param int   $rating_id   The rating ID.
         */
        $update_data = apply_filters('shuriken_before_update_rating', $update_data, $rating_id);

        $result = $this->wpdb->update(
            $this->ratings_table,
            $update_data,
            array('id' => $rating_id),
            $format,
            array('%d')
        );

        if ($result === false) {
            throw Shuriken_Database_Exception::update_failed('ratings', $rating_id);
        }

        /**
         * Fires after a rating is updated.
         *
         * @since 1.7.0
         * @param int   $rating_id   The rating ID.
         * @param array $update_data The updated data.
         */
        do_action('shuriken_rating_updated', $rating_id, $update_data);

        return true;
    }

    /**
     * Delete a rating and its associated votes
     *
     * @param int $rating_id Rating ID
     * @return bool True on success
     * @throws Shuriken_Database_Exception If delete fails or transaction fails
     */
    public function delete_rating(int $rating_id): bool {
        /**
         * Fires before a rating is deleted.
         *
         * @since 1.7.0
         * @param int $rating_id The rating ID about to be deleted.
         */
        do_action('shuriken_before_delete_rating', $rating_id);

        // Start transaction
        $this->wpdb->query('START TRANSACTION');

        try {
            // First, update any sub-ratings to have no parent
            $this->wpdb->update(
                $this->ratings_table,
                array('parent_id' => null),
                array('parent_id' => $rating_id),
                array(null),
                array('%d')
            );

            // Update any mirrors to have no mirror_of
            $this->wpdb->update(
                $this->ratings_table,
                array('mirror_of' => null),
                array('mirror_of' => $rating_id),
                array(null),
                array('%d')
            );

            // Delete associated votes first
            $this->wpdb->delete(
                $this->votes_table,
                array('rating_id' => $rating_id),
                array('%d')
            );

            // Delete the rating
            $result = $this->wpdb->delete(
                $this->ratings_table,
                array('id' => $rating_id),
                array('%d')
            );

            if ($result === false) {
                $this->wpdb->query('ROLLBACK');
                throw Shuriken_Database_Exception::delete_failed('ratings', $rating_id);
            }

            $this->wpdb->query('COMMIT');

            /**
             * Fires after a rating is deleted.
             *
             * @since 1.7.0
             * @param int $rating_id The deleted rating ID.
             */
            do_action('shuriken_rating_deleted', $rating_id);

            return true;

        } catch (Shuriken_Database_Exception $e) {
            // Re-throw our own exceptions
            throw $e;
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
            throw Shuriken_Database_Exception::transaction_failed();
        }
    }

    /**
     * Get all sub-ratings for a parent rating
     *
     * @param int $parent_id Parent rating ID
     * @return array Array of sub-rating objects
     */
    public function get_sub_ratings(int $parent_id): array {
        $fields = self::RATING_FIELDS;
        $ratings = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT {$fields} FROM {$this->ratings_table} 
             WHERE parent_id = %d 
             ORDER BY name ASC",
            $parent_id
        ));

        foreach ($ratings as &$rating) {
            // Set source_id for voting
            $rating->source_id = $rating->id;
            
            // Calculate average
            self::attach_averages($rating);
        }

        return $ratings;
    }

    /**
     * Get all parent ratings (ratings that can have sub-ratings)
     *
     * @param int|null $exclude_id Rating ID to exclude from results
     * @return array Array of rating objects
     */
    public function get_parent_ratings(?int $exclude_id = null): array {
        $fields = self::RATING_FIELDS;
        if ($exclude_id) {
            return $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT {$fields} FROM {$this->ratings_table} 
                 WHERE parent_id IS NULL AND mirror_of IS NULL AND id != %d 
                 ORDER BY name ASC",
                $exclude_id
            ));
        }

        return $this->wpdb->get_results(
            "SELECT {$fields} FROM {$this->ratings_table} 
             WHERE parent_id IS NULL AND mirror_of IS NULL 
             ORDER BY name ASC"
        );
    }

    /**
     * Calculate and update parent rating totals based on sub-ratings
     *
     * @param int $parent_id Parent rating ID
     * @return bool True on success
     * @throws Shuriken_Database_Exception If update fails
     */
    public function recalculate_parent_rating(int $parent_id): bool {
        // Get all sub-ratings
        $sub_ratings = $this->get_sub_ratings($parent_id);
        
        if (empty($sub_ratings)) {
            return true;
        }

        $total_votes = 0;
        $total_rating = 0;

        foreach ($sub_ratings as $sub) {
            if ($sub->total_votes > 0) {
                $total_votes += $sub->total_votes;
                
                if ($sub->effect_type === 'negative') {
                    // Scale-aware inversion using the INTERNAL normalized scale (1–RATING_SCALE_DEFAULT)
                    // since total_rating is stored normalized, not on the display scale.
                    // Binary [0, 1]: inverted = 1 - value → aggregate = votes * scale - total (scale = 1)
                    // Stars/numeric [1, RATING_SCALE_DEFAULT]: inverted = (RATING_SCALE_DEFAULT + 1) - value
                    $is_binary = (Shuriken_Rating_Type::tryFrom($sub->rating_type) ?? Shuriken_Rating_Type::Stars)->isBinary();
                    $inv_const = $is_binary ? (int) $sub->scale : (self::RATING_SCALE_DEFAULT + 1);
                    $inverted_rating = ($sub->total_votes * $inv_const) - $sub->total_rating;
                    $total_rating += $inverted_rating;
                } else {
                    $total_rating += $sub->total_rating;
                }
            }
        }

        // update_rating() now throws exceptions, so propagate them
        return $this->update_rating($parent_id, array(
            'total_votes' => $total_votes,
            'total_rating' => $total_rating
        ));
    }

    /**
     * Get all ratings that can be mirrored (not already mirrors themselves)
     *
     * @param int|null $exclude_id Rating ID to exclude from results
     * @return array Array of rating objects
     */
    public function get_mirrorable_ratings(?int $exclude_id = null): array {
        $fields = self::RATING_FIELDS;
        if ($exclude_id) {
            return $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT {$fields} FROM {$this->ratings_table} 
                 WHERE mirror_of IS NULL AND id != %d 
                 ORDER BY name ASC",
                $exclude_id
            ));
        }

        return $this->wpdb->get_results(
            "SELECT {$fields} FROM {$this->ratings_table} 
             WHERE mirror_of IS NULL 
             ORDER BY name ASC"
        );
    }

    /**
     * Get all mirrors of a rating
     *
     * @param int $rating_id Rating ID
     * @return array Array of mirror rating objects
     */
    public function get_mirrors(int $rating_id): array {
        $fields = self::RATING_FIELDS;
        $mirrors = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT {$fields} FROM {$this->ratings_table} 
             WHERE mirror_of = %d 
             ORDER BY name ASC",
            $rating_id
        ));

        if (!empty($mirrors)) {
            // Fetch the source rating once and copy vote data to all mirrors
            $source = $this->get_original_rating($rating_id);
            foreach ($mirrors as $mirror) {
                $mirror->source_id = $source ? (int) $source->id : (int) $rating_id;
                if ($source) {
                    $mirror->total_votes  = $source->total_votes;
                    $mirror->total_rating = $source->total_rating;
                    $mirror->display_only = $source->display_only;
                }
                self::attach_averages($mirror);
            }
        }

        return $mirrors;
    }

    /**
     * Get multiple ratings by IDs in a single query
     *
     * This is more efficient than calling get_rating() multiple times
     * as it executes a single SQL query with WHERE IN clause.
     *
     * @param array $ids Array of rating IDs
     * @return array Array of rating objects indexed by ID
     */
    public function get_ratings_by_ids(array $ids): array {
        if (empty($ids)) {
            return array();
        }

        $ids = array_map('intval', $ids);
        $ids = array_filter($ids); // Remove zeros/invalid
        
        if (empty($ids)) {
            return array();
        }

        $ids_placeholder = implode(',', array_fill(0, count($ids), '%d'));
        $fields = self::RATING_FIELDS;

        $ratings = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT {$fields} FROM {$this->ratings_table} WHERE id IN ($ids_placeholder)",
            ...$ids
        ));

        // Index by ID and calculate averages
        $result = array();
        foreach ($ratings as $rating) {
            // Default source_id to self
            $rating->source_id = $rating->id;
            
            // Calculate average
            self::attach_averages($rating);
            
            $result[$rating->id] = $rating;
        }

        // Handle mirrors - collect mirror_of IDs that need resolution
        $mirror_ids = array();
        foreach ($result as $rating) {
            if (!empty($rating->mirror_of) && !isset($result[$rating->mirror_of])) {
                $mirror_ids[] = $rating->mirror_of;
            }
        }

        // Fetch original ratings for mirrors if not already in result
        if (!empty($mirror_ids)) {
            $mirror_ids = array_unique($mirror_ids);
            $mirror_placeholder = implode(',', array_fill(0, count($mirror_ids), '%d'));
            $originals = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT {$fields} FROM {$this->ratings_table} WHERE id IN ($mirror_placeholder)",
                ...$mirror_ids
            ));

            $originals_map = array();
            foreach ($originals as $orig) {
                self::attach_averages($orig);
                $originals_map[$orig->id] = $orig;
            }

            // Apply mirror data — mirror uses its own scale for display_average
            foreach ($result as $id => $rating) {
                if (!empty($rating->mirror_of) && isset($originals_map[$rating->mirror_of])) {
                    $original = $originals_map[$rating->mirror_of];
                    $result[$id]->total_votes = $original->total_votes;
                    $result[$id]->total_rating = $original->total_rating;
                    $result[$id]->display_only = $original->display_only;
                    $result[$id]->average = $original->average;
                    $result[$id]->display_average = self::denormalize_average((float) $original->average, (int) $result[$id]->scale);
                    $result[$id]->source_id = $original->id;
                }
            }
        }

        return $result;
    }

    /**
     * Get contextual stats for a rating scoped to a specific post/entity
     *
     * Computes total_votes, total_rating, and average from the votes table
     * filtered by context_id and context_type.
     *
     * @param int    $rating_id    Rating ID.
     * @param int    $context_id   Post/entity ID.
     * @param string $context_type Context type ('post', 'product', etc.).
     * @param int    $scale        Display scale for denormalization.
     * @return object Object with total_votes, total_rating, average, display_average properties.
     */
    public function get_contextual_stats(int $rating_id, int $context_id, string $context_type, int $scale = self::RATING_SCALE_DEFAULT): object {
        $row = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT COUNT(*) as total_votes, COALESCE(SUM(rating_value), 0) as total_rating
             FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id = %d AND context_type = %s",
            $rating_id,
            $context_id,
            $context_type
        ));

        $stats = new \stdClass();
        $stats->total_votes = $row ? (int) $row->total_votes : 0;
        $stats->total_rating = $row ? (float) $row->total_rating : 0.0;
        self::attach_averages($stats, $scale);

        return $stats;
    }

    /**
     * Get contextual stats for multiple ratings in a single query
     *
     * @param array  $rating_ids   Array of rating IDs.
     * @param int    $context_id   Post/entity ID.
     * @param string $context_type Context type ('post', 'product', etc.).
     * @param array  $scales       Associative array of rating_id => display scale. Defaults to RATING_SCALE_DEFAULT for missing entries.
     * @return array Associative array keyed by rating_id => stats object.
     */
    public function get_contextual_stats_batch(array $rating_ids, int $context_id, string $context_type, array $scales = array()): array {
        if (empty($rating_ids)) {
            return array();
        }

        $rating_ids = array_map('intval', array_filter($rating_ids));
        if (empty($rating_ids)) {
            return array();
        }

        $ids_placeholder = implode(',', array_fill(0, count($rating_ids), '%d'));

        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT rating_id, COUNT(*) as total_votes, COALESCE(SUM(rating_value), 0) as total_rating
             FROM {$this->votes_table}
             WHERE rating_id IN ($ids_placeholder) AND context_id = %d AND context_type = %s
             GROUP BY rating_id",
            ...array_merge($rating_ids, [$context_id, $context_type])
        ));

        $result = array();
        foreach ($rows as $row) {
            $stats = new \stdClass();
            $stats->total_votes = (int) $row->total_votes;
            $stats->total_rating = (float) $row->total_rating;
            $rid = (int) $row->rating_id;
            self::attach_averages($stats, $scales[$rid] ?? self::RATING_SCALE_DEFAULT);
            $result[$rid] = $stats;
        }

        return $result;
    }

    /**
     * Count distinct contexts (posts/entities) per rating
     *
     * Returns an associative array of rating_id => context_count for every
     * rating that has at least one contextual vote.
     *
     * @return array<int, int> [rating_id => count_of_distinct_contexts]
     * @since 1.15.0
     */
    public function get_context_usage_counts(): array {
        $rows = $this->wpdb->get_results(
            "SELECT rating_id, COUNT(DISTINCT CONCAT(context_id, ':', context_type)) as context_count
             FROM {$this->votes_table}
             WHERE context_id IS NOT NULL
             GROUP BY rating_id"
        );

        $result = array();
        foreach ($rows as $row) {
            $result[(int) $row->rating_id] = (int) $row->context_count;
        }

        return $result;
    }

    /**
     * Check which ratings have global (non-contextual) votes
     *
     * @return array<int, int> [rating_id => global_vote_count]
     * @since 1.15.0
     */
    public function get_global_vote_counts(): array {
        $rows = $this->wpdb->get_results(
            "SELECT rating_id, COUNT(*) as vote_count
             FROM {$this->votes_table}
             WHERE context_id IS NULL
             GROUP BY rating_id"
        );

        $result = array();
        foreach ($rows as $row) {
            $result[(int) $row->rating_id] = (int) $row->vote_count;
        }

        return $result;
    }

    /**
     * Get all ratings that have contextual votes for a specific context
     *
     * Returns rating objects enriched with per-context stats.
     *
     * @param int    $context_id   Post/entity ID.
     * @param string $context_type Context type ('post', 'page', 'product', etc.).
     * @return array Array of objects with rating info + contextual stats.
     * @since 1.15.0
     */
    public function get_ratings_for_context(int $context_id, string $context_type): array {
        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT r.id, r.name, r.rating_type, r.scale, r.mirror_of,
                    COUNT(v.id) as ctx_votes,
                    COALESCE(SUM(v.rating_value), 0) as ctx_total
             FROM {$this->votes_table} v
             JOIN {$this->ratings_table} r ON v.rating_id = r.id
             WHERE v.context_id = %d AND v.context_type = %s
             GROUP BY r.id
             ORDER BY ctx_votes DESC",
            $context_id,
            $context_type
        ));

        foreach ($rows as &$row) {
            $row->ctx_votes = (int) $row->ctx_votes;
            $row->ctx_total = (int) $row->ctx_total;
            $row->ctx_average = $row->ctx_votes > 0
                ? round($row->ctx_total / $row->ctx_votes, 1)
                : 0;
            $s = isset($row->scale) ? (int) $row->scale : self::RATING_SCALE_DEFAULT;
            $row->ctx_display_average = self::denormalize_average((float) $row->ctx_average, $s);
        }

        return $rows;
    }

    /**
     * Get child ratings of a parent rating
     *
     * Returns all ratings that have the specified parent_id.
     *
     * @param int $parent_id The parent rating ID
     * @return array Array of child rating objects
     * @since 1.9.0
     */
    public function get_child_ratings(int $parent_id): array {
        $parent_id = absint($parent_id);
        
        if (!$parent_id) {
            return array();
        }

        $fields = self::RATING_FIELDS;
        $ratings = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT {$fields} FROM {$this->ratings_table} 
             WHERE parent_id = %d 
             ORDER BY name ASC",
            $parent_id
        ));

        // Calculate averages and set source_id
        foreach ($ratings as &$rating) {
            $rating->source_id = $rating->id;
            self::attach_averages($rating);
        }

        return $ratings;
    }

    /**
     * Search ratings by name
     *
     * Used for AJAX autocomplete in the block editor dropdown.
     * Returns limited results for efficient searching.
     *
     * @param string $search_term Search term to match against rating names
     * @param int    $limit       Maximum number of results (default 20)
     * @param string $type        Filter type: 'all', 'parents', 'mirrorable' (default 'all')
     * @return array Array of rating objects matching the search
     */
    public function search_ratings(string $search_term, int $limit = 20, string $type = 'all'): array {
        $search_term = sanitize_text_field($search_term);
        $limit = absint($limit);
        
        if ($limit < 1) {
            $limit = 20;
        }
        if ($limit > 100) {
            $limit = 100; // Cap at 100 for performance
        }

        $fields = self::RATING_FIELDS;
        $where_clause = '';

        // Add type filter
        switch ($type) {
            case 'parents':
                $where_clause = 'WHERE parent_id IS NULL AND mirror_of IS NULL';
                break;
            case 'parents_and_mirrors':
                // Parents (no parent, no mirror) + mirrors whose source is a parent
                $where_clause = "WHERE (parent_id IS NULL AND mirror_of IS NULL) OR (mirror_of IS NOT NULL AND mirror_of IN (SELECT id FROM {$this->ratings_table} WHERE parent_id IS NULL AND mirror_of IS NULL))";
                break;
            case 'mirrorable':
                $where_clause = 'WHERE mirror_of IS NULL';
                break;
            default:
                $where_clause = 'WHERE 1=1';
        }

        // Add search condition if search term provided
        if (!empty($search_term)) {
            $search_like = '%' . $this->wpdb->esc_like($search_term) . '%';
            $ratings = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT {$fields} FROM {$this->ratings_table} 
                 {$where_clause} AND name LIKE %s 
                 ORDER BY name ASC 
                 LIMIT %d",
                $search_like,
                $limit
            ));
        } else {
            // No search term - return first N results
            $ratings = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT {$fields} FROM {$this->ratings_table} 
                 {$where_clause}
                 ORDER BY name ASC 
                 LIMIT %d",
                $limit
            ));
        }

        // Calculate averages and resolve mirrors
        $mirror_source_ids = array();
        foreach ($ratings as &$rating) {
            $rating->source_id = $rating->id;
            if (!empty($rating->mirror_of)) {
                $mirror_source_ids[$rating->mirror_of] = true;
            }
        }
        unset($rating);

        // Batch-fetch source ratings for any mirrors in the results
        if (!empty($mirror_source_ids)) {
            $source_ids = array_keys($mirror_source_ids);
            $placeholders = implode(',', array_fill(0, count($source_ids), '%d'));
            $source_ratings = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT {$fields} FROM {$this->ratings_table} WHERE id IN ($placeholders)",
                ...$source_ids
            ));
            $source_map = array();
            foreach ($source_ratings as $src) {
                $source_map[(int) $src->id] = $src;
            }
            foreach ($ratings as &$rating) {
                if (!empty($rating->mirror_of) && isset($source_map[(int) $rating->mirror_of])) {
                    $original = $source_map[(int) $rating->mirror_of];
                    $rating->total_votes = $original->total_votes;
                    $rating->total_rating = $original->total_rating;
                    $rating->display_only = $original->display_only;
                    $rating->source_id = $original->id;
                }
            }
            unset($rating);
        }

        foreach ($ratings as &$rating) {
            self::attach_averages($rating);
        }

        return $ratings;
    }

    /**
     * Delete multiple ratings and their associated votes
     *
     * @param array $rating_ids Array of rating IDs
     * @return int Number of deleted ratings
     * @throws Shuriken_Database_Exception If delete fails or transaction fails
     * @throws Shuriken_Validation_Exception If no rating IDs provided
     */
    public function delete_ratings(array $rating_ids): int {
        if (empty($rating_ids)) {
            throw Shuriken_Validation_Exception::required_field('rating_ids');
        }

        $ids = array_map('intval', $rating_ids);
        $ids_placeholder = implode(',', array_fill(0, count($ids), '%d'));

        // Start transaction
        $this->wpdb->query('START TRANSACTION');

        try {
            // Delete associated votes first
            $this->wpdb->query($this->wpdb->prepare(
                "DELETE FROM {$this->votes_table} WHERE rating_id IN ($ids_placeholder)",
                ...$ids
            ));

            // Delete ratings
            $deleted = $this->wpdb->query($this->wpdb->prepare(
                "DELETE FROM {$this->ratings_table} WHERE id IN ($ids_placeholder)",
                ...$ids
            ));

            if ($deleted === false) {
                $this->wpdb->query('ROLLBACK');
                throw Shuriken_Database_Exception::delete_failed('ratings', implode(',', $ids));
            }

            $this->wpdb->query('COMMIT');
            return $deleted;

        } catch (Shuriken_Database_Exception $e) {
            // Re-throw our own exceptions
            throw $e;
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
            throw Shuriken_Database_Exception::transaction_failed();
        }
    }

    // =========================================================================
    // Vote Operations
    // =========================================================================

    /**
     * Get a user's vote for a specific rating
     *
     * @param int $rating_id Rating ID
     * @param int $user_id User ID (0 for guests)
     * @param string|null $user_ip IP address for guest votes
     * @param int|null $context_id Optional post/entity ID for contextual votes
     * @param string|null $context_type Optional context type ('post', 'product', etc.)
     * @return object|null Vote object or null if not found
     */
    public function get_user_vote(int $rating_id, int $user_id, ?string $user_ip = null, ?int $context_id = null, ?string $context_type = null): ?object {
        if ($context_id !== null && $context_type !== null) {
            if ($user_id > 0) {
                return $this->wpdb->get_row($this->wpdb->prepare(
                    "SELECT * FROM {$this->votes_table} 
                     WHERE rating_id = %d AND user_id = %d AND context_id = %d AND context_type = %s",
                    $rating_id,
                    $user_id,
                    $context_id,
                    $context_type
                ));
            } else {
                return $this->wpdb->get_row($this->wpdb->prepare(
                    "SELECT * FROM {$this->votes_table} 
                     WHERE rating_id = %d AND user_ip = %s AND user_id = 0 AND context_id = %d AND context_type = %s",
                    $rating_id,
                    $user_ip,
                    $context_id,
                    $context_type
                ));
            }
        }

        if ($user_id > 0) {
            return $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT * FROM {$this->votes_table} 
                 WHERE rating_id = %d AND user_id = %d AND context_id IS NULL",
                $rating_id,
                $user_id
            ));
        } else {
            return $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT * FROM {$this->votes_table} 
                 WHERE rating_id = %d AND user_ip = %s AND user_id = 0 AND context_id IS NULL",
                $rating_id,
                $user_ip
            ));
        }
    }

    /**
     * Create a new vote
     *
     * @param int $rating_id Rating ID
     * @param int $rating_value Rating value (1-5)
     * @param int $user_id User ID (0 for guests)
     * @param string|null $user_ip IP address for guest votes
     * @param int|null $context_id Optional post/entity ID for contextual votes
     * @param string|null $context_type Optional context type ('post', 'product', etc.)
     * @return bool True on success
     * @throws Shuriken_Database_Exception If insert fails or transaction fails
     * @throws Shuriken_Validation_Exception If rating_value is invalid
     */
    public function create_vote(int $rating_id, float|int $rating_value, int $user_id = 0, ?string $user_ip = null, ?int $context_id = null, ?string $context_type = null): bool {
        // Validate rating value against normalized scale (1-5 for stars/numeric, 0/1 for binary types)
        if ($rating_value < 0 || $rating_value > self::RATING_SCALE_DEFAULT) {
            throw Shuriken_Validation_Exception::out_of_range('rating_value', $rating_value, 0, self::RATING_SCALE_DEFAULT);
        }

        $this->wpdb->query('START TRANSACTION');

        try {
            $current_time = current_time('mysql');
            $insert_data = array(
                'rating_id' => $rating_id,
                'user_id' => $user_id,
                'rating_value' => $rating_value,
                'date_created' => $current_time,
                'date_modified' => $current_time,
            );
            $format = array('%d', '%d', '%f', '%s', '%s');

            if ($user_id === 0 && $user_ip) {
                $insert_data['user_ip'] = $user_ip;
                $format[] = '%s';
            }

            if ($context_id !== null && $context_type !== null) {
                $insert_data['context_id'] = $context_id;
                $insert_data['context_type'] = $context_type;
                $format[] = '%d';
                $format[] = '%s';
            }

            $result = $this->wpdb->insert($this->votes_table, $insert_data, $format);

            if ($result === false) {
                $this->wpdb->query('ROLLBACK');
                throw Shuriken_Database_Exception::insert_failed('votes');
            }

            // Update rating totals
            $update_result = $this->wpdb->query($this->wpdb->prepare(
                "UPDATE {$this->ratings_table} 
                 SET total_votes = total_votes + 1, total_rating = total_rating + %f 
                 WHERE id = %d",
                $rating_value,
                $rating_id
            ));

            if ($update_result === false) {
                $this->wpdb->query('ROLLBACK');
                throw Shuriken_Database_Exception::update_failed('ratings', $rating_id);
            }

            $this->wpdb->query('COMMIT');
            return true;

        } catch (Shuriken_Database_Exception $e) {
            // Re-throw our own exceptions
            throw $e;
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
            throw Shuriken_Database_Exception::transaction_failed();
        }
    }

    /**
     * Update an existing vote
     *
     * @param int $vote_id Vote ID
     * @param int $rating_id Rating ID
     * @param int $old_value Previous rating value
     * @param int $new_value New rating value
     * @return bool True on success
     * @throws Shuriken_Database_Exception If update fails or transaction fails
     * @throws Shuriken_Validation_Exception If new_value is invalid
     */
    public function update_vote(int $vote_id, int $rating_id, float|int $old_value, float|int $new_value): bool {
        // Validate new rating value against normalized scale
        if ($new_value < 0 || $new_value > 5) {
            throw Shuriken_Validation_Exception::out_of_range('new_value', $new_value, 0, 5);
        }

        $this->wpdb->query('START TRANSACTION');

        try {
            // Update the vote - always update date_modified for rate limiting
            $result = $this->wpdb->update(
                $this->votes_table,
                array(
                    'rating_value' => $new_value,
                    'date_modified' => current_time('mysql'),
                ),
                array('id' => $vote_id),
                array('%f', '%s'),
                array('%d')
            );

            if ($result === false) {
                $this->wpdb->query('ROLLBACK');
                throw Shuriken_Database_Exception::update_failed('votes', $vote_id);
            }

            // Update rating total (subtract old, add new)
            $update_result = $this->wpdb->query($this->wpdb->prepare(
                "UPDATE {$this->ratings_table} 
                 SET total_rating = total_rating - %f + %f 
                 WHERE id = %d",
                $old_value,
                $new_value,
                $rating_id
            ));

            if ($update_result === false) {
                $this->wpdb->query('ROLLBACK');
                throw Shuriken_Database_Exception::update_failed('ratings', $rating_id);
            }

            $this->wpdb->query('COMMIT');
            return true;

        } catch (Shuriken_Database_Exception $e) {
            // Re-throw our own exceptions
            throw $e;
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
            throw Shuriken_Database_Exception::transaction_failed();
        }
    }

    // =========================================================================
    // Rate Limiting
    // =========================================================================

    /**
     * Get the timestamp of the last vote for a rating by a user/guest
     *
     * @param int         $rating_id    Rating ID.
     * @param int         $user_id      User ID (0 for guests).
     * @param string|null $user_ip      User IP address (for guests).
     * @param int|null    $context_id   Optional post/entity ID for contextual votes.
     * @param string|null $context_type Optional context type ('post', 'product', etc.).
     * @return string|null Datetime string or null if no vote found.
     */
    public function get_last_vote_time(int $rating_id, int $user_id, ?string $user_ip = null, ?int $context_id = null, ?string $context_type = null): ?string {
        $context_clause = ($context_id !== null && $context_type !== null)
            ? $this->wpdb->prepare(' AND context_id = %d AND context_type = %s', $context_id, $context_type)
            : ' AND context_id IS NULL';

        if ($user_id > 0) {
            return $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT date_modified FROM {$this->votes_table} 
                 WHERE rating_id = %d AND user_id = %d{$context_clause}
                 ORDER BY date_modified DESC LIMIT 1",
                $rating_id,
                $user_id
            ));
        } else {
            return $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT date_modified FROM {$this->votes_table} 
                 WHERE rating_id = %d AND user_ip = %s AND user_id = 0{$context_clause}
                 ORDER BY date_modified DESC LIMIT 1",
                $rating_id,
                $user_ip
            ));
        }
    }

    /**
     * Count votes by a user/guest since a given datetime
     *
     * @param int         $user_id User ID (0 for guests).
     * @param string|null $user_ip User IP address (for guests).
     * @param string      $since   Datetime string (Y-m-d H:i:s format).
     * @return int Number of votes since the given time.
     */
    public function count_votes_since(int $user_id, ?string $user_ip, string $since): int {
        if ($user_id > 0) {
            return (int) $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->votes_table} 
                 WHERE user_id = %d AND date_modified >= %s",
                $user_id,
                $since
            ));
        } else {
            return (int) $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->votes_table} 
                 WHERE user_ip = %s AND user_id = 0 AND date_modified >= %s",
                $user_ip,
                $since
            ));
        }
    }

    /**
     * Get the oldest vote datetime within a time window
     *
     * Used to calculate when rate limits will reset.
     *
     * @param int         $user_id        User ID (0 for guests).
     * @param string|null $user_ip        User IP address (for guests).
     * @param int         $window_seconds Time window in seconds.
     * @return string|null Datetime string or null if no votes in window.
     */
    public function get_oldest_vote_in_window(int $user_id, ?string $user_ip, int $window_seconds): ?string {
        $since = gmdate('Y-m-d H:i:s', strtotime(current_time('mysql')) - $window_seconds);

        if ($user_id > 0) {
            return $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT date_modified FROM {$this->votes_table} 
                 WHERE user_id = %d AND date_modified >= %s
                 ORDER BY date_modified ASC LIMIT 1",
                $user_id,
                $since
            ));
        } else {
            return $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT date_modified FROM {$this->votes_table} 
                 WHERE user_ip = %s AND user_id = 0 AND date_modified >= %s
                 ORDER BY date_modified ASC LIMIT 1",
                $user_ip,
                $since
            ));
        }
    }

    // =========================================================================
    // Table Management
    // =========================================================================

    /**
     * Create the plugin database tables
     *
     * @return bool True on success, false on failure
     */
    public function create_tables(): bool {
        $charset_collate = $this->wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Create ratings table
        $sql_ratings = "CREATE TABLE IF NOT EXISTS {$this->ratings_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            total_votes int DEFAULT 0,
            total_rating DECIMAL(10,2) DEFAULT 0,
            parent_id mediumint(9) DEFAULT NULL,
            effect_type varchar(10) DEFAULT 'positive',
            display_only tinyint(1) DEFAULT 0,
            mirror_of mediumint(9) DEFAULT NULL,
            rating_type varchar(20) NOT NULL DEFAULT 'stars',
            scale tinyint unsigned NOT NULL DEFAULT 5,
            date_created datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY parent_id (parent_id),
            KEY mirror_of (mirror_of)
        ) $charset_collate;";

        dbDelta($sql_ratings);

        // Create votes table
        $sql_votes = "CREATE TABLE IF NOT EXISTS {$this->votes_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            rating_id mediumint(9) NOT NULL,
            user_id bigint(20) DEFAULT 0,
            user_ip varchar(45) DEFAULT NULL,
            rating_value DECIMAL(5,2) NOT NULL,
            context_id bigint(20) unsigned DEFAULT NULL,
            context_type varchar(20) DEFAULT NULL,
            date_created datetime DEFAULT CURRENT_TIMESTAMP,
            date_modified datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_vote (rating_id, user_id, user_ip, context_id, context_type),
            KEY context_lookup (rating_id, context_id, context_type)
        ) $charset_collate;";

        dbDelta($sql_votes);

        // Verify tables were created
        $ratings_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$this->ratings_table}'") === $this->ratings_table;
        $votes_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$this->votes_table}'") === $this->votes_table;

        return $ratings_exists && $votes_exists;
    }

    /**
     * Check if tables exist
     *
     * @return bool True if both tables exist
     */
    public function tables_exist(): bool {
        $ratings_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$this->ratings_table}'") === $this->ratings_table;
        $votes_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$this->votes_table}'") === $this->votes_table;

        return $ratings_exists && $votes_exists;
    }

    /**
     * Run database migrations
     *
     * @param string $current_version Current DB version
     * @return bool True on success
     */
    public function run_migrations(string $current_version): bool {
        if (!$this->tables_exist()) {
            return false;
        }

        // v1.5.0: user_ip, parent structure (parent_id/effect_type/display_only),
        //         mirror_of, rating_type, and scale columns.
        // Inner SHOW COLUMNS guards are kept so partially-applied upgrades are safe.
        if (version_compare($current_version, '1.5.0', '<')) {
            $column_exists = $this->wpdb->get_results(
                "SHOW COLUMNS FROM {$this->votes_table} LIKE 'user_ip'"
            );

            if (empty($column_exists)) {
                $this->wpdb->query("ALTER TABLE {$this->votes_table} ADD COLUMN user_ip varchar(45) DEFAULT NULL AFTER user_id");

                $index_exists = $this->wpdb->get_results(
                    "SHOW INDEX FROM {$this->votes_table} WHERE Key_name = 'unique_vote'"
                );

                if (!empty($index_exists)) {
                    $this->wpdb->query("ALTER TABLE {$this->votes_table} DROP INDEX unique_vote");
                }

                $this->wpdb->query("ALTER TABLE {$this->votes_table} ADD UNIQUE KEY unique_vote (rating_id, user_id, user_ip)");
                $this->wpdb->query("ALTER TABLE {$this->votes_table} MODIFY user_id bigint(20) DEFAULT 0");
            }

            $parent_id_exists = $this->wpdb->get_results(
                "SHOW COLUMNS FROM {$this->ratings_table} LIKE 'parent_id'"
            );

            if (empty($parent_id_exists)) {
                $this->wpdb->query("ALTER TABLE {$this->ratings_table} ADD COLUMN parent_id mediumint(9) DEFAULT NULL AFTER total_rating");
                $this->wpdb->query("ALTER TABLE {$this->ratings_table} ADD COLUMN effect_type varchar(10) DEFAULT 'positive' AFTER parent_id");
                $this->wpdb->query("ALTER TABLE {$this->ratings_table} ADD COLUMN display_only tinyint(1) DEFAULT 0 AFTER effect_type");
                $this->wpdb->query("ALTER TABLE {$this->ratings_table} ADD KEY parent_id (parent_id)");
            }

            $mirror_of_exists = $this->wpdb->get_results(
                "SHOW COLUMNS FROM {$this->ratings_table} LIKE 'mirror_of'"
            );

            if (empty($mirror_of_exists)) {
                $this->wpdb->query("ALTER TABLE {$this->ratings_table} ADD COLUMN mirror_of mediumint(9) DEFAULT NULL AFTER display_only");
                $this->wpdb->query("ALTER TABLE {$this->ratings_table} ADD KEY mirror_of (mirror_of)");
            }

            $rating_type_exists = $this->wpdb->get_results(
                "SHOW COLUMNS FROM {$this->ratings_table} LIKE 'rating_type'"
            );

            if (empty($rating_type_exists)) {
                $this->wpdb->query("ALTER TABLE {$this->ratings_table} ADD COLUMN rating_type varchar(20) NOT NULL DEFAULT 'stars' AFTER mirror_of");
                $this->wpdb->query("ALTER TABLE {$this->ratings_table} ADD COLUMN scale tinyint unsigned NOT NULL DEFAULT 5 AFTER rating_type");
            }
        }

        // v1.6.0: Contextual voting columns (context_id, context_type)
        if (version_compare($current_version, '1.6.0', '<')) {
            $context_id_exists = $this->wpdb->get_results(
                "SHOW COLUMNS FROM {$this->votes_table} LIKE 'context_id'"
            );

            if (empty($context_id_exists)) {
                $this->wpdb->query("ALTER TABLE {$this->votes_table} ADD COLUMN context_id bigint(20) unsigned DEFAULT NULL AFTER rating_value");
                $this->wpdb->query("ALTER TABLE {$this->votes_table} ADD COLUMN context_type varchar(20) DEFAULT NULL AFTER context_id");

                $index_exists = $this->wpdb->get_results(
                    "SHOW INDEX FROM {$this->votes_table} WHERE Key_name = 'unique_vote'"
                );
                if (!empty($index_exists)) {
                    $this->wpdb->query("ALTER TABLE {$this->votes_table} DROP INDEX unique_vote");
                }
                $this->wpdb->query("ALTER TABLE {$this->votes_table} ADD UNIQUE KEY unique_vote (rating_id, user_id, user_ip, context_id, context_type)");
                $this->wpdb->query("ALTER TABLE {$this->votes_table} ADD KEY context_lookup (rating_id, context_id, context_type)");
            }
        }

        // v1.7.0: Migrate rating_value and total_rating from INT to DECIMAL for float precision
        if (version_compare($current_version, '1.7.0', '<')) {
            $rating_value_col = $this->wpdb->get_results(
                "SHOW COLUMNS FROM {$this->votes_table} LIKE 'rating_value'"
            );

            if (!empty($rating_value_col) && stripos($rating_value_col[0]->Type, 'int') !== false) {
                $this->wpdb->query("ALTER TABLE {$this->votes_table} MODIFY rating_value DECIMAL(5,2) NOT NULL");
                $this->wpdb->query("ALTER TABLE {$this->ratings_table} MODIFY total_rating DECIMAL(10,2) DEFAULT 0");
            }
        }

        return true;
    }

    // =========================================================================
    // Export Helpers
    // =========================================================================

    /**
     * Get all ratings with calculated averages for export
     *
     * @return array Array of rating objects
     */
    public function get_ratings_for_export(): array {
        return $this->wpdb->get_results(
            "SELECT id, name, total_votes, total_rating, parent_id, effect_type, display_only, mirror_of, date_created,
                    ROUND(total_rating / NULLIF(total_votes, 0), 2) as average_rating
             FROM {$this->ratings_table}
             ORDER BY id ASC"
        );
    }

    /**
     * Get all votes for a rating for export
     *
     * @param int $rating_id Rating ID
     * @return array Array of vote objects with user info
     */
    public function get_votes_for_export(int $rating_id): array {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT v.id, v.rating_value, v.user_id, v.user_ip, v.date_created, 
                    u.display_name, u.user_email
             FROM {$this->votes_table} v
             LEFT JOIN {$this->wpdb->users} u ON v.user_id = u.ID
             WHERE v.rating_id = %d
             ORDER BY v.date_created DESC",
            $rating_id
        ));
    }
}
