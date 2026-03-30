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
 * Provides build_date_condition() for any analytics class that works with
 * date-filtered queries. Classes using this trait must have a $wpdb property.
 *
 * @since 1.14.0
 */
trait Shuriken_Analytics_Helpers {

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
