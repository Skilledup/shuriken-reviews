<?php
/**
 * Shuriken Rating Repository
 *
 * Handles rating CRUD, search, pagination, hierarchy, mirrors, contextual stats, and export.
 *
 * @package Shuriken_Reviews
 * @since 1.15.5
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_Rating_Repository
 *
 * Manages all rating-related database operations.
 *
 * @since 1.15.5
 */
class Shuriken_Rating_Repository {

    /**
     * @var string Standard rating fields for SELECT queries
     */
    private const RATING_FIELDS = 'id, name, total_votes, total_rating, parent_id, effect_type, display_only, mirror_of, rating_type, scale, label_description, date_created';

    /**
     * Constructor
     *
     * @param \wpdb  $wpdb          WordPress database instance.
     * @param string $ratings_table  Prefixed ratings table name.
     * @param string $votes_table    Prefixed votes table name.
     */
    public function __construct(
        private readonly \wpdb $wpdb,
        private readonly string $ratings_table,
        private readonly string $votes_table,
    ) {}

    /**
     * Compute normalized average and attach display_average to a rating/stats object.
     *
     * Every method that computes `->average` MUST call this instead of doing inline math.
     *
     * @param object   $obj   The object to attach averages to.
     * @param int|null $scale The display scale. If null, reads from $obj->scale.
     */
    private static function attach_averages(object $obj, ?int $scale = null): void {
        $s = $scale ?? (isset($obj->scale) ? (int) $obj->scale : Shuriken_Database::RATING_SCALE_DEFAULT);
        $obj->average = (isset($obj->total_votes) && $obj->total_votes > 0)
            ? round((float) $obj->total_rating / (int) $obj->total_votes, 2)
            : 0;
        $obj->display_average = Shuriken_Database::denormalize_average((float) $obj->average, $s);
    }

    // =========================================================================
    // Rating CRUD
    // =========================================================================

