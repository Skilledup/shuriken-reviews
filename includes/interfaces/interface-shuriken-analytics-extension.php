<?php
/**
 * Shuriken Reviews Analytics Extension Interface
 *
 * Defines the contract for extending analytics operations with extra stats on Shuriken.
 *
 * @package Shuriken_Reviews
 * @since 1.15.x
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface Shuriken_Analytics_Extension_Interface
 *
 * Contract for custom analytics extensions.
 *
 * @since 1.15.x
 */
interface Shuriken_Analytics_Extension_Interface {

    /**
     * Get extra statistics from third-party extensions
     *
     * @param string|int|array $date_range Date range filter.
     * @return array Custom statistics array.
     */
    public function get_extra_stats(string|int|array $date_range = 'all'): array;
}
