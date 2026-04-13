<?php
/**
 * Shuriken Vote Repository
 *
 * Handles vote CRUD operations and rate-limiting queries.
 *
 * @package Shuriken_Reviews
 * @since 1.15.5
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_Vote_Repository
 *
 * Manages vote insert/update and rate-limit timestamp queries.
 *
 * @since 1.15.5
 */
class Shuriken_Vote_Repository {

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

    // =========================================================================
    // Vote CRUD
    // =========================================================================

    /**
     * Get a user's vote for a specific rating
     *
     * @param int         $rating_id    Rating ID
     * @param int         $user_id      User ID (0 for guests)
     * @param string|null $user_ip      IP address for guest votes
     * @param int|null    $context_id   Optional post/entity ID for contextual votes
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
     * @param int         $rating_id    Rating ID
     * @param float|int   $rating_value Rating value (normalized)
     * @param int         $user_id      User ID (0 for guests)
     * @param string|null $user_ip      IP address for guest votes
     * @param int|null    $context_id   Optional post/entity ID for contextual votes
     * @param string|null $context_type Optional context type ('post', 'product', etc.)
     * @return bool True on success
     * @throws Shuriken_Database_Exception If insert fails or transaction fails
     * @throws Shuriken_Validation_Exception If rating_value is invalid
     */
    public function create_vote(int $rating_id, float|int $rating_value, int $user_id = 0, ?string $user_ip = null, ?int $context_id = null, ?string $context_type = null): bool {
        // Validate rating value against normalized scale (1-5 for stars/numeric, 0/1 for binary types)
        if ($rating_value < 0 || $rating_value > Shuriken_Database::RATING_SCALE_DEFAULT) {
            throw Shuriken_Validation_Exception::out_of_range('rating_value', $rating_value, 0, Shuriken_Database::RATING_SCALE_DEFAULT);
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
            throw $e;
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
            throw Shuriken_Database_Exception::transaction_failed();
        }
    }

    /**
     * Update an existing vote
     *
     * @param int       $vote_id   Vote ID
     * @param int       $rating_id Rating ID
     * @param float|int $old_value Previous rating value
     * @param float|int $new_value New rating value
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
}
