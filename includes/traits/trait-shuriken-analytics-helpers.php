<?php
/**
 * Shuriken Analytics Helpers Trait
 *
 * Shared database-aware helper methods used by multiple analytics classes.
 * Requires $this->wpdb to be set on the using class.
 *
 * @package Shuriken_Reviews
 * @since 1.14.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Trait Shuriken_Analytics_Helpers
 *
 * Provides build_date_condition() and rating-type utilities for any analytics
 * class that works with date-filtered queries. Classes using this trait must
 * have a $wpdb property.
 *
 * @since 1.14.0
 */
trait Shuriken_Analytics_Helpers {

    /**
     * Check if a rating type uses binary values (0/1) instead of a 1-N scale
     *
     * @param string $rating_type Rating type identifier
     * @return bool
     */
    protected function is_binary_type(string $rating_type): bool {
        return (Shuriken_Rating_Type::tryFrom($rating_type) ?? Shuriken_Rating_Type::Stars)->isBinary();
    }

    /**
     * Build an empty distribution array for a rating type and scale
     *
     * Stars/numeric: keys 1 through scale (e.g., {1:0, 2:0, 3:0, 4:0, 5:0})
     * Binary: keys 0 and 1 (e.g., {0:0, 1:0})
     *
     * @param string $rating_type Rating type
     * @param int $scale Rating scale
     * @return array Empty distribution array
     */
    protected function build_empty_distribution(string $rating_type, int $scale): array {
        if ($this->is_binary_type($rating_type)) {
            return array(0 => 0, 1 => 0);
        }
        return array_fill(1, Shuriken_Database::RATING_SCALE_DEFAULT, 0);
    }

    /**
     * Build date condition SQL for filtering by date range
     * 
     * Supports three modes:
     * 1. Relative days: Pass a number like '30' for "last 30 days"
     * 2. Custom range: Pass an array with 'start' and/or 'end' keys (Y-m-d format)
     * 3. All time: Pass 'all' or empty value
     *
     * @param string|int|array $date_range Number of days, 'all', or array with 'start'/'end' keys
     * @param string $column Column name for date comparison (can include table alias like 'v.date_created')
     * @return string SQL condition string
     */
    public function build_date_condition(string|int|array $date_range, string $column = 'date_created'): string {
        // No filter for 'all' or empty
        if ($date_range === 'all' || empty($date_range)) {
            return '';
        }
        
        // Custom date range with start and/or end dates
        if (is_array($date_range)) {
            $conditions = array();
            
            if (!empty($date_range['start'])) {
                $start_date = sanitize_text_field($date_range['start']);
                // Validate date format (Y-m-d)
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
                    $conditions[] = $this->wpdb->prepare(
                        "{$column} >= %s",
                        $start_date . ' 00:00:00'
                    );
                }
            }
            
            if (!empty($date_range['end'])) {
                $end_date = sanitize_text_field($date_range['end']);
                // Validate date format (Y-m-d)
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
                    $conditions[] = $this->wpdb->prepare(
                        "{$column} <= %s",
                        $end_date . ' 23:59:59'
                    );
                }
            }
            
            if (!empty($conditions)) {
                return ' AND ' . implode(' AND ', $conditions);
            }
            return '';
        }
        
        // Relative days (e.g., '30' for last 30 days)
        return $this->wpdb->prepare(
            " AND {$column} >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            intval($date_range)
        );
    }
}