    /**
     * Get a single rating by ID
     *
     * @param int $rating_id Rating ID
     * @return object|null Rating object or null if not found
     */
    public function get_rating(int $rating_id): ?object {
        $fields = self::RATING_FIELDS;
        $rating = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT {$fields} FROM {$this->ratings_table} WHERE id = %d",
            $rating_id
        ));

        if ($rating) {
            $rating->source_id = $rating->id;

            if (!empty($rating->mirror_of)) {
                $original = $this->get_original_rating($rating->mirror_of);
                if ($original) {
                    $rating->total_votes = $original->total_votes;
                    $rating->total_rating = $original->total_rating;
                    $rating->display_only = $original->display_only;
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
     * @param int    $per_page Items per page
     * @param int    $page     Current page (1-indexed)
     * @param string $search   Optional search term
     * @param string $orderby  Column to order by
     * @param string $order    ASC or DESC
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
     * @param string   $name         Rating name
     * @param int|null $parent_id    Parent rating ID (optional)
     * @param string   $effect_type  Effect type on parent: 'positive' or 'negative'
     * @param bool        $display_only      Whether the rating is display-only
     * @param int|null    $mirror_of         Original rating ID to mirror (optional)
     * @param string      $rating_type       Rating type
     * @param int         $scale             Display scale
     * @param string|null $label_description Optional description displayed beneath the rating title
     * @return int The new rating ID
     * @throws Shuriken_Database_Exception If insert fails
     * @throws Shuriken_Validation_Exception If name is empty
     */
    public function create_rating(string $name, ?int $parent_id = null, string $effect_type = 'positive', bool $display_only = false, ?int $mirror_of = null, string $rating_type = 'stars', int $scale = Shuriken_Database::RATING_SCALE_DEFAULT, ?string $label_description = null): int {
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
                $scale = isset($source->scale) ? intval($source->scale) : Shuriken_Database::RATING_SCALE_DEFAULT;
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

        if ($label_description !== null && $label_description !== '') {
            $insert_data['label_description'] = sanitize_text_field($label_description);
            $format[] = '%s';
        }

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
     * @param int   $rating_id Rating ID
     * @param array $data      Data to update
     * @return bool True on success
     * @throws Shuriken_Database_Exception If update fails
     * @throws Shuriken_Validation_Exception If no valid data provided
     */
    public function update_rating(int $rating_id, array $data): bool {
        // Block type/scale changes if rating has votes or is a mirror
        if (isset($data['rating_type']) || isset($data['scale'])) {
            $existing = $this->get_rating($rating_id);
            if ($existing) {
                if (!empty($existing->mirror_of)) {
                    unset($data['rating_type'], $data['scale']);
                } elseif ($existing->total_votes > 0) {
                    $type_changed = isset($data['rating_type']) && $data['rating_type'] !== ($existing->rating_type ?? 'stars');
                    $scale_changed = isset($data['scale']) && intval($data['scale']) !== intval($existing->scale ?? Shuriken_Database::RATING_SCALE_DEFAULT);
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

        $allowed_fields = array('name', 'total_votes', 'total_rating', 'parent_id', 'effect_type', 'display_only', 'mirror_of', 'rating_type', 'scale', 'label_description');
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
                    $update_data[$key] = max(1, min(Shuriken_Database::NUMERIC_SCALE_MAX, intval($value)));
                    $format[] = '%d';
                } elseif ($key === 'parent_id' || $key === 'mirror_of') {
                    $update_data[$key] = $value === null || $value === '' || $value === 0 ? null : intval($value);
                    $format[] = $update_data[$key] === null ? null : '%d';
                } elseif ($key === 'display_only') {
                    $update_data[$key] = $value ? 1 : 0;
                    $format[] = '%d';
                } elseif ($key === 'label_description') {
                    $update_data[$key] = ($value === null || $value === '') ? null : sanitize_text_field($value);
                    $format[] = $update_data[$key] === null ? null : '%s';
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

        $this->wpdb->query('START TRANSACTION');

        try {
            // Update any sub-ratings to have no parent
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
            throw $e;
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
            throw Shuriken_Database_Exception::transaction_failed();
        }
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
            throw $e;
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
            throw Shuriken_Database_Exception::transaction_failed();
        }
    }

    // =========================================================================
    // Hierarchy & Mirrors
    // =========================================================================

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
            $rating->source_id = $rating->id;
            self::attach_averages($rating);
        }

        return $ratings;
    }

    /**
     * Get child ratings of a parent rating
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

        foreach ($ratings as &$rating) {
            $rating->source_id = $rating->id;
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
                    $is_binary = (Shuriken_Rating_Type::tryFrom($sub->rating_type) ?? Shuriken_Rating_Type::Stars)->isBinary();
                    $inv_const = $is_binary ? (int) $sub->scale : (Shuriken_Database::RATING_SCALE_DEFAULT + 1);
                    $inverted_rating = ($sub->total_votes * $inv_const) - $sub->total_rating;
                    $total_rating += $inverted_rating;
                } else {
                    $total_rating += $sub->total_rating;
                }
            }
        }

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
     * @param array $ids Array of rating IDs
     * @return array Array of rating objects indexed by ID
     */
    public function get_ratings_by_ids(array $ids): array {
        if (empty($ids)) {
            return array();
        }

        $ids = array_map('intval', $ids);
        $ids = array_filter($ids);

        if (empty($ids)) {
            return array();
        }

        $ids_placeholder = implode(',', array_fill(0, count($ids), '%d'));
        $fields = self::RATING_FIELDS;

        $ratings = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT {$fields} FROM {$this->ratings_table} WHERE id IN ($ids_placeholder)",
            ...$ids
        ));

        $result = array();
        foreach ($ratings as $rating) {
            $rating->source_id = $rating->id;
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

            foreach ($result as $id => $rating) {
                if (!empty($rating->mirror_of) && isset($originals_map[$rating->mirror_of])) {
                    $original = $originals_map[$rating->mirror_of];
                    $result[$id]->total_votes = $original->total_votes;
                    $result[$id]->total_rating = $original->total_rating;
                    $result[$id]->display_only = $original->display_only;
                    $result[$id]->average = $original->average;
                    $result[$id]->display_average = Shuriken_Database::denormalize_average((float) $original->average, (int) $result[$id]->scale);
                    $result[$id]->source_id = $original->id;
                }
            }
        }

        return $result;
    }

    /**
     * Search ratings by name
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
            $limit = 100;
        }

        $fields = self::RATING_FIELDS;
        $where_clause = '';

        switch ($type) {
            case 'parents':
                $where_clause = 'WHERE parent_id IS NULL AND mirror_of IS NULL';
                break;
            case 'parents_and_mirrors':
                $where_clause = "WHERE (parent_id IS NULL AND mirror_of IS NULL) OR (mirror_of IS NOT NULL AND mirror_of IN (SELECT id FROM {$this->ratings_table} WHERE parent_id IS NULL AND mirror_of IS NULL))";
                break;
            case 'mirrorable':
                $where_clause = 'WHERE mirror_of IS NULL';
                break;
            default:
                $where_clause = 'WHERE 1=1';
        }

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
            $ratings = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT {$fields} FROM {$this->ratings_table} 
                 {$where_clause}
                 ORDER BY name ASC 
                 LIMIT %d",
                $limit
            ));
        }

        // Resolve mirrors
        $mirror_source_ids = array();
        foreach ($ratings as &$rating) {
            $rating->source_id = $rating->id;
            if (!empty($rating->mirror_of)) {
                $mirror_source_ids[$rating->mirror_of] = true;
            }
        }
        unset($rating);

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

    // =========================================================================
    // Contextual Stats
    // =========================================================================

    /**
     * Get contextual stats for a rating scoped to a specific post/entity
     *
     * @param int    $rating_id    Rating ID.
     * @param int    $context_id   Post/entity ID.
     * @param string $context_type Context type ('post', 'product', etc.).
     * @param int    $scale        Display scale for denormalization.
     * @return object Object with total_votes, total_rating, average, display_average properties.
     */
    public function get_contextual_stats(int $rating_id, int $context_id, string $context_type, int $scale = Shuriken_Database::RATING_SCALE_DEFAULT): object {
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
     * @param array  $scales       Map of rating_id => display scale.
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
            self::attach_averages($stats, $scales[$rid] ?? Shuriken_Database::RATING_SCALE_DEFAULT);
            $result[$rid] = $stats;
        }

        return $result;
    }

    /**
     * Count distinct contexts (posts/entities) per rating
     *
     * @return array<int, int> [rating_id => count_of_distinct_contexts]
     * @since 1.15.5
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
     * @since 1.15.5
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
     * @param int    $context_id   Post/entity ID.
     * @param string $context_type Context type.
     * @return array Array of objects with rating info + contextual stats.
     * @since 1.15.5
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
            $s = isset($row->scale) ? (int) $row->scale : Shuriken_Database::RATING_SCALE_DEFAULT;
            $row->ctx_display_average = Shuriken_Database::denormalize_average((float) $row->ctx_average, $s);
        }

        return $rows;
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
