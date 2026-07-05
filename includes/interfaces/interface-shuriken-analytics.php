<?php
/**
 * Shuriken Reviews Analytics Interface
 *
 * Defines the contract for analytics operations in the Shuriken Reviews plugin.
 * This interface improves testability by allowing mock implementations.
 *
 * @package Shuriken_Reviews
 * @since 1.7.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface Shuriken_Analytics_Interface
 *
 * Contract for analytics operations. Composed of focused sub-interfaces per
 * concern (formatting, ranking, dashboard, rating stats, contextual) so add-on
 * decorators can implement only the slice they need, while this interface
 * remains the full contract for backward compatibility.
 *
 * @since 1.7.0
 */
interface Shuriken_Analytics_Interface extends
    Shuriken_Analytics_Formatter_Interface,
    Shuriken_Analytics_Ranking_Interface,
    Shuriken_Analytics_Dashboard_Interface,
    Shuriken_Analytics_Rating_Stats_Interface,
    Shuriken_Analytics_Context_Interface {

    /**
     * Build date condition for SQL queries
     *
     * @param string $date_range Date range identifier.
     * @param string $column     Column name for date comparison.
     * @return string SQL WHERE clause condition.
     */
    public function build_date_condition(string|int|array $date_range, string $column = 'date_created'): string;
}
