<?php
/**
 * Shuriken Schema Manager
 *
 * Handles database table creation, existence checks, and migrations.
 *
 * @package Shuriken_Reviews
 * @since 1.15.5
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_Schema_Manager
 *
 * Manages the plugin's database schema lifecycle.
 *
 * @since 1.15.5
 */
class Shuriken_Schema_Manager {

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
            label_description varchar(500) DEFAULT NULL,
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

        // v1.8.0: Add label_description column to ratings table
        if (version_compare($current_version, '1.8.0', '<')) {
            $col_exists = $this->wpdb->get_results(
                "SHOW COLUMNS FROM {$this->ratings_table} LIKE 'label_description'"
            );

            if (empty($col_exists)) {
                $this->wpdb->query("ALTER TABLE {$this->ratings_table} ADD COLUMN label_description varchar(500) DEFAULT NULL AFTER scale");
            }
        }

        return true;
    }
}
