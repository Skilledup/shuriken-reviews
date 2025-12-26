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
     * @var wpdb WordPress database instance
     */
    private $wpdb;

    /**
     * @var string Ratings table name
     */
    private $ratings_table;

    /**
     * @var string Votes table name
     */
    private $votes_table;

    /**
     * @var Shuriken_Database Singleton instance
     */
    private static $instance = null;

    /**
     * @var string Standard rating fields for SELECT queries
     */
    private const RATING_FIELDS = 'id, name, total_votes, total_rating, parent_id, effect_type, display_only, mirror_of, date_created';

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
    public static function get_instance() {
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
    public function get_ratings_table() {
        return $this->ratings_table;
    }

    /**
     * Get the votes table name
     *
     * @return string
     */
    public function get_votes_table() {
        return $this->votes_table;
    }

    /**
     * Get the wpdb instance
     *
     * @return wpdb
     */
    public function get_wpdb() {
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
    public function get_rating($rating_id) {
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
            
            $rating->average = $rating->total_votes > 0 
                ? round($rating->total_rating / $rating->total_votes, 1) 
                : 0;
        }

        return $rating;
    }

    /**
     * Get original rating without mirror resolution (to avoid infinite loops)
     *
     * @param int $rating_id Rating ID
     * @return object|null Rating object or null if not found
     */
    private function get_original_rating($rating_id) {
        $fields = self::RATING_FIELDS;
        $rating = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT {$fields} FROM {$this->ratings_table} WHERE id = %d",
            $rating_id
        ));

        if ($rating) {
            $rating->average = $rating->total_votes > 0 
                ? round($rating->total_rating / $rating->total_votes, 1) 
                : 0;
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
    public function get_all_ratings($orderby = 'id', $order = 'DESC') {
        $allowed_orderby = array('id', 'name', 'total_votes', 'total_rating', 'date_created', 'parent_id', 'mirror_of');
        $orderby = in_array($orderby, $allowed_orderby, true) ? $orderby : 'id';
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        $fields = self::RATING_FIELDS;

        return $this->wpdb->get_results(
            "SELECT {$fields} FROM {$this->ratings_table} ORDER BY {$orderby} {$order}"
        );
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
    public function get_ratings_paginated($per_page = 20, $page = 1, $search = '', $orderby = 'id', $order = 'DESC') {
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
     * @return int|false The new rating ID or false on failure
     */
    public function create_rating($name, $parent_id = null, $effect_type = 'positive', $display_only = false, $mirror_of = null) {
        $insert_data = array(
            'name' => sanitize_text_field($name),
            'effect_type' => in_array($effect_type, array('positive', 'negative'), true) ? $effect_type : 'positive',
            'display_only' => $display_only ? 1 : 0
        );
        $format = array('%s', '%s', '%d');

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

        if ($result !== false) {
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

        return false;
    }

    /**
     * Update a rating
     *
     * @param int $rating_id Rating ID
     * @param array $data Data to update (name, total_votes, total_rating, parent_id, effect_type, display_only, mirror_of)
     * @return bool True on success, false on failure
     */
    public function update_rating($rating_id, $data) {
        $allowed_fields = array('name', 'total_votes', 'total_rating', 'parent_id', 'effect_type', 'display_only', 'mirror_of');
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
            return false;
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

        if ($result !== false) {
            /**
             * Fires after a rating is updated.
             *
             * @since 1.7.0
             * @param int   $rating_id   The rating ID.
             * @param array $update_data The updated data.
             */
            do_action('shuriken_rating_updated', $rating_id, $update_data);
        }

        return $result !== false;
    }

    /**
     * Delete a rating and its associated votes
     *
     * @param int $rating_id Rating ID
     * @return bool True on success, false on failure
     */
    public function delete_rating($rating_id) {
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
                return false;
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

        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
            return false;
        }
    }

    /**
     * Get all sub-ratings for a parent rating
     *
     * @param int $parent_id Parent rating ID
     * @return array Array of sub-rating objects
     */
    public function get_sub_ratings($parent_id) {
        $fields = self::RATING_FIELDS;
        $ratings = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT {$fields} FROM {$this->ratings_table} 
             WHERE parent_id = %d 
             ORDER BY name ASC",
            $parent_id
        ));

        foreach ($ratings as &$rating) {
            $rating->average = $rating->total_votes > 0 
                ? round($rating->total_rating / $rating->total_votes, 1) 
                : 0;
        }

        return $ratings;
    }

    /**
     * Get all parent ratings (ratings that can have sub-ratings)
     *
     * @param int|null $exclude_id Rating ID to exclude from results
     * @return array Array of rating objects
     */
    public function get_parent_ratings($exclude_id = null) {
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
     * @return bool True on success, false on failure
     */
    public function recalculate_parent_rating($parent_id) {
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
                    // For negative effect: invert the rating (6 - rating becomes the effective rating)
                    // e.g., a 5-star negative rating contributes 1 point, 1-star contributes 5 points
                    $inverted_rating = ($sub->total_votes * 6) - $sub->total_rating;
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
    public function get_mirrorable_ratings($exclude_id = null) {
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
    public function get_mirrors($rating_id) {
        $fields = self::RATING_FIELDS;
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT {$fields} FROM {$this->ratings_table} 
             WHERE mirror_of = %d 
             ORDER BY name ASC",
            $rating_id
        ));
    }

    /**
     * Delete multiple ratings and their associated votes
     *
     * @param array $rating_ids Array of rating IDs
     * @return int|false Number of deleted ratings or false on failure
     */
    public function delete_ratings($rating_ids) {
        if (empty($rating_ids)) {
            return false;
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
                return false;
            }

            $this->wpdb->query('COMMIT');
            return $deleted;

        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
            return false;
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
     * @return object|null Vote object or null if not found
     */
    public function get_user_vote($rating_id, $user_id, $user_ip = null) {
        if ($user_id > 0) {
            return $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT * FROM {$this->votes_table} 
                 WHERE rating_id = %d AND user_id = %d",
                $rating_id,
                $user_id
            ));
        } else {
            return $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT * FROM {$this->votes_table} 
                 WHERE rating_id = %d AND user_ip = %s AND user_id = 0",
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
     * @return bool True on success, false on failure
     */
    public function create_vote($rating_id, $rating_value, $user_id = 0, $user_ip = null) {
        $this->wpdb->query('START TRANSACTION');

        try {
            $insert_data = array(
                'rating_id' => $rating_id,
                'user_id' => $user_id,
                'rating_value' => $rating_value
            );
            $format = array('%d', '%d', '%d');

            if ($user_id === 0 && $user_ip) {
                $insert_data['user_ip'] = $user_ip;
                $format[] = '%s';
            }

            $result = $this->wpdb->insert($this->votes_table, $insert_data, $format);

            if ($result === false) {
                $this->wpdb->query('ROLLBACK');
                return false;
            }

            // Update rating totals
            $update_result = $this->wpdb->query($this->wpdb->prepare(
                "UPDATE {$this->ratings_table} 
                 SET total_votes = total_votes + 1, total_rating = total_rating + %d 
                 WHERE id = %d",
                $rating_value,
                $rating_id
            ));

            if ($update_result === false) {
                $this->wpdb->query('ROLLBACK');
                return false;
            }

            $this->wpdb->query('COMMIT');
            return true;

        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
            return false;
        }
    }

    /**
     * Update an existing vote
     *
     * @param int $vote_id Vote ID
     * @param int $rating_id Rating ID
     * @param int $old_value Previous rating value
     * @param int $new_value New rating value
     * @return bool True on success, false on failure
     */
    public function update_vote($vote_id, $rating_id, $old_value, $new_value) {
        $this->wpdb->query('START TRANSACTION');

        try {
            // Update the vote
            $result = $this->wpdb->update(
                $this->votes_table,
                array('rating_value' => $new_value),
                array('id' => $vote_id),
                array('%d'),
                array('%d')
            );

            if ($result === false) {
                $this->wpdb->query('ROLLBACK');
                return false;
            }

            // Update rating total (subtract old, add new)
            $update_result = $this->wpdb->query($this->wpdb->prepare(
                "UPDATE {$this->ratings_table} 
                 SET total_rating = total_rating - %d + %d 
                 WHERE id = %d",
                $old_value,
                $new_value,
                $rating_id
            ));

            if ($update_result === false) {
                $this->wpdb->query('ROLLBACK');
                return false;
            }

            $this->wpdb->query('COMMIT');
            return true;

        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
            return false;
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
    public function create_tables() {
        $charset_collate = $this->wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Create ratings table
        $sql_ratings = "CREATE TABLE IF NOT EXISTS {$this->ratings_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            total_votes int DEFAULT 0,
            total_rating int DEFAULT 0,
            parent_id mediumint(9) DEFAULT NULL,
            effect_type varchar(10) DEFAULT 'positive',
            display_only tinyint(1) DEFAULT 0,
            mirror_of mediumint(9) DEFAULT NULL,
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
            rating_value int NOT NULL,
            date_created datetime DEFAULT CURRENT_TIMESTAMP,
            date_modified datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_vote (rating_id, user_id, user_ip)
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
    public function tables_exist() {
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
    public function run_migrations($current_version) {
        if (!$this->tables_exist()) {
            return false;
        }

        // Check if user_ip column exists in votes table
        $column_exists = $this->wpdb->get_results(
            "SHOW COLUMNS FROM {$this->votes_table} LIKE 'user_ip'"
        );

        if (empty($column_exists)) {
            // Add user_ip column
            $this->wpdb->query("ALTER TABLE {$this->votes_table} ADD COLUMN user_ip varchar(45) DEFAULT NULL AFTER user_id");

            // Update unique index
            $index_exists = $this->wpdb->get_results(
                "SHOW INDEX FROM {$this->votes_table} WHERE Key_name = 'unique_vote'"
            );

            if (!empty($index_exists)) {
                $this->wpdb->query("ALTER TABLE {$this->votes_table} DROP INDEX unique_vote");
            }

            $this->wpdb->query("ALTER TABLE {$this->votes_table} ADD UNIQUE KEY unique_vote (rating_id, user_id, user_ip)");

            // Update user_id default
            $this->wpdb->query("ALTER TABLE {$this->votes_table} MODIFY user_id bigint(20) DEFAULT 0");
        }

        // Check if parent_id column exists in ratings table
        $parent_id_exists = $this->wpdb->get_results(
            "SHOW COLUMNS FROM {$this->ratings_table} LIKE 'parent_id'"
        );

        if (empty($parent_id_exists)) {
            // Add parent_id column
            $this->wpdb->query("ALTER TABLE {$this->ratings_table} ADD COLUMN parent_id mediumint(9) DEFAULT NULL AFTER total_rating");
            // Add effect_type column
            $this->wpdb->query("ALTER TABLE {$this->ratings_table} ADD COLUMN effect_type varchar(10) DEFAULT 'positive' AFTER parent_id");
            // Add display_only column
            $this->wpdb->query("ALTER TABLE {$this->ratings_table} ADD COLUMN display_only tinyint(1) DEFAULT 0 AFTER effect_type");
            // Add index for parent_id
            $this->wpdb->query("ALTER TABLE {$this->ratings_table} ADD KEY parent_id (parent_id)");
        }

        // Check if mirror_of column exists in ratings table
        $mirror_of_exists = $this->wpdb->get_results(
            "SHOW COLUMNS FROM {$this->ratings_table} LIKE 'mirror_of'"
        );

        if (empty($mirror_of_exists)) {
            // Add mirror_of column
            $this->wpdb->query("ALTER TABLE {$this->ratings_table} ADD COLUMN mirror_of mediumint(9) DEFAULT NULL AFTER display_only");
            // Add index for mirror_of
            $this->wpdb->query("ALTER TABLE {$this->ratings_table} ADD KEY mirror_of (mirror_of)");
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
    public function get_ratings_for_export() {
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
    public function get_votes_for_export($rating_id) {
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
